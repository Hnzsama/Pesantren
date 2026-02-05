<?php

use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\Level;
use App\Models\Student;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $event_id = '';

    #[Url]
    public string $search = '';

    #[Url]
    public $level_id = '';

    public function mount()
    {
        if (empty($this->event_id)) {
            // Default to latest event or redirect?
            // For now, let's require event_id or show empty.
            // Or maybe list events to pick?
            // The "detail" page usually shows ONE event.
            // If no event_id, maybe redirect back?
            // But the user pattern for "Detail Harian" was a full page.
            // For "Detail Agenda", maybe it's "Select Event" then show list?
            // Let's assume we pass event_id param or select first one properly.

            $latestEvent = Event::orderBy('event_date', 'desc')->first();
            if ($latestEvent) {
                $this->event_id = $latestEvent->id;
            }
        }
    }

    #[Livewire\Attributes\Computed]
    public function event()
    {
        return Event::find($this->event_id);
    }

    #[Livewire\Attributes\Computed]
    public function levels()
    {
        return Level::orderBy('name')->get();
    }

    #[Livewire\Attributes\Computed]
    public function availableEvents()
    {
        $user = auth()->user();
        $schoolId = $user->hasRole('admin') ? $user->school_id : null;

        return Event::query()
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->orderBy('event_date', 'desc')
            ->limit(50) // Limit to recent events
            ->get();
    }

    #[Livewire\Attributes\Computed]
    public function students()
    {
        $user = auth()->user();
        $schoolId = $user->hasRole('admin') ? $user->school_id : null;

        return Student::query()
            ->with('level')
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->when($this->search, fn ($q) => $q->where('full_name', 'like', "%{$this->search}%")->orWhere('nis', 'like', "%{$this->search}%"))
            ->when($this->level_id, fn ($q) => $q->where('level_id', $this->level_id))
            ->where('status', 'ACTIVE')
            ->orderBy('full_name')
            ->paginate(10);
    }

    #[Livewire\Attributes\Computed]
    public function attendanceStatusMap()
    {
        if (! $this->event_id) {
            return collect();
        }

        return EventAttendance::where('event_id', $this->event_id)
            ->get()
            ->keyBy('student_id');
    }

    public function setStatus($studentId, $status)
    {
        if (! $this->event) {
            return;
        }

        EventAttendance::updateOrCreate(
            [
                'event_id' => $this->event->id,
                'student_id' => $studentId,
            ],
            [
                'check_in_time' => now(), // Or keep existing? For manual toggle, maybe irrelevant
                'status' => $status,
            ]
        );

        // If set to absent (status 'absent' or null?), usually we delete or set status.
        // EventAttendance usually implies presence. If "absent", maybe we delete the record or have explicit status.
        // Model has 'status' field.
        // If status is 'absent' and we want to remove attendance?
        // Let's assume 'status' field can store 'present', 'permission', 'absent'.

        if ($status === 'absent') {
            // Optional: Delete record if "absent" means "not attended" and we don't track absence reason?
            // But user might want to track Alpha.
            // We will update status.
        }

        // Clear computed cache to refresh attendance display
        unset($this->attendanceStatusMap);

        $this->dispatch('attendance-updated');
    }
}; ?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">Detail Presensi Agenda</flux:heading>
                <flux:subheading size="lg" class="mb-6">Manajemen kehadiran per agenda kegiatan.</flux:subheading>
            </div>
            <flux:button href="{{ route('events.attendance.index') }}" variant="ghost" icon="arrow-left" wire:navigate>
                Kembali
            </flux:button>
        </div>
        <flux:separator variant="subtle" />
    </div>

    {{-- Filters --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <flux:select variant="listbox" wire:model.live="event_id" label="Agenda Kegiatan" placeholder="Pilih Agenda">
            @foreach ($this->availableEvents as $e)
                <flux:select.option value="{{ $e->id }}">
                    {{ $e->title }} - {{ \Carbon\Carbon::parse($e->event_date)->format('d M Y') }}
                </flux:select.option>
            @endforeach
        </flux:select>

        <flux:select variant="listbox" wire:model.live="level_id" label="Jenjang / Kelas" placeholder="Semua Kelas">
            <flux:select.option value="">Semua Kelas</flux:select.option>
            @foreach ($this->levels as $level)
                <flux:select.option value="{{ $level->id }}">{{ $level->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:input wire:model.live.debounce.300ms="search" label="Cari Santri" placeholder="Nama / NIS..."
            icon="magnifying-glass" />
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto space-y-4">
        <flux:table :paginate="$this->students">
            <flux:table.columns>
                <flux:table.column>Nama Santri</flux:table.column>
                <flux:table.column>Kelas</flux:table.column>
                <flux:table.column align="center">Status Kehadiran</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->students as $student)
                    @php
                        // Get attendance status from map
                        $attendance = $this->attendanceStatusMap->get($student->id);
                        $status = $attendance ? strtolower($attendance->status) : 'absent';
                    @endphp
                    <flux:table.row :key="$student->id">
                        <flux:table.cell>
                            <div class="font-medium">{{ $student->full_name }}</div>
                            <div class="text-xs text-zinc-500">{{ $student->nis }}</div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge size="sm" color="zinc">{{ $student->level->name ?? '-' }}</flux:badge>
                        </flux:table.cell>

                        <flux:table.cell align="center">
                            <div class="flex items-center justify-center gap-1">
                                {{-- Hadir --}}
                                <flux:button wire:click="setStatus('{{ $student->id }}', 'present')" size="sm"
                                    :variant="$status === 'present' ? 'primary' : 'ghost'" color="green" icon="check-circle"
                                    :class="$status === 'present' ? '' : 'text-green-600 dark:text-green-400'" title="Hadir"
                                    class="!px-2" />

                                {{-- Izin --}}
                                <flux:button wire:click="setStatus('{{ $student->id }}', 'permission')" size="sm"
                                    :variant="$status === 'permission' ? 'primary' : 'ghost'" color="orange"
                                    icon="information-circle"
                                    :class="$status === 'permission' ? '' : 'text-orange-600 dark:text-orange-400'"
                                    title="Izin" class="!px-2" />

                                {{-- Alpha/Absent --}}
                                <flux:button wire:click="setStatus('{{ $student->id }}', 'absent')" size="sm"
                                    :variant="$status === 'absent' ? 'primary' : 'ghost'" color="red" icon="x-circle"
                                    :class="$status === 'absent' ? '' : 'text-red-600 dark:text-red-400'"
                                    title="Alpha / Tidak Hadir" class="!px-2" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>
</div>