<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new #[Lazy] #[Layout('layouts.app')] #[Title('Dashboard')] class extends Component {
    public function placeholder()
    {
        return <<<'HTML'
    <div class="space-y-6">
        <div class="flex items-center justify-between mb-6">
        <div class="space-y-2">
            <div class="h-8 w-64 bg-zinc-100 dark:bg-zinc-800 rounded animate-pulse"></div>
            <div class="h-4 w-96 bg-zinc-50 dark:bg-zinc-900 rounded animate-pulse"></div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        @foreach(range(1, 4) as $i)
            <div class="p-6 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 space-y-3">
                <div class="h-4 w-24 bg-zinc-100 dark:bg-zinc-800 rounded animate-pulse"></div>
                <div class="h-8 w-32 bg-zinc-200 dark:bg-zinc-700 rounded animate-pulse"></div>
                <div class="h-3 w-40 bg-zinc-50 dark:bg-zinc-900 rounded animate-pulse"></div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 space-y-6">
            <div
                class="p-6 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 h-96 animate-pulse">
            </div>
        </div>
        <div class="space-y-6">
            <div
                class="p-6 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 h-96 animate-pulse">
            </div>
        </div>
    </div>
    </div>
    HTML;
    }
    public $filter = 'today';
    public $dateFrom = '';
    public $dateTo = '';

    public function with(): array
    {
        $user = auth()->user();
        $schoolId = $user->school_id;
        $isCashier = $user->hasRole('kasir');

        $start = now()->startOfDay();
        $end = now()->endOfDay();

        if ($this->filter === 'this_week') {
            $start = now()->startOfWeek();
        } elseif ($this->filter === 'this_month') {
            $start = now()->startOfMonth();
        } elseif ($this->filter === 'custom') {
            $start = $this->dateFrom ? Carbon::parse($this->dateFrom)->startOfDay() : now()->startOfDay();
            $end = $this->dateTo ? Carbon::parse($this->dateTo)->endOfDay() : now()->endOfDay();
        }

        $baseQuery = WalletTransaction::where('school_id', $schoolId)
            ->where('category', 'PURCHASE')
            ->where('status', 'PAID')
            ->when($isCashier, fn($q) => $q->where('operator_id', $user->id));

        // Stats based on filter
        $salesPeriod = (clone $baseQuery)
            ->whereBetween('created_at', [$start, $end])
            ->sum('amount');

        $countPeriod = (clone $baseQuery)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $activeStudentsCount = (clone $baseQuery)
            ->whereBetween('created_at', [$start, $end])
            ->distinct('student_id')
            ->count('student_id');

        // Stats This Month
        $salesMonth = (clone $baseQuery)
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->sum('amount');

        // Recent 5 Transactions
        $recentPurchases = (clone $baseQuery)
            ->with('student')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Trend Sales (Last 30 Days)
        $days = 30;
        $trendData = (clone $baseQuery)
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy(DB::raw('DATE(created_at)'))
            ->selectRaw('DATE(created_at) as date, sum(amount) as value')
            ->get()
            ->pluck('value', 'date');

        $dates = [];
        $values = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $dates[] = Carbon::parse($date)->format('d M');
            $values[] = $trendData[$date] ?? 0;
        }

        $chartData = [
            'categories' => $dates,
            'series' => [['name' => 'Penjualan', 'data' => $values]],
        ];

        return [
            'salesPeriod' => $salesPeriod,
            'countPeriod' => $countPeriod,
            'activeStudentsCount' => $activeStudentsCount,
            'salesMonth' => $salesMonth,
            'recentPurchases' => $recentPurchases,
            'chartData' => $chartData,
            'filterLabel' => match ($this->filter) {
                'today' => 'Hari Ini',
                'this_week' => 'Minggu Ini',
                'this_month' => 'Bulan Ini',
                'custom' => 'Rentang Kustom',
                default => 'Hari Ini'
            },
        ];
    }
}; ?>

