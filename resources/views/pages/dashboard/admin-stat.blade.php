<?php

use App\Models\Student;
use App\Models\Level;
use App\Models\Event;
use App\Models\Bill;
use App\Models\Attendance;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new #[Lazy] #[Layout('layouts.app')] #[Title('Dashboard')] class extends Component {
    public function placeholder()
    {
        return <<<'HTML'
        <div class="space-y-8 w-full animate-pulse">
            <div class="h-10 w-64 bg-zinc-200 dark:bg-zinc-800 rounded-lg"></div>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="h-32 bg-zinc-100 dark:bg-zinc-800 rounded-3xl border border-zinc-100 dark:border-zinc-800"></div>
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

        // 1. Total Students
        $totalStudents = Student::where('school_id', $schoolId)->count();
        $activeStudents = Student::where('school_id', $schoolId)->where('status', 'ACTIVE')->count();

        // 2. Gender Distribution (Pie Chart)
        $genderRaw = Student::where('school_id', $schoolId)
            ->select('gender', DB::raw('count(*) as total'))
            ->groupBy('gender')
            ->pluck('total', 'gender')
            ->toArray();

        $genderData = [
            'labels' => ['Laki-laki', 'Perempuan'],
            'series' => [
                (int) ($genderRaw['L'] ?? 0),
                (int) ($genderRaw['P'] ?? 0)
            ]
        ];

        // 3. Students per Level (Bar Chart)
        $levelStats = Level::where('school_id', $schoolId)
            ->withCount([
                'students' => function ($q) {
                    $q->where('status', 'ACTIVE');
                }
            ])
            ->get();

        $levelData = [
            'labels' => $levelStats->pluck('name')->toArray(),
            'series' => [
                [
                    'name' => 'Jumlah Santri',
                    'data' => $levelStats->pluck('students_count')->toArray()
                ]
            ]
        ];

        // 4. Active Events
        $activeEvents = Event::where('school_id', $schoolId)
            ->whereDate('event_date', '>=', now())
            ->count();

        // 5. Bill Counts (Operational Statistics)
        $billStats = Bill::where('school_id', $schoolId)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // 6. Today's Attendance
        $today = now()->toDateString();
        $attendanceStats = Attendance::where('school_id', $schoolId)
            ->whereDate('date', $today)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'totalStudents' => $totalStudents,
            'activeStudents' => $activeStudents,
            'genderData' => $genderData,
            'levelData' => $levelData,
            'activeEvents' => $activeEvents,
            'unpaidBillsCount' => $billStats['UNPAID'] ?? 0,
            'paidBillsCount' => $billStats['PAID'] ?? 0,
            'attendance' => [
                'present' => $attendanceStats['PRESENT'] ?? 0,
                'late' => $attendanceStats['LATE'] ?? 0,
                'absent' => $attendanceStats['ABSENT'] ?? 0,
            ]
        ];
    }
}; ?>

<div class="space-y-10">
    <div class="flex flex-col md:flex-row items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Dashboard Operasional') }}</flux:heading>
            <flux:subheading size="lg">{{ __('Ringkasan data santri, kehadiran, dan agenda sekolah.') }}
            </flux:subheading>
        </div>
        <flux:button icon="arrow-path" variant="subtle" wire:click="$refresh" class="shadow-sm">Refresh</flux:button>
    </div>

    <!-- Metrics Grid -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <!-- Total Santri -->
        <flux:card
            class="relative overflow-hidden group hover:shadow-lg transition-all duration-300 border-none bg-gradient-to-br from-blue-500/10 to-indigo-500/5 dark:from-sky-500/15 dark:to-indigo-500/10 ring-1 ring-blue-100 dark:ring-blue-900/30">
            <div class="flex items-start justify-between">
                <div class="space-y-3">
                    <flux:text class="font-bold text-blue-600 dark:text-sky-400 uppercase tracking-widest text-[10px]">
                        Total Santri</flux:text>
                    <div class="flex items-baseline gap-2">
                        <flux:heading size="xl"
                            class="text-3xl font-bold tabular-nums text-blue-700 dark:text-blue-300">
                            {{ $totalStudents }}
                        </flux:heading>
                    </div>
                    <flux:text size="xs"
                        class="font-medium text-blue-600/70 dark:text-blue-400/60 transition-opacity group-hover:opacity-100">
                        {{ $activeStudents }} aktif
                    </flux:text>
                </div>
                <div
                    class="p-3 bg-blue-500/10 dark:bg-sky-500/20 rounded-2xl text-blue-600 dark:text-sky-400 group-hover:rotate-12 transition-transform duration-300">
                    <flux:icon name="users" variant="outline" class="w-6 h-6" />
                </div>
            </div>
            <div class="absolute -bottom-2 -right-2 opacity-5 scale-150 rotate-12 text-blue-600 dark:text-sky-400">
                <flux:icon name="users" class="w-20 h-20" />
            </div>
        </flux:card>

        <!-- Tagihan Aktif -->
        <flux:card
            class="relative overflow-hidden group hover:shadow-lg transition-all duration-300 border-none bg-gradient-to-br from-amber-500/10 to-orange-500/5 dark:from-amber-500/15 dark:to-orange-500/10 ring-1 ring-amber-100 dark:ring-amber-900/30">
            <div class="flex items-start justify-between">
                <div class="space-y-3">
                    <flux:text
                        class="font-bold text-amber-600 dark:text-amber-400 uppercase tracking-widest text-[10px]">
                        Tagihan Aktif</flux:text>
                    <div class="flex items-baseline gap-2">
                        <flux:heading size="xl"
                            class="text-3xl font-bold tabular-nums text-amber-700 dark:text-amber-300">
                            {{ $unpaidBillsCount }}
                        </flux:heading>
                    </div>
                    <flux:text size="xs" class="font-medium text-amber-600/70 dark:text-amber-400/60">
                        {{ $paidBillsCount }} lunas
                    </flux:text>
                </div>
                <div
                    class="p-3 bg-amber-500/10 dark:bg-amber-500/20 rounded-2xl text-amber-600 dark:text-amber-400 group-hover:rotate-12 transition-transform duration-300">
                    <flux:icon name="credit-card" variant="outline" class="w-6 h-6" />
                </div>
            </div>
            <div class="absolute -bottom-2 -right-2 opacity-5 scale-150 rotate-12 text-amber-600 dark:text-amber-400">
                <flux:icon name="credit-card" class="w-20 h-20" />
            </div>
        </flux:card>

        <!-- Kehadiran -->
        <flux:card
            class="relative overflow-hidden group hover:shadow-lg transition-all duration-300 border-none bg-gradient-to-br from-emerald-500/10 to-teal-500/5 dark:from-emerald-500/15 dark:to-teal-500/10 ring-1 ring-emerald-100 dark:ring-emerald-900/30">
            <div class="flex items-start justify-between">
                <div class="space-y-3">
                    <flux:text
                        class="font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-widest text-[10px]">
                        Hadir Hari Ini</flux:text>
                    <div class="flex items-baseline gap-2">
                        <flux:heading size="xl"
                            class="text-3xl font-bold tabular-nums text-emerald-700 dark:text-emerald-300">
                            {{ $attendance['present'] }}
                        </flux:heading>
                    </div>
                    <flux:text size="xs" class="font-medium text-emerald-600/70 dark:text-emerald-400/60">
                        {{ $attendance['late'] }} telat, {{ $attendance['absent'] }} absen
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

        <!-- Agenda -->
        <flux:card
            class="relative overflow-hidden group hover:shadow-lg transition-all duration-300 border-none bg-gradient-to-br from-indigo-500/10 to-blue-500/5 dark:from-indigo-500/15 dark:to-blue-500/10 ring-1 ring-indigo-100 dark:ring-indigo-900/30">
            <div class="flex items-start justify-between">
                <div class="space-y-3">
                    <flux:text
                        class="font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-widest text-[10px]">
                        Agenda Aktif</flux:text>
                    <div class="flex items-baseline gap-2">
                        <flux:heading size="xl"
                            class="text-3xl font-bold tabular-nums text-indigo-700 dark:text-indigo-300">
                            {{ $activeEvents }}
                        </flux:heading>
                    </div>
                    <flux:text size="xs" class="font-medium text-indigo-600/70 dark:text-indigo-400/60">kegiatan
                        terdaftar</flux:text>
                </div>
                <div
                    class="p-3 bg-indigo-500/10 dark:bg-indigo-500/20 rounded-2xl text-indigo-600 dark:text-indigo-400 group-hover:rotate-12 transition-transform duration-300">
                    <flux:icon name="calendar-days" variant="outline" class="w-6 h-6" />
                </div>
            </div>
            <div class="absolute -bottom-2 -right-2 opacity-5 scale-150 rotate-12 text-indigo-600 dark:text-indigo-400">
                <flux:icon name="calendar-days" class="w-20 h-20" />
            </div>
        </flux:card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Quick Actions -->
        <flux:card>
            <flux:heading size="lg" class="mb-4">Aksi Cepat</flux:heading>
            <div class="grid grid-cols-2 gap-4">
                <flux:button href="{{ route('dashboard.admin.manual-topup') }}" icon="plus-circle" wire:navigate
                    class="h-24 flex-col gap-2">
                    Top Up Manual
                </flux:button>
                <flux:button href="{{ route('dashboard.admin.student-cash-withdrawal') }}" icon="banknotes" wire:navigate
                    class="h-24 flex-col gap-2">
                    Tarik Tunai
                </flux:button>
                <flux:button href="{{ route('students.index') }}" icon="user-plus" wire:navigate
                    class="h-24 flex-col gap-2">
                    Santri Baru
                </flux:button>
                <flux:button href="{{ route('bills.index') }}" icon="document-text" wire:navigate
                    class="h-24 flex-col gap-2">
                    Data Tagihan
                </flux:button>
                <flux:button href="{{ route('dashboard.admin.manual-bill-payment') }}" icon="banknotes" wire:navigate
                    class="h-24 flex-col gap-2">
                    Bayar Tagihan
                </flux:button>
            </div>
        </flux:card>

        <!-- Gender Chart -->
        <flux:card class="flex flex-col">
            <flux:heading size="lg" class="mb-8">Rasio Gender</flux:heading>
            <div class="flex-1 flex items-center justify-center">
                <div x-data="{
                    chart: null,
                    init() {
                        this.chart = new ApexCharts(this.$refs.chart, {
                            series: @js($genderData['series']),
                            labels: @js($genderData['labels']),
                            chart: { 
                                type: 'donut', 
                                height: 300, 
                                background: 'transparent' 
                            },
                            colors: ['#3b82f6', '#f472b6'],
                            stroke: { width: 0 },
                            legend: { position: 'bottom', labels: { colors: 'var(--color-zinc-500)' } },
                            dataLabels: { enabled: false },
                            theme: { mode: document.documentElement.classList.contains('dark') ? 'dark' : 'light' }
                        });
                        this.chart.render();
                    }
                }" wire:ignore class="w-full">
                    <div x-ref="chart"></div>
                </div>
            </div>
        </flux:card>

        <!-- Students per Level Chart -->
        <flux:card class="lg:col-span-2">
            <flux:heading size="lg" class="mb-8 font-semibold">Data Santri per Kelas</flux:heading>
            <div x-data="{
                chart: null,
                init() {
                    const isDark = document.documentElement.classList.contains('dark');
                    this.chart = new ApexCharts(this.$refs.chart, {
                        series: @js($levelData['series']),
                        chart: { 
                            type: 'bar', 
                            height: 320, 
                            toolbar: { show: false }, 
                            background: 'transparent' 
                        },
                        plotOptions: { 
                            bar: { 
                                columnWidth: '40%', 
                                borderRadius: 6,
                                distributed: true
                            } 
                        },
                        colors: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'],
                        xaxis: { 
                            categories: @js($levelData['labels']),
                            labels: { style: { colors: 'var(--color-zinc-400)', fontSize: '11px' } },
                            axisBorder: { show: false },
                            axisTicks: { show: false }
                        },
                        yaxis: {
                            labels: { style: { colors: 'var(--color-zinc-500)', fontSize: '11px' } }
                        },
                        grid: {
                            borderColor: isDark ? '#334155' : '#e2e8f0',
                            strokeDashArray: 4
                        },
                        legend: { show: false },
                        theme: { mode: isDark ? 'dark' : 'light' }
                    });
                    this.chart.render();
                }
            }" wire:ignore class="w-full">
                <div x-ref="chart"></div>
            </div>
        </flux:card>
    </div>
</div>