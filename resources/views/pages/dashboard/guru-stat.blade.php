<?php

use App\Models\Student;
use App\Models\ActivityAttendance;
use App\Models\EventAttendance;
use App\Models\Activity;
use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new #[Lazy] #[Layout('layouts.app')] #[Title('Dashboard Guru')] class extends Component {
    public function placeholder()
    {
        return <<<'HTML'
        <div class="space-y-8 w-full animate-pulse">
            <div class="h-10 w-64 bg-zinc-200 dark:bg-zinc-800 rounded-lg"></div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="h-32 bg-zinc-100 dark:bg-zinc-800 rounded-3xl border border-zinc-100 dark:border-zinc-800"></div>
                <div class="h-32 bg-zinc-100 dark:bg-zinc-800 rounded-3xl border border-zinc-100 dark:border-zinc-800"></div>
                <div class="h-32 bg-zinc-100 dark:bg-zinc-800 rounded-3xl border border-zinc-100 dark:border-zinc-800"></div>
            </div>
            <div class="h-96 bg-zinc-50 dark:bg-zinc-900 rounded-3xl border border-zinc-100 dark:border-zinc-800"></div>
        </div>
        HTML;
    }

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;
        $today = now()->toDateString();

        // 1. Total Students
        $totalStudents = Student::where('school_id', $schoolId)->where('status', 'ACTIVE')->count();

        // 2. Presensi Harian (Activity Type: daily)
        $dailyAttendance = ActivityAttendance::whereHas('activity', fn($q) => $q->where('type', 'daily'))
            ->where('date', $today)
            ->whereHas('student', fn($q) => $q->where('school_id', $schoolId))
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // 3. Presensi Kegiatan (Activity Type: rutinan)
        $activityAttendance = ActivityAttendance::whereHas('activity', fn($q) => $q->where('type', 'rutinan'))
            ->where('date', $today)
            ->whereHas('student', fn($q) => $q->where('school_id', $schoolId))
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // 4. Presensi Agenda (Events)
        $eventAttendance = EventAttendance::whereHas('event', fn($q) => $q->whereDate('event_date', $today)->where('school_id', $schoolId))
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // 5. Recent Activity List
        $recentActivities = ActivityAttendance::with(['student', 'activity'])
            ->where('date', $today)
            ->whereHas('student', fn($q) => $q->where('school_id', $schoolId))
            ->latest()
            ->take(5)
            ->get();

        return [
            'totalStudents' => $totalStudents,
            'daily' => [
                'present' => $dailyAttendance['present'] ?? 0,
                'late' => $dailyAttendance['late'] ?? 0,
                'absent' => $dailyAttendance['absent'] ?? 0,
                'permission' => $dailyAttendance['permission'] ?? 0,
            ],
            'activity' => [
                'present' => $activityAttendance['present'] ?? 0,
                'late' => $activityAttendance['late'] ?? 0,
                'absent' => $activityAttendance['absent'] ?? 0,
                'permission' => $activityAttendance['permission'] ?? 0,
            ],
            'event' => [
                'present' => ($eventAttendance['PRESENT'] ?? 0) + ($eventAttendance['present'] ?? 0),
                'late' => ($eventAttendance['LATE'] ?? 0) + ($eventAttendance['late'] ?? 0),
                'izin' => ($eventAttendance['IZIN'] ?? 0) + ($eventAttendance['izin'] ?? 0),
            ],
            'recentActivities' => $recentActivities,
        ];
    }
}; ?>

<div class="space-y-10">
    <div class="flex flex-col md:flex-row items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Dashboard Guru') }}</flux:heading>
            <flux:subheading size="lg">{{ __('Ringkasan presensi harian, kegiatan, dan agenda hari ini.') }}
            </flux:subheading>
        </div>
        <flux:button icon="arrow-path" variant="subtle" wire:click="$refresh" class="shadow-sm">Refresh</flux:button>
    </div>

    <!-- Metrics Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Presensi Harian -->
        <flux:card
            class="relative overflow-hidden group hover:shadow-lg transition-all duration-300 border-none bg-gradient-to-br from-emerald-500/10 to-teal-500/5 dark:from-emerald-500/15 dark:to-teal-500/10 ring-1 ring-emerald-100 dark:ring-emerald-900/30">
            <div class="flex items-start justify-between">
                <div class="space-y-3">
                    <flux:text
                        class="font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-widest text-[10px]">
                        Presensi Harian</flux:text>
                    <div class="flex items-baseline gap-2">
                        <flux:heading size="xl"
                            class="text-3xl font-bold tabular-nums text-emerald-700 dark:text-emerald-300">
                            {{ $daily['present'] }}
                        </flux:heading>
                        <flux:text size="sm" class="text-emerald-600/70">Hadir</flux:text>
                    </div>
                    <flux:text size="xs" class="font-medium text-emerald-600/70 dark:text-emerald-400/60">
                        {{ $daily['late'] }} telat, {{ $daily['permission'] }} izin, {{ $daily['absent'] }} alpa
                    </flux:text>
                </div>
                <div
                    class="p-3 bg-emerald-500/10 dark:bg-emerald-500/20 rounded-2xl text-emerald-600 dark:text-emerald-400 group-hover:rotate-12 transition-transform duration-300">
                    <flux:icon name="check-badge" variant="outline" class="w-6 h-6" />
                </div>
            </div>
            <div
                class="absolute -bottom-2 -right-2 opacity-5 scale-150 rotate-12 text-emerald-600 dark:text-emerald-400">
                <flux:icon name="check-badge" class="w-20 h-20" />
            </div>
        </flux:card>

        <!-- Presensi Kegiatan -->
        <flux:card
            class="relative overflow-hidden group hover:shadow-lg transition-all duration-300 border-none bg-gradient-to-br from-blue-500/10 to-indigo-500/5 dark:from-sky-500/15 dark:to-indigo-500/10 ring-1 ring-blue-100 dark:ring-blue-900/30">
            <div class="flex items-start justify-between">
                <div class="space-y-3">
                    <flux:text class="font-bold text-blue-600 dark:text-sky-400 uppercase tracking-widest text-[10px]">
                        Presensi Kegiatan</flux:text>
                    <div class="flex items-baseline gap-2">
                        <flux:heading size="xl"
                            class="text-3xl font-bold tabular-nums text-blue-700 dark:text-blue-300">
                            {{ $activity['present'] }}
                        </flux:heading>
                        <flux:text size="sm" class="text-blue-600/70">Hadir</flux:text>
                    </div>
                    <flux:text size="xs" class="font-medium text-blue-600/70 dark:text-blue-400/60">
                        {{ $activity['late'] }} telat, {{ $activity['permission'] }} izin
                    </flux:text>
                </div>
                <div
                    class="p-3 bg-blue-500/10 dark:bg-sky-500/20 rounded-2xl text-blue-600 dark:text-sky-400 group-hover:rotate-12 transition-transform duration-300">
                    <flux:icon name="calendar-days" variant="outline" class="w-6 h-6" />
                </div>
            </div>
            <div class="absolute -bottom-2 -right-2 opacity-5 scale-150 rotate-12 text-blue-600 dark:text-sky-400">
                <flux:icon name="calendar-days" class="w-20 h-20" />
            </div>
        </flux:card>

        <!-- Presensi Agenda -->
        <flux:card
            class="relative overflow-hidden group hover:shadow-lg transition-all duration-300 border-none bg-gradient-to-br from-amber-500/10 to-orange-500/5 dark:from-amber-500/15 dark:to-orange-500/10 ring-1 ring-amber-100 dark:ring-amber-900/30">
            <div class="flex items-start justify-between">
                <div class="space-y-3">
                    <flux:text
                        class="font-bold text-amber-600 dark:text-amber-400 uppercase tracking-widest text-[10px]">
                        Presensi Agenda</flux:text>
                    <div class="flex items-baseline gap-2">
                        <flux:heading size="xl"
                            class="text-3xl font-bold tabular-nums text-amber-700 dark:text-amber-300">
                            {{ $event['present'] }}
                        </flux:heading>
                        <flux:text size="sm" class="text-amber-600/70">Hadir</flux:text>
                    </div>
                    <flux:text size="xs" class="font-medium text-amber-600/70 dark:text-amber-400/60">
                        {{ $event['late'] }} telat, {{ $event['izin'] }} izin
                    </flux:text>
                </div>
                <div
                    class="p-3 bg-amber-500/10 dark:bg-amber-500/20 rounded-2xl text-amber-600 dark:text-amber-400 group-hover:rotate-12 transition-transform duration-300">
                    <flux:icon name="flag" variant="outline" class="w-6 h-6" />
                </div>
            </div>
            <div class="absolute -bottom-2 -right-2 opacity-5 scale-150 rotate-12 text-amber-600 dark:text-amber-400">
                <flux:icon name="flag" class="w-20 h-20" />
            </div>
        </flux:card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Quick Actions -->
        <flux:card>
            <flux:heading size="lg" class="mb-4">Aksi Cepat</flux:heading>
            <div class="grid grid-cols-2 gap-4">
                <flux:button href="{{ route('activities.attendance.detail') }}" icon="document-text" wire:navigate class="h-24 flex-col gap-2">
                    Presensi Harian
                </flux:button>
                <flux:button href="{{ route('activities.attendance.index') }}" icon="qr-code" wire:navigate class="h-24 flex-col gap-2">
                    Presensi Kegiatan
                </flux:button>
                <flux:button href="{{ route('events.attendance.index') }}" icon="calendar-days" wire:navigate class="h-24 flex-col gap-2">
                    Presensi Agenda
                </flux:button>
                <flux:button href="{{ route('student-reports.index') }}" icon="academic-cap" wire:navigate class="h-24 flex-col gap-2">
                    Rapor Santri
                </flux:button>
            </div>
        </flux:card>

        <!-- Recent Attendance -->
        <flux:card>
            <flux:heading size="lg" class="mb-4">Aktifitas Presensi Terbaru</flux:heading>
            <div class="space-y-4">
                @forelse($recentActivities as $recent)
                    <div class="flex items-center justify-between p-3 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-zinc-200 dark:bg-zinc-700 flex items-center justify-center font-bold text-zinc-600 dark:text-zinc-300">
                                {{ substr($recent->student->full_name, 0, 1) }}
                            </div>
                            <div>
                                <div class="text-sm font-semibold">{{ $recent->student->full_name }}</div>
                                <div class="text-xs text-zinc-500">{{ $recent->activity->name }}</div>
                            </div>
                        </div>
                        <flux:badge size="sm" :color="$recent->status === 'present' ? 'green' : ($recent->status === 'late' ? 'amber' : 'zinc')">
                            {{ ucfirst($recent->status) }}
                        </flux:badge>
                    </div>
                @empty
                    <div class="text-center py-8 text-zinc-500">
                        Belum ada aktifitas hari ini.
                    </div>
                @endforelse
            </div>
        </flux:card>
    </div>
</div>
