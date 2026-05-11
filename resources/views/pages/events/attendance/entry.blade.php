<?php

use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\Student;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Component;

new #[Lazy] #[Layout('layouts.app')] class extends Component {
    public function placeholder()
    {
        return <<<'HTML'
        <div class="space-y-6 w-full animate-pulse">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                <div class="space-y-2">
                    <div class="h-8 w-64 bg-zinc-200 dark:bg-zinc-800 rounded-lg"></div>
                    <div class="h-4 w-48 bg-zinc-100 dark:bg-zinc-900 rounded-lg"></div>
                </div>
                <div class="h-10 w-32 bg-zinc-200 dark:bg-zinc-800 rounded-lg"></div>
            </div>
            <div class="space-y-4">
                <div class="h-12 w-full bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-zinc-100 dark:border-zinc-800"></div>
                <div class="h-64 w-full bg-zinc-50 dark:bg-zinc-900 rounded-2xl border border-zinc-100 dark:border-zinc-800"></div>
            </div>
        </div>
        HTML;
    }

    public $event;

    public $students = [];

    public $attendance = [];

    public function mount($eventId)
    {
        $this->event = Event::with('levels')->findOrFail($eventId);
        $this->loadStudents();
    }

    protected function hasApprovedPermission($studentId, $date)
    {
        return \App\Models\StudentPermission::where('student_id', $studentId)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();
    }

    public function loadStudents()
    {
        $levelIds = $this->event->levels->pluck('id');
        $this->students = Student::whereIn('level_id', $levelIds)
            ->where('school_id', $this->event->school_id)
            ->orderBy('full_name')
            ->get();

        // Load existing attendance
        $existingAttendance = EventAttendance::where('event_id', $this->event->id)
            ->get()
            ->keyBy('student_id');

        foreach ($this->students as $student) {
            $record = $existingAttendance->get($student->id);
            if ($record) {
                $this->attendance[$student->id] = $record->status;
            } elseif ($this->hasApprovedPermission($student->id, $this->event->event_date)) {
                $this->attendance[$student->id] = 'IZIN';
            } else {
                $this->attendance[$student->id] = 'ABSENT';
            }
        }
    }

    public function saveAttendance()
    {
        foreach ($this->attendance as $studentId => $status) {
            // Only save if status is PRESENT or LATE. If ABSENT, we might delete or just skip?
            // Schema says enum: PRESENT, LATE. So we probably only store records for those present/late.

            if (in_array($status, ['PRESENT', 'LATE', 'IZIN'])) {
                EventAttendance::updateOrCreate(
                    [
                        'event_id' => $this->event->id,
                        'student_id' => $studentId,
                    ],
                    [
                        'check_in_time' => now(), // Or event date?
                        'status' => $status,
                    ]
                );
            } else {
                // If marked absent (or unselected), remove record if exists
                EventAttendance::where('event_id', $this->event->id)
                    ->where('student_id', $studentId)
                    ->delete();
            }
        }

        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Presensi berhasil disimpan.');
    }

    public function markAllPresent()
    {
        foreach ($this->students as $student) {
            $this->attendance[$student->id] = 'PRESENT';
        }
    }
}; ?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <flux:breadcrumbs class="mb-2">
            <flux:breadcrumbs.item href="{{ route('events.index') }}" wire:navigate>Agenda Kegiatan
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Presensi</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <flux:heading size="xl" level="1">Presensi: {{ $this->event->title }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ $this->event->event_date->format('d F Y') }}</flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    @if(!auth()->user()->hasRole('guru'))
    <div class="flex justify-end gap-2 mb-4">
        <flux:button wire:click="markAllPresent" size="sm" icon="check-circle">Hadir Semua</flux:button>
        <flux:button wire:click="saveAttendance" variant="primary" size="sm" icon="document-check">Simpan Presensi
        </flux:button>
    </div>
    @endif

    <div class="space-y-4">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Nama Santri</flux:table.column>
                <flux:table.column>Kelas</flux:table.column>
                <flux:table.column>Status</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($students as $student)
                    <flux:table.row :key="$student->id">
                        <flux:table.cell>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $student->full_name }}</div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge size="sm">{{ $student->level->name ?? '-' }}</flux:badge>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="flex gap-2">
                                @php $isGuru = auth()->user()->hasRole('guru'); @endphp
                                <flux:radio.group wire:model="attendance.{{ $student->id }}" variant="cards"
                                    class="flex-row">
                                    <flux:radio value="PRESENT" label="Hadir" :disabled="$isGuru" />
                                    <flux:radio value="LATE" label="Telat" :disabled="$isGuru" />
                                    <flux:radio value="IZIN" label="Izin" :disabled="$isGuru" />
                                    <flux:radio value="ABSENT" label="Alpa" :disabled="$isGuru" />
                                </flux:radio.group>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="3" class="text-center py-8">
                            <div class="text-zinc-500">Tidak ada santri di level kegiatan ini.</div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>