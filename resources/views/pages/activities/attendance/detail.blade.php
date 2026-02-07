<?php

use App\Models\Activity;
use App\Models\ActivityAttendance;
use App\Models\Level;
use App\Models\Student;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy] #[Layout('layouts.app')] class extends Component
{
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

    use WithPagination;

    #[Url]
    public string $date = '';

    #[Url]
    public string $search = '';

    #[Url]
    public $level_id = '';

    public function mount()
    {
        if (empty($this->date)) {
            $this->date = Carbon::today()->format('Y-m-d');
        }

        if (empty($this->level_id)) {
            $firstLevel = Level::orderBy('name')->first();
            if ($firstLevel) {
                $this->level_id = $firstLevel->id;
            }
        }
    }

    public function updated($property)
    {
        if (in_array($property, ['search', 'level_id', 'date'])) {
            $this->resetPage();
        }
    }

    #[Livewire\Attributes\Computed]
    public function levels()
    {
        return Level::orderBy('name')->get();
    }

    #[Livewire\Attributes\Computed]
    public function activities()
    {
        $user = auth()->user();
        $schoolId = $user->hasRole('admin') ? $user->school_id : null;

        return Activity::query()
            ->where('type', 'daily')
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->when($this->level_id, function ($q) {
                $q->where(function ($query) {
                    $query->where('level_id', $this->level_id)
                        ->orWhereNull('level_id');
                });
            })
            ->orderBy('name')
            ->get();
    }

    #[Livewire\Attributes\Computed]
    public function students()
    {
        $user = auth()->user();
        $schoolId = $user->hasRole('admin') ? $user->school_id : null;

        return Student::query()
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->when($this->search, fn ($q) => $q->where('full_name', 'like', "%{$this->search}%")->orWhere('nis', 'like', "%{$this->search}%"))
            ->when($this->level_id, fn ($q) => $q->where('level_id', $this->level_id))
            ->where('status', 'ACTIVE')
            ->orderBy('full_name')
            ->paginate(10);
    }

    #[Livewire\Attributes\Computed]
    public function attendanceMatrix()
    {
        $studentIds = $this->students->pluck('id')->toArray();
        $activityIds = $this->activities->pluck('id')->toArray();

        $records = ActivityAttendance::whereIn('student_id', $studentIds)
            ->whereIn('activity_id', $activityIds)
            ->where('date', $this->date)
            ->get();

        $matrix = [];
        foreach ($records as $record) {
            $matrix[$record->student_id][$record->activity_id] = $record;
        }

        return $matrix;
    }

    public function setStatus($studentId, $activityId, $status)
    {
        $student = Student::find($studentId);
        if (! $student) {
            return;
        }

        ActivityAttendance::updateOrCreate(
            [
                'student_id' => $studentId,
                'activity_id' => $activityId,
                'date' => $this->date,
            ],
            [
                'school_id' => $student->school_id,
                'status' => $status,
            ]
        );

        unset($this->attendanceMatrix);
        $this->dispatch('attendance-updated');
    }
}; ?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">Detail Absensi Kegiatan Harian</flux:heading>
                <flux:subheading size="lg" class="mb-6">Kelola status kehadiran santri per kegiatan.</flux:subheading>
            </div>
            <flux:button href="{{ route('activities.attendance.index') }}" variant="ghost" icon="arrow-left"
                wire:navigate>
                Kembali
            </flux:button>
        </div>
        <flux:separator variant="subtle" />
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <flux:input type="date" wire:model.live="date" label="Tanggal" />

        <flux:select variant="listbox" wire:model.live="level_id" label="Jenjang / Kelas" placeholder="Pilih Jenjang">
            @foreach ($this->levels as $level)
                <flux:select.option value="{{ $level->id }}">{{ $level->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:input wire:model.live.debounce.300ms="search" label="Cari Santri" placeholder="Nama..."
            icon="magnifying-glass" />
    </div>

    <div class="overflow-x-auto space-y-4">
        <flux:table :paginate="$this->students">
            <flux:table.columns>
                <flux:table.column>Nama Santri</flux:table.column>
                @foreach ($this->activities as $activity)
                    <flux:table.column align="center">{{ $activity->name }}</flux:table.column>
                @endforeach
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->students as $student)
                    <flux:table.row :key="$student->id">
                        <flux:table.cell>
                            <div class="font-medium">{{ $student->full_name }}</div>
                            <div class="text-xs text-zinc-500">{{ $student->nis }}</div>
                        </flux:table.cell>

                        @foreach ($this->activities as $activity)
                            @php
                                $record = $this->attendanceMatrix[$student->id][$activity->id] ?? null;
                                $currentStatus = $record?->status ?? 'absent'; 
                            @endphp
                            <flux:table.cell align="center">
                                <div class="flex items-center justify-center gap-1">
                                    {{-- Hadir --}}
                                    <flux:button wire:click="setStatus('{{ $student->id }}', '{{ $activity->id }}', 'present')"
                                        size="sm" :variant="$currentStatus === 'present' ? 'primary' : 'ghost'" color="green"
                                        icon="check-circle"
                                        :class="$currentStatus === 'present' ? '' : 'text-green-600 dark:text-green-400'"
                                        title="Hadir" class="!px-2" />

                                    {{-- Izin --}}
                                    <flux:button
                                        wire:click="setStatus('{{ $student->id }}', '{{ $activity->id }}', 'permission')"
                                        size="sm" :variant="$currentStatus === 'permission' ? 'primary' : 'ghost'"
                                        color="orange" icon="information-circle"
                                        :class="$currentStatus === 'permission' ? '' : 'text-orange-600 dark:text-orange-400'"
                                        title="Izin" class="!px-2" />

                                    {{-- Alpha --}}
                                    <flux:button wire:click="setStatus('{{ $student->id }}', '{{ $activity->id }}', 'absent')"
                                        size="sm" :variant="$currentStatus === 'absent' ? 'primary' : 'ghost'" color="red"
                                        icon="x-circle"
                                        :class="$currentStatus === 'absent' ? '' : 'text-red-600 dark:text-red-400'"
                                        title="Alpha" class="!px-2" />
                                </div>
                            </flux:table.cell>
                        @endforeach
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>
</div>