<?php

use App\Models\ActivityAttendance;
use App\Models\Activity;
use App\Models\Level;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Flux\Flux;
use Carbon\Carbon;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public $dateFilter;
    public $activityFilter;
    public $levelFilter;

    public $activities = [];
    public $levels = [];

    public function mount()
    {
        $this->dateFilter = Carbon::today()->format('Y-m-d');
        $this->levels = Level::orderBy('name')->get();
        $this->loadActivities();
    }

    public function updatedLevelFilter()
    {
        $this->loadActivities();
        $this->resetPage();
    }

    public function loadActivities()
    {
        $user = auth()->user();
        $schoolId = $user->hasRole('admin') ? $user->school_id : null;

        $query = Activity::query()
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->orderBy('name');

        if ($this->levelFilter) {
            $query->where(function ($q) {
                $q->where('level_id', $this->levelFilter)
                    ->orWhereNull('level_id');
            });
        }

        $this->activities = $query->get();
    }

    public function with(): array
    {
        $user = auth()->user();
        $schoolId = $user->hasRole('admin') ? $user->school_id : null;

        // Grouping by Date, Activity to show "Sessions"
        // Since Eloquent doesn't make distinct grouping easy for this view without custom queries, 
        // we might just show recent logs or use a custom query.
        // Let's try to query distinct date/activity pairs.

        $sessions = ActivityAttendance::query()
            ->select('date', 'activity_id', 'school_id')
            ->distinct()
            ->with('activity')
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->when($this->dateFilter, fn($q) => $q->where('date', $this->dateFilter))
            ->when($this->activityFilter, fn($q) => $q->where('activity_id', $this->activityFilter))
            // We might want to filter sessions by Level too (via students or activity relation)
            // But ActivityAttendance doesn't explicitly store level_id (it stores student_id).
            // Schema: school_id, student_id, activity_id.
            // If we filter, we might need whereHas('student', fn($q) => $q->where('level_id', $this->levelFilter))
            ->when($this->levelFilter, function ($q) {
                $q->whereHas('student', fn($sq) => $sq->where('level_id', $this->levelFilter));
            })
            ->orderBy('date', 'desc')
            ->orderBy('activity_id')
            ->paginate(10);

        // Calculate counts for each session manually or via subquery? 
        // For simplicity, let's just show the session. We can add counts later if needed for perf.

        return [
            'sessions' => $sessions,
        ];
    }

    public function getAttendanceCount($date, $activityId)
    {
        return ActivityAttendance::where('date', $date)
            ->where('activity_id', $activityId)
            ->when($this->levelFilter, function ($q) {
                $q->whereHas('student', fn($sq) => $sq->where('level_id', $this->levelFilter));
            })
            ->count();
    }
}; ?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">Riwayat Presensi Kegiatan</flux:heading>
                <flux:subheading size="lg" class="mb-6">Daftar pencatatan presensi kegiatan harian.</flux:subheading>
            </div>
            <flux:button href="{{ route('activities.attendance.entry') }}" variant="primary" icon="plus" wire:navigate>
                Catat Presensi Baru</flux:button>
        </div>
        <flux:separator variant="subtle" />
    </div>

    <!-- Filters -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <flux:input type="date" wire:model.live="dateFilter" label="Tanggal" />

        <flux:select wire:model.live="levelFilter" label="Kelas" variant="listbox" placeholder="Pilih Kelas...">
            <flux:select.option value="">Semua Kelas</flux:select.option>
            @foreach($levels as $level)
                <flux:select.option value="{{ $level->id }}">{{ $level->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="activityFilter" label="Kegiatan" variant="listbox" placeholder="Semua Kegiatan">
            <flux:select.option value="">Semua Kegiatan</flux:select.option>
            @foreach($activities as $activity)
                <flux:select.option value="{{ $activity->id }}">{{ $activity->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <!-- Table -->
    <div class="space-y-4">
        <flux:table :paginate="$sessions">
            <flux:table.columns>
                <flux:table.column>Tanggal</flux:table.column>
                <flux:table.column>Kegiatan</flux:table.column>
                <flux:table.column>Jumlah Tercatat</flux:table.column>
                <flux:table.column>Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($sessions as $session)
                    <flux:table.row :key="$session->date . $session->activity_id">
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <flux:icon.calendar-days class="w-4 h-4 text-zinc-400" />
                                {{ \Carbon\Carbon::parse($session->date)->format('d F Y') }}
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $session->activity->name ?? '-' }}
                            </div>
                            <div class="text-xs text-zinc-500">{{ $session->activity->type ?? '' }}</div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge size="sm" color="zinc">
                                {{ $this->getAttendanceCount($session->date, $session->activity_id) }} Santri
                            </flux:badge>
                        </flux:table.cell>

                        <flux:table.cell>
                            {{-- Link to Entry page pre-filled with this date and activity. Note: Entry page needs query
                            params or mount logic --}}
                            {{-- Since Entry page uses wire:model for date/activity, passing params might need 'mount'
                            update in Entry page --}}
                            {{-- Let's assume standard link roughly works or user selects again. But ideally we pass params.
                            --}}
                            <flux:button
                                href="{{ route('activities.attendance.entry', ['date' => $session->date, 'activityId' => $session->activity_id]) }}"
                                icon="pencil-square" size="sm" wire:navigate>
                                Detail / Edit
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center py-8">
                            <div class="text-zinc-500">Belum ada riwayat presensi yang sesuai filter.</div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>