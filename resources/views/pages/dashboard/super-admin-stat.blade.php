<?php

use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Lazy;
use Livewire\Component;

new #[Lazy] #[Layout('layouts.app')] #[Title('Dashboard')] class extends Component
{
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
        // 1. Schools Count
        $totalSchools = School::count();

        // 2. Total Students
        $totalStudents = Student::count();

        // 3. Total Users
        $totalUsers = User::count();

        // 4. Gender Distribution (Pie Chart)
        $genderRaw = Student::select('gender', DB::raw('count(*) as total'))
            ->groupBy('gender')
            ->pluck('total', 'gender')
            ->toArray();

        $genderData = [
            'labels' => ['Laki-laki', 'Perempuan'],
            'series' => [
                (int) ($genderRaw['L'] ?? 0),
                (int) ($genderRaw['P'] ?? 0),
            ],
        ];

        // 5. Students per School (Top 10 Bar Chart)
        $topSchoolsRaw = School::withCount('students')
            ->orderBy('students_count', 'desc')
            ->limit(10)
            ->get();

        $topSchoolsData = [
            'labels' => $topSchoolsRaw->pluck('name')->toArray(),
            'series' => [
                [
                    'name' => 'Jumlah Santri',
                    'data' => $topSchoolsRaw->pluck('students_count')->toArray(),
                ],
            ],
        ];

        // 6. Latest 5 Schools
        $latestSchools = School::orderBy('created_at', 'desc')->limit(5)->get();

        // 7. Latest 5 System Activities (Using Wallet Transactions as proxy)
        $latestActivities = WalletTransaction::with(['student', 'school'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return [
            'totalSchools' => $totalSchools,
            'totalStudents' => $totalStudents,
            'totalUsers' => $totalUsers,
            'genderData' => $genderData,
            'topSchoolsData' => $topSchoolsData,
            'latestSchools' => $latestSchools,
            'latestActivities' => $latestActivities,
        ];
    }
}; ?>

<div class="space-y-10">
    <div class="flex flex-col md:flex-row items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Dashboard Global') }}</flux:heading>
            <flux:subheading size="lg">{{ __('Monitoring statistik sistem dan institusi.') }}</flux:subheading>
        </div>
        <flux:button icon="arrow-path" variant="subtle" wire:click="$refresh" class="shadow-sm">Refresh Data
        </flux:button>
    </div>

    <!-- Metrics Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <flux:card
            class="relative overflow-hidden group hover:shadow-lg transition-all duration-300 border-none bg-gradient-to-br from-blue-500/10 to-indigo-500/5 dark:from-sky-500/15 dark:to-indigo-500/5 ring-1 ring-zinc-200 dark:ring-zinc-800">
            <div class="flex items-start justify-between">
                <div class="space-y-3">
                    <flux:text
                        class="font-semibold text-blue-600 dark:text-sky-400 uppercase tracking-wider text-[10px]">Total
                        Pesantren</flux:text>
                    <div class="flex items-baseline gap-2">
                        <flux:heading size="xl" class="text-3xl font-bold tabular-nums">{{ $totalSchools }}
                        </flux:heading>
                        <flux:text size="xs" class="opacity-70 font-medium">Lembaga</flux:text>
                    </div>
                </div>
                <div
                    class="p-3 bg-blue-500/10 dark:bg-sky-500/20 rounded-2xl text-blue-600 dark:text-sky-400 group-hover:scale-110 transition-transform duration-300">
                    <flux:icon name="building-library" variant="outline" class="w-6 h-6" />
                </div>
            </div>
            <div class="absolute -bottom-2 -right-2 opacity-5 scale-150 rotate-12">
                <flux:icon name="building-library" class="w-24 h-24" />
            </div>
        </flux:card>

        <flux:card
            class="relative overflow-hidden group hover:shadow-lg transition-all duration-300 border-none bg-gradient-to-br from-emerald-500/10 to-teal-500/5 dark:from-emerald-500/15 dark:to-teal-500/5 ring-1 ring-zinc-200 dark:ring-zinc-800">
            <div class="flex items-start justify-between">
                <div class="space-y-3">
                    <flux:text
                        class="font-semibold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider text-[10px]">
                        Total Santri</flux:text>
                    <div class="flex items-baseline gap-2">
                        <flux:heading size="xl" class="text-3xl font-bold tabular-nums">
                            {{ number_format($totalStudents, 0, ',', '.') }}
                        </flux:heading>
                        <flux:text size="xs" class="opacity-70 font-medium">Jiwa</flux:text>
                    </div>
                </div>
                <div
                    class="p-3 bg-emerald-500/10 dark:bg-emerald-500/20 rounded-2xl text-emerald-600 dark:text-emerald-400 group-hover:scale-110 transition-transform duration-300">
                    <flux:icon name="users" variant="outline" class="w-6 h-6" />
                </div>
            </div>
            <div class="absolute -bottom-2 -right-2 opacity-5 scale-150 rotate-12">
                <flux:icon name="users" class="w-24 h-24" />
            </div>
        </flux:card>

        <flux:card
            class="relative overflow-hidden group hover:shadow-lg transition-all duration-300 border-none bg-gradient-to-br from-zinc-500/10 to-zinc-400/5 dark:from-zinc-400/15 dark:to-zinc-800/5 ring-1 ring-zinc-200 dark:ring-zinc-800">
            <div class="flex items-start justify-between">
                <div class="space-y-3">
                    <flux:text
                        class="font-semibold text-zinc-600 dark:text-zinc-400 uppercase tracking-wider text-[10px]">
                        Total Pengguna</flux:text>
                    <div class="flex items-baseline gap-2">
                        <flux:heading size="xl" class="text-3xl font-bold tabular-nums">
                            {{ number_format($totalUsers, 0, ',', '.') }}
                        </flux:heading>
                        <flux:text size="xs" class="opacity-70 font-medium">Akun</flux:text>
                    </div>
                </div>
                <div
                    class="p-3 bg-zinc-500/10 dark:bg-zinc-400/20 rounded-2xl text-zinc-600 dark:text-zinc-400 group-hover:scale-110 transition-transform duration-300">
                    <flux:icon name="user-group" variant="outline" class="w-6 h-6" />
                </div>
            </div>
            <div class="absolute -bottom-2 -right-2 opacity-5 scale-150 rotate-12">
                <flux:icon name="user-group" class="w-24 h-24" />
            </div>
        </flux:card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Gender Chart -->
        <flux:card class="flex flex-col">
            <flux:heading size="lg" class="mb-8">Distribusi Gender Santri</flux:heading>
            <div class="flex-1 flex items-center justify-center p-4">
                <div x-data="{
                    chart: null,
                    init() {
                        this.chart = new ApexCharts(this.$refs.chart, {
                            series: @js($genderData['series']),
                            labels: @js($genderData['labels']),
                            chart: { 
                                type: 'donut', 
                                height: 320, 
                                background: 'transparent',
                                animations: { enabled: true, easing: 'easeinout', speed: 800 }
                            },
                            colors: ['#3b82f6', '#f472b6'],
                            stroke: { width: 0 },
                            plotOptions: {
                                pie: {
                                    donut: {
                                        size: '75%',
                                        labels: {
                                            show: true,
                                            name: { show: true, fontSize: '14px', fontWeight: 600 },
                                            total: { show: true, label: 'Gender', fontSize: '12px' }
                                        }
                                    }
                                }
                            },
                            legend: { position: 'bottom', labels: { colors: 'var(--color-zinc-500)', fontSize: '11px' } },
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

        <!-- Top Schools Bar Chart -->
        <flux:card class="lg:col-span-2">
            <flux:heading size="lg" class="mb-8 font-semibold">Top 10 Pesantren (Jumlah Santri)</flux:heading>
            <div x-data="{
                chart: null,
                init() {
                    const isDark = document.documentElement.classList.contains('dark');
                    this.chart = new ApexCharts(this.$refs.chart, {
                        series: @js($topSchoolsData['series']),
                        chart: { 
                            type: 'bar', 
                            height: 320, 
                            toolbar: { show: false }, 
                            background: 'transparent',
                            fontFamily: 'inherit'
                        },
                        plotOptions: { 
                            bar: { 
                                borderRadius: 8, 
                                horizontal: true, 
                                barHeight: '55%',
                                distributed: true
                            } 
                        },
                        colors: [
                            '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', 
                            '#ec4899', '#06b6d4', '#475569', '#14b8a6', '#f43f5e'
                        ],
                        xaxis: { 
                            categories: @js($topSchoolsData['labels']),
                            labels: { style: { colors: 'var(--color-zinc-400)', fontSize: '11px' } },
                            axisBorder: { show: false },
                            axisTicks: { show: false }
                        },
                        yaxis: {
                            labels: { style: { colors: 'var(--color-zinc-500)', fontSize: '11px', fontWeight: 500 } }
                        },
                        grid: {
                            borderColor: isDark ? '#334155' : '#e2e8f0',
                            strokeDashArray: 4,
                            xaxis: { lines: { show: true } },
                            yaxis: { lines: { show: false } }
                        },
                        legend: { show: false },
                        dataLabels: {
                             enabled: true,
                             style: { fontSize: '11px', fontWeight: 'bold' }
                        },
                        theme: { mode: isDark ? 'dark' : 'light' }
                    });
                    this.chart.render();
                }
            }" wire:ignore class="w-full">
                <div x-ref="chart"></div>
            </div>
        </flux:card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Latest Schools -->
        <flux:card
            class="bg-zinc-50/50 dark:bg-zinc-900/40 border-none ring-1 ring-zinc-200 dark:ring-zinc-800 shadow-sm">
            <div class="flex items-center justify-between mb-6">
                <flux:heading size="lg" class="font-bold">Pesantren Baru</flux:heading>
                <flux:button variant="subtle" size="sm" icon-trailing="chevron-right" :href="route('schools.index')"
                    wire:navigate>Lihat Semua</flux:button>
            </div>
            <div class="space-y-4">
                @forelse ($latestSchools as $school)
                    <div
                        class="group flex items-center justify-between p-4 bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 hover:border-blue-400 dark:hover:border-sky-500/50 hover:shadow-md transition-all duration-300">
                        <div class="flex items-center gap-4">
                            <div class="relative">
                                <flux:avatar :name="$school->name" size="lg"
                                    class="rounded-xl shadow-sm ring-2 ring-white dark:ring-zinc-800" />
                                <div
                                    class="absolute -bottom-1 -right-1 w-4 h-4 bg-emerald-500 border-2 border-white dark:border-zinc-900 rounded-full">
                                </div>
                            </div>
                            <div class="space-y-1">
                                <flux:text
                                    class="font-bold text-zinc-900 dark:text-zinc-100 group-hover:text-blue-600 dark:group-hover:text-sky-400 transition-colors">
                                    {{ $school->name }}
                                </flux:text>
                                <div class="flex items-center gap-2">
                                    <flux:icon name="clock" class="w-3 h-3 text-zinc-400" />
                                    <flux:text size="xs" class="text-zinc-500">{{ $school->created_at->diffForHumans() }}
                                    </flux:text>
                                </div>
                            </div>
                        </div>
                        <flux:badge variant="subtle" color="blue" size="sm"
                            class="rounded-lg group-hover:bg-blue-500 group-hover:text-white transition-all">Baru
                        </flux:badge>
                    </div>
                @empty
                    <div class="p-8 text-center text-zinc-500 italic text-sm">Belum ada pesantren baru.</div>
                @endforelse
            </div>
        </flux:card>

        <!-- Latest Activities -->
        <flux:card
            class="bg-zinc-50/50 dark:bg-zinc-900/40 border-none ring-1 ring-zinc-200 dark:ring-zinc-800 shadow-sm">
            <div class="flex items-center justify-between mb-6">
                <flux:heading size="lg" class="font-bold">Aktivitas Terbaru</flux:heading>
            </div>
            <div class="space-y-4">
                @forelse ($latestActivities as $activity)
                    <div
                        class="group flex items-start gap-4 p-4 bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 hover:border-indigo-400 dark:hover:border-indigo-500/50 hover:shadow-md transition-all duration-300">
                        <div
                            class="p-3 bg-indigo-50 dark:bg-indigo-900/20 rounded-xl text-indigo-600 dark:text-indigo-400 group-hover:scale-105 transition-transform">
                            <flux:icon name="banknotes" variant="outline" class="w-5 h-5" />
                        </div>
                        <div class="flex-1 space-y-1">
                            <div class="flex items-center justify-between">
                                <flux:text class="text-xs font-bold text-blue-600 dark:text-sky-400">
                                    {{ $activity->student->full_name }}
                                </flux:text>
                                <flux:text size="xs" class="text-zinc-400 font-medium">
                                    {{ $activity->created_at->format('H:i') }}
                                </flux:text>
                            </div>
                            <flux:text size="sm" class="text-zinc-700 dark:text-zinc-300 font-medium">
                                {{ $activity->description ?? 'Melakukan transaksi saldo' }}
                            </flux:text>
                            <div class="flex items-center gap-2 pt-1">
                                <flux:icon name="building-library" class="w-3 h-3 text-zinc-400" />
                                <flux:text size="xs" class="text-zinc-500">{{ $activity->school->name }}</flux:text>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-zinc-500 italic text-sm">Belum ada aktivitas terekam.</div>
                @endforelse
            </div>
        </flux:card>
    </div>
</div>