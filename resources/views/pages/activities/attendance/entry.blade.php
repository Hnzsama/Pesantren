<?php

use App\Models\Activity;
use App\Models\ActivityAttendance;
use App\Models\Level;
use App\Models\Student;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Carbon\Carbon;
use Flux\Flux;

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

    public $date;
    public $levelId;
    public $activityId;

    public $levels = [];
    public $activities = [];
    public $students = [];
    public $attendance = []; // [student_id => status]
    public $remarks = []; // [student_id => remarks]

    public function loadActivities()
    {
        $user = auth()->user();
        $schoolId = $user->hasRole('admin') ? $user->school_id : null;

        $query = Activity::query()
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->orderBy('name')
            ->when($this->levelId, function ($q) {
                $q->whereHas('levels', function ($query) {
                    $query->where('levels.id', $this->levelId);
                });
            });

        $this->activities = $query->get();
    }

    public function mount($date = null, $activityId = null)
    {
        $this->date = $date ?? Carbon::today()->format('Y-m-d');
        $this->activityId = $activityId;
        $this->levels = Level::orderBy('name')->get(); // This might also need school scope if Level doesn't have it

        $this->loadActivities(); // Load initial activities

        if ($this->date && $this->activityId) {
            $this->loadStudents();
        }
    }

    public function updatedLevelId($value)
    {
        $this->loadActivities();
        $this->loadStudents();
    }

    public function updatedActivityId()
    {
        $this->loadStudents();
    }

    public function updatedDate()
    {
        $this->loadStudents();
    }

    public function loadStudents()
    {
        if (!$this->levelId || !$this->activityId || !$this->date) {
            $this->students = [];
            return;
        }

        $this->students = Student::where('level_id', $this->levelId)
            ->orderBy('name')
            ->get();

        // Load existing attendance for this date and activity
        $existing = ActivityAttendance::where('activity_id', $this->activityId)
            ->where('date', $this->date)
            ->get()
            ->keyBy('student_id');

        $this->attendance = [];
        $this->remarks = [];

        foreach ($this->students as $student) {
            if ($record = $existing->get($student->id)) {
                $this->attendance[$student->id] = $record->status;
                $this->remarks[$student->id] = $record->remarks;
            } else {
                $this->attendance[$student->id] = 'present'; // Default to present?
                $this->remarks[$student->id] = '';
            }
        }
    }

    public function save()
    {
        $this->validate([
            'date' => 'required|date',
            'activityId' => 'required|exists:activities,id',
            'levelId' => 'required|exists:levels,id',
        ]);

        $user = auth()->user();
        $schoolId = $user->hasAnyRole(['admin', 'guru']) ? $user->school_id : null;
        if (!$schoolId) {
            $activity = Activity::find($this->activityId);
            $schoolId = $activity->school_id;
        }

        foreach ($this->students as $student) {
            $status = $this->attendance[$student->id] ?? null;
            $remark = $this->remarks[$student->id] ?? null;

            if ($status) {
                ActivityAttendance::updateOrCreate(
                    [
                        'activity_id' => $this->activityId,
                        'student_id' => $student->id,
                        'date' => $this->date,
                    ],
                    [
                        'school_id' => $schoolId,
                        'student_name' => $student->full_name,
                        'student_nis' => $student->nis,
                        'status' => $status,
                        'remarks' => $remark,
                    ]
                );
            }
        }

        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Presensi kegiatan berhasil disimpan.');
    }
}; ?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <flux:heading size="xl" level="1">Presensi Kegiatan Harian</flux:heading>
        <flux:subheading size="lg" class="mb-6">Catat kehadiran santri pada kegiatan harian/rutin.</flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    <!-- Filters -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <flux:input type="date" wire:model.live="date" label="Tanggal" />

        <flux:select wire:model.live="levelId" label="Kelas" variant="listbox" placeholder="Pilih Kelas...">
            <flux:select.option value="">-- Pilih Kelas --</flux:select.option>
            @foreach($levels as $level)
                <flux:select.option value="{{ $level->id }}">{{ $level->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="activityId" label="Kegiatan" variant="listbox" placeholder="Pilih Kegiatan...">
            <flux:select.option value="">-- Pilih Kegiatan --</flux:select.option>
            @foreach($activities as $activity)
                <flux:select.option value="{{ $activity->id }}">{{ $activity->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <div class="flex items-end">
            @if(!auth()->user()->hasRole('guru'))
            <flux:button wire:click="save" variant="primary" icon="document-check" class="w-full"
                :disabled="empty($students)">Simpan Data</flux:button>
            @endif
        </div>
    </div>

    <!-- Table -->
    <div class="space-y-4">
        @if($levelId && $activityId)
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Nama Santri</flux:table.column>
                    <flux:table.column>Status Kehadiran</flux:table.column>
                    <flux:table.column>Catatan</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($students as $student)
                        <flux:table.row :key="$student->id">
                            <flux:table.cell>
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $student->full_name }}</div>
                                <div class="text-xs text-zinc-500">{{ $student->nis }}</div>
                            </flux:table.cell>

                            <flux:table.cell>
                                @php $isGuru = auth()->user()->hasRole('guru'); @endphp
                                <flux:radio.group wire:model="attendance.{{ $student->id }}" variant="segmented" size="sm">
                                    <flux:radio value="present" label="Hadir" :disabled="$isGuru" />
                                    <flux:radio value="late" label="Telat" :disabled="$isGuru" />
                                    <flux:radio value="permission" label="Ijin" :disabled="$isGuru" />
                                    <flux:radio value="absent" label="Alpa" :disabled="$isGuru" />
                                </flux:radio.group>
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:input wire:model="remarks.{{ $student->id }}" size="sm" placeholder="Keterangan..." :disabled="$isGuru" />
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="3" class="text-center py-8">
                                <div class="text-zinc-500">Tidak ada santri di kelas ini.</div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        @else
            <div
                class="text-center py-12 text-zinc-400 bg-zinc-50 dark:bg-zinc-900 rounded-lg border border-dashed border-zinc-200 dark:border-zinc-700">
                <div class="mb-2">
                    <flux:icon.calendar-days class="w-12 h-12 mx-auto text-zinc-300" />
                </div>
                Silakan pilih Tanggal, Kelas, dan Kegiatan terlebih dahulu.
            </div>
        @endif
    </div>
</div>