<div class="w-full">
    <div class="relative mb-10 w-full">
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-6">
            <div class="space-y-1">
                <flux:heading size="xl" level="1">{{ __('Dashboard Koperasi') }}</flux:heading>
                <flux:subheading size="lg">
                    {{ __('Ringkasan operasional dan penjualan koperasi') }}
                </flux:subheading>
            </div>

            <div class="flex flex-col md:flex-row gap-4 items-end">
                @if ($filter === 'custom')
                    <div class="flex gap-2">
                        <flux:input wire:model.live="dateFrom" type="date" label="Dari" size="sm" />
                        <flux:input wire:model.live="dateTo" type="date" label="Sampai" size="sm" />
                    </div>
                @endif
                <flux:select wire:model.live="filter" variant="listbox" size="sm" class="w-48" label="Pilih Periode">
                    <flux:select.option value="today">Hari Ini</flux:select.option>
                    <flux:select.option value="this_week">Minggu Ini</flux:select.option>
                    <flux:select.option value="this_month">Bulan Ini</flux:select.option>
                    <flux:select.option value="custom">Rentang Kustom</flux:select.option>
                </flux:select>
            </div>
        </div>
        <flux:separator variant="subtle" />
    </div>

    <!-- Metrics Grid -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-16">
        <!-- Penjualan Periode -->
        <flux:card
            class="relative overflow-hidden group hover:shadow-lg transition-all duration-300 border-none bg-gradient-to-br from-blue-500/10 to-indigo-500/5 dark:from-sky-500/15 dark:to-indigo-500/10 ring-1 ring-blue-100 dark:ring-blue-900/30">
            <div class="flex items-start justify-between">
                <div class="space-y-3">
                    <flux:text class="font-bold text-blue-600 dark:text-sky-400 uppercase tracking-widest text-[10px]">
                        Penjualan ({{ $filterLabel }})</flux:text>
                    <div class="flex items-baseline gap-2">
                        <flux:heading size="xl"
                            class="text-2xl font-bold tabular-nums text-blue-700 dark:text-blue-300">Rp
                            {{ number_format($salesPeriod, 0, ',', '.') }}
                        </flux:heading>
                    </div>
                </div>
                <div
                    class="p-3 bg-blue-500/10 dark:bg-sky-500/20 rounded-2xl text-blue-600 dark:text-sky-400 group-hover:rotate-12 transition-transform duration-300">
                    <flux:icon name="shopping-bag" variant="outline" class="w-6 h-6" />
                </div>
            </div>
            <div class="absolute -bottom-2 -right-2 opacity-5 scale-150 rotate-12 text-blue-600 dark:text-sky-400">
                <flux:icon name="shopping-bag" class="w-20 h-20" />
            </div>
        </flux:card>

        <!-- Santri Bertransaksi -->
        <flux:card
            class="relative overflow-hidden group hover:shadow-lg transition-all duration-300 border-none bg-gradient-to-br from-amber-500/10 to-orange-500/5 dark:from-amber-500/15 dark:to-orange-500/10 ring-1 ring-amber-100 dark:ring-amber-900/30">
            <div class="flex items-start justify-between">
                <div class="space-y-3">
                    <flux:text
                        class="font-bold text-amber-600 dark:text-amber-400 uppercase tracking-widest text-[10px]">
                        Santri Bertransaksi</flux:text>
                    <div class="flex items-baseline gap-2">
                        <flux:heading size="xl"
                            class="text-2xl font-bold tabular-nums text-amber-700 dark:text-amber-300">
                            {{ $activeStudentsCount }}
                        </flux:heading>
                    </div>
                    <flux:text size="xs"
                        class="font-medium text-amber-600/70 dark:text-amber-400/60 uppercase tracking-tight">
                        Eksklusif</flux:text>
                </div>
                <div
                    class="p-3 bg-amber-500/10 dark:bg-amber-500/20 rounded-2xl text-amber-600 dark:text-amber-400 group-hover:rotate-12 transition-transform duration-300">
                    <flux:icon name="user-group" variant="outline" class="w-6 h-6" />
                </div>
            </div>
            <div class="absolute -bottom-2 -right-2 opacity-5 scale-150 rotate-12 text-amber-600 dark:text-amber-400">
                <flux:icon name="user-group" class="w-20 h-20" />
            </div>
        </flux:card>

        <!-- Transaksi Periode -->
        <flux:card
            class="relative overflow-hidden group hover:shadow-lg transition-all duration-300 border-none bg-gradient-to-br from-emerald-500/10 to-teal-500/5 dark:from-emerald-500/15 dark:to-teal-500/10 ring-1 ring-emerald-100 dark:ring-emerald-900/30">
            <div class="flex items-start justify-between">
                <div class="space-y-3">
                    <flux:text
                        class="font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-widest text-[10px]">
                        Transaksi ({{ $filterLabel }})</flux:text>
                    <div class="flex items-baseline gap-2">
                        <flux:heading size="xl"
                            class="text-2xl font-bold tabular-nums text-emerald-700 dark:text-emerald-300">
                            {{ $countPeriod }}
                        </flux:heading>
                    </div>
                </div>
                <div
                    class="p-3 bg-emerald-500/10 dark:bg-emerald-500/20 rounded-2xl text-emerald-600 dark:text-emerald-400 group-hover:rotate-12 transition-transform duration-300">
                    <flux:icon name="credit-card" variant="outline" class="w-6 h-6" />
                </div>
            </div>
            <div
                class="absolute -bottom-2 -right-2 opacity-5 scale-150 rotate-12 text-emerald-600 dark:text-emerald-400">
                <flux:icon name="credit-card" class="w-20 h-20" />
            </div>
        </flux:card>

        <!-- Penjualan Bulan Ini -->
        <flux:card
            class="relative overflow-hidden group hover:shadow-lg transition-all duration-300 border-none bg-gradient-to-br from-purple-500/10 to-fuchsia-500/5 dark:from-purple-500/15 dark:to-fuchsia-500/10 ring-1 ring-purple-100 dark:ring-purple-900/30">
            <div class="flex items-start justify-between">
                <div class="space-y-3">
                    <flux:text
                        class="font-bold text-purple-700 dark:text-purple-400 uppercase tracking-widest text-[10px]">
                        Penjualan Bulan Ini</flux:text>
                    <div class="flex items-baseline gap-2">
                        <flux:heading size="xl"
                            class="text-2xl font-bold tabular-nums text-purple-700 dark:text-purple-400">Rp
                            {{ number_format($salesMonth, 0, ',', '.') }}
                        </flux:heading>
                    </div>
                </div>
                <div
                    class="p-3 bg-purple-500/10 dark:bg-purple-900/20 rounded-2xl text-purple-600 dark:text-purple-400 group-hover:rotate-12 transition-transform duration-300">
                    <flux:icon name="banknotes" variant="outline" class="w-6 h-6" />
                </div>
            </div>
            <div class="absolute -bottom-2 -right-2 opacity-5 scale-150 rotate-12 text-purple-600 dark:text-purple-400">
                <flux:icon name="banknotes" class="w-20 h-20" />
            </div>
        </flux:card>
    </div>

    <!-- Sales Trend Chart -->
    <flux:card class="mb-12">
        <flux:heading size="lg" class="mb-6 font-bold">Tren Penjualan (30 Hari Terakhir)</flux:heading>
        <div x-data="{
                init() {
                     const isDark = document.documentElement.classList.contains('dark');
                     const options = {
                        series: @js($chartData['series']),
                        chart: { type: 'area', height: 350, toolbar: { show: false }, background: 'transparent' },
                        dataLabels: { enabled: false },
                        stroke: { curve: 'smooth', width: 2 },
                        xaxis: {
                            categories: @js($chartData['categories']),
                            axisBorder: { show: false },
                            axisTicks: { show: false },
                            labels: { style: { colors: 'var(--color-zinc-400)', fontSize: '11px' } }
                        },
                         yaxis: {
                            labels: {
                                style: { colors: 'var(--color-zinc-500)', fontSize: '11px' },
                                formatter: (value) => {
                                    return new Intl.NumberFormat('id-ID', { notation: 'compact' }).format(value)
                                }
                            }
                        },
                        colors: ['#3b82f6'],
                        fill: {
                            type: 'gradient',
                            gradient: { shadeIntensity: 1, opacityFrom: 0.45, opacityTo: 0.05, stops: [50, 100] }
                        },
                         grid: { 
                            borderColor: isDark ? '#334155' : '#e2e8f0', 
                            strokeDashArray: 4 
                        },
                         theme: {
                            mode: isDark ? 'dark' : 'light'
                        },
                        tooltip: {
                            theme: isDark ? 'dark' : 'light'
                        }
                    };
                    const chart = new ApexCharts(this.$el, options);
                    chart.render();
                }
            }" class="w-full h-[350px]"></div>
    </flux:card>

    <!-- Recent Transactions -->
    <div class="space-y-6">
        <div class="flex justify-between items-center px-1">
            <flux:heading size="lg">Pembelian Terakhir</flux:heading>
            <flux:button variant="ghost" icon-trailing="chevron-right" :href="route('koperasi.history')" wire:navigate>
                Lihat Semua
            </flux:button>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Waktu</flux:table.column>
                <flux:table.column>Santri</flux:table.column>
                <flux:table.column>Nominal</flux:table.column>
                <flux:table.column align="end">Invoice</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($recentPurchases as $purchase)
                    <flux:table.row :key="$purchase->id">
                        <flux:table.cell class="text-zinc-500 whitespace-nowrap">
                            {{ $purchase->created_at->diffForHumans() }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-col">
                                @if($purchase->student)
                                    <span class="font-medium">{{ $purchase->student->full_name }}</span>
                                    <span class="text-xs text-zinc-500">{{ $purchase->student->nis }}</span>
                                @else
                                    <span class="font-medium text-zinc-400 italic">PELANGGAN UMUM</span>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell class="font-bold text-red-600 dark:text-red-400">
                            -Rp {{ number_format($purchase->amount, 0, ',', '.') }}
                        </flux:table.cell>
                        <flux:table.cell align="end" class="font-mono text-xs">
                            {{ $purchase->invoice_code }}
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center py-8 text-zinc-500">
                            Belum ada transaksi terbaru.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>