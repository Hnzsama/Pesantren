<?php

use App\Models\Bill;
use App\Models\WalletTransaction;
use App\Models\School;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;

new #[Lazy] class extends Component {
    public function placeholder()
    {
        return <<<'HTML'
        <div class="space-y-8 w-full animate-pulse">
            <div class="h-10 w-64 bg-zinc-200 dark:bg-zinc-800 rounded-lg"></div>
            <div class="grid grid-cols-1 md:grid-cols-5 gap-6">
                <div class="h-32 bg-zinc-100 dark:bg-zinc-800 rounded-3xl border border-zinc-100 dark:border-zinc-800"></div>
                <div class="h-32 bg-zinc-100 dark:bg-zinc-800 rounded-3xl border border-zinc-100 dark:border-zinc-800"></div>
                <div class="h-32 bg-zinc-100 dark:bg-zinc-800 rounded-3xl border border-zinc-100 dark:border-zinc-800"></div>
                <div class="h-32 bg-zinc-100 dark:bg-zinc-800 rounded-3xl border border-zinc-100 dark:border-zinc-800"></div>
                <div class="h-32 bg-zinc-100 dark:bg-zinc-800 rounded-3xl border border-zinc-100 dark:border-zinc-800"></div>
            </div>
            <div class="h-96 bg-zinc-50 dark:bg-zinc-900 rounded-3xl border border-zinc-100 dark:border-zinc-800"></div>
        </div>
        HTML;
    }

    // State
    public $chartFilter = '30_days';
    public $schoolId = null;
    public $schools = [];

    // Computed Properties for Summary Cards
    // Using computed lets Livewire cache these for the request duration and recalculate only on updates

    #[Computed]
    public function totalBillPaid()
    {
        return Bill::where('status', 'PAID')
            ->when($this->schoolId, fn($q) => $q->where('school_id', $this->schoolId))
            ->sum('amount');
    }

    #[Computed]
    public function totalTopUp()
    {
        return WalletTransaction::where('type', 'CREDIT')
            ->where('category', 'TOPUP')
            ->where('status', 'PAID')
            ->when($this->schoolId, fn($q) => $q->where('school_id', $this->schoolId))
            ->sum('amount');
    }

    #[Computed]
    public function totalPlatformFeeCash()
    {
        $billFees = Bill::where('status', 'PAID')
            ->where('payment_method', 'MANUAL')
            ->where('admin_fee', '>', 0)
            ->when($this->schoolId, fn($q) => $q->where('school_id', $this->schoolId))
            ->sum('admin_fee');

        $walletFees = WalletTransaction::where('status', 'PAID')
            ->where('payment_method', 'cash')
            ->where('admin_fee', '>', 0)
            ->when($this->schoolId, fn($q) => $q->where('school_id', $this->schoolId))
            ->sum('admin_fee');

        return $billFees + $walletFees;
    }

    #[Computed]
    public function totalPlatformFeeNonCash()
    {
        $billFees = Bill::where('status', 'PAID')
            ->where('payment_method', '!=', 'MANUAL')
            ->where('admin_fee', '>', 0)
            ->when($this->schoolId, fn($q) => $q->where('school_id', $this->schoolId))
            ->sum('admin_fee');

        $walletFees = WalletTransaction::where('status', 'PAID')
            ->where('payment_method', '!=', 'cash')
            ->where('admin_fee', '>', 0)
            ->when($this->schoolId, fn($q) => $q->where('school_id', $this->schoolId))
            ->sum('admin_fee');

        return $billFees + $walletFees;
    }

    #[Computed]
    public function totalPlatformFee()
    {
        return $this->totalPlatformFeeCash + $this->totalPlatformFeeNonCash;
    }

    #[Computed]
    public function totalManagedBalance()
    {
        return $this->totalBillPaid + $this->totalTopUp;
    }

    #[Computed]
    public function totalGatewayBalance()
    {
        $billGateway = Bill::where('status', 'PAID')
            ->where(fn($q) => $q->where('payment_method', 'gateway')->orWhere('payment_method', 'like', 'payment_gateway%'))
            ->when($this->schoolId, fn($q) => $q->where('school_id', $this->schoolId))
            ->sum('amount');

        $walletGateway = WalletTransaction::where('status', 'PAID')
            ->where('category', 'TOPUP')
            ->where('payment_method', 'gateway')
            ->when($this->schoolId, fn($q) => $q->where('school_id', $this->schoolId))
            ->sum('amount');

        return $billGateway + $walletGateway;
    }

    #[Computed]
    public function totalPendingDeposits()
    {
        return \App\Models\AdminDeposit::where('status', 'pending')
            ->when($this->schoolId, fn($q) => $q->where('school_id', $this->schoolId))
            ->sum('amount');
    }

    #[Computed]
    public function pendingDepositsCount()
    {
        return \App\Models\AdminDeposit::where('status', 'pending')
            ->when($this->schoolId, fn($q) => $q->where('school_id', $this->schoolId))
            ->count();
    }

    #[Computed]
    public function totalCashAtAgents()
    {
        return app(\App\Services\FinancialService::class)->getTotalCashAtAgents($this->schoolId);
    }

    #[Computed]
    public function sparklines()
    {
        // Efficient query for last 7 days sparklines
        $days = 7;

        // Helper to get daily sums
        $getDailySum = function ($query, $dateCol = 'created_at') use ($days) {
            return $query->where($dateCol, '>=', now()->subDays($days))
                ->groupBy(DB::raw('DATE(' . $dateCol . ')'))
                ->orderBy(DB::raw('DATE(' . $dateCol . ')'))
                ->pluck(DB::raw('SUM(amount)'), DB::raw('DATE(' . $dateCol . ')'))
                ->toArray();
        };

        $bills = $getDailySum(
            Bill::where('status', 'PAID')
                ->when($this->schoolId, fn($q) => $q->where('school_id', $this->schoolId))
        );

        $topups = $getDailySum(
            WalletTransaction::where('type', 'CREDIT')
                ->where('category', 'TOPUP')
                ->where('status', 'PAID')
                ->when($this->schoolId, fn($q) => $q->where('school_id', $this->schoolId))
        );

        // Fill missing dates
        $chartData = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $chartData['bills'][] = $bills[$date] ?? 0;
            $chartData['topups'][] = $topups[$date] ?? 0;
            // Fee sparkline omitted for brevity or calculated similarly
        }

        return $chartData;
    }

    public $chartData = [];

    public function mount()
    {
        $this->schools = School::select('id', 'name')->orderBy('name')->get();
        $this->loadChartData();
    }

    public function updatedChartFilter()
    {
        $this->loadChartData();
    }

    public function updatedSchoolId()
    {
        $this->loadChartData();
    }

    public function loadChartData()
    {
        // 30 Days Trend
        $days = 30;
        $dates = [];
        $billSeries = [];
        $topupSeries = [];
        $feeSeries = [];

        // Aggregate Bills
        $rawBills = Bill::selectRaw('DATE(created_at) as date, SUM(amount) as total, SUM(admin_fee) as fee')
            ->where('status', 'PAID')
            ->when($this->schoolId, fn($q) => $q->where('school_id', $this->schoolId))
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->pluck('total', 'date');

        $rawBillFees = Bill::selectRaw('DATE(created_at) as date, SUM(admin_fee) as fee')
            ->where('status', 'PAID')
            ->when($this->schoolId, fn($q) => $q->where('school_id', $this->schoolId))
            ->where('admin_fee', '>', 0)
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->pluck('fee', 'date');

        // Aggregate Topups
        $rawTopups = WalletTransaction::selectRaw('DATE(created_at) as date, SUM(amount) as total, SUM(admin_fee) as fee')
            ->where('type', 'CREDIT')
            ->where('category', 'TOPUP')
            ->where('status', 'PAID')
            ->when($this->schoolId, fn($q) => $q->where('school_id', $this->schoolId))
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->pluck('total', 'date');

        $rawWalletFees = WalletTransaction::selectRaw('DATE(created_at) as date, SUM(admin_fee) as fee')
            ->where('status', 'PAID')
            ->when($this->schoolId, fn($q) => $q->where('school_id', $this->schoolId))
            ->where('admin_fee', '>', 0)
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->pluck('fee', 'date');

        // Merge logic
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = now()->subDays($i)->format('Y-m-d');
            $dates[] = Carbon::parse($d)->format('d M');
            $billSeries[] = $rawBills[$d] ?? 0;
            $topupSeries[] = $rawTopups[$d] ?? 0;
            $feeSeries[] = ($rawBillFees[$d] ?? 0) + ($rawWalletFees[$d] ?? 0);
        }

        $this->chartData = [
            'categories' => $dates,
            'series' => [
                ['name' => 'Bill Payments', 'data' => $billSeries],
                ['name' => 'Top Up', 'data' => $topupSeries],
                ['name' => 'Platform Fee', 'data' => $feeSeries],
            ],
        ];
    }
}; ?>

<div class="space-y-8">
    <div class="flex flex-col md:flex-row items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Dashboard Keuangan</flux:heading>
            <flux:subheading size="lg" class="mb-6">Global Overview Real-time</flux:subheading>

            <div class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                Viewing: <span class="font-medium text-zinc-800 dark:text-zinc-200">
                    {{ $schoolId ? $schools->firstWhere('id', $schoolId)->name : 'Semua Pesantren' }}
                </span>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <flux:select wire:model.live="schoolId" variant="listbox" searchable placeholder="Filter Pesantren"
                class="min-w-[200px]">
                <flux:select.option value="">Semua Pesantren</flux:select.option>
                @foreach ($schools as $school)
                    <flux:select.option value="{{ $school->id }}">{{ $school->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:button icon="arrow-path" variant="subtle" wire:click="$refresh">Refresh</flux:button>
        </div>
    </div>

    <div class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Total Cash at Agents -->
            <flux:card
                class="relative overflow-hidden group hover:shadow-lg transition-all duration-300 border-none bg-gradient-to-br from-amber-500/10 to-orange-500/5 dark:from-amber-500/15 dark:to-orange-500/10 ring-1 ring-amber-100 dark:ring-amber-900/30 flex flex-col h-full">
                <div class="flex items-start justify-between flex-1">
                    <div class="space-y-3">
                        <flux:text class="font-bold text-amber-600 dark:text-amber-400 uppercase tracking-widest text-[10px]">
                            Tunai di Sekolah</flux:text>
                        <div class="flex items-baseline gap-2">
                            <flux:heading size="xl" class="text-2xl font-bold tabular-nums">Rp
                                {{ number_format($this->totalCashAtAgents, 0, ',', '.') }}
                            </flux:heading>
                        </div>
                    </div>
                    <div
                        class="p-3 bg-amber-500/10 dark:bg-amber-500/20 rounded-2xl text-amber-600 dark:text-amber-400 group-hover:rotate-12 transition-transform duration-300">
                        <flux:icon name="banknotes" variant="outline" class="w-6 h-6" />
                    </div>
                </div>
                <div class="absolute -bottom-2 -right-2 opacity-5 scale-150 rotate-12 text-amber-600 dark:text-amber-400">
                    <flux:icon name="banknotes" class="w-20 h-20" />
                </div>
            </flux:card>

            <!-- Gateway Balance -->
            <flux:card
                class="relative overflow-hidden group hover:shadow-lg transition-all duration-300 border-none bg-gradient-to-br from-blue-500/10 to-indigo-500/5 dark:from-sky-500/15 dark:to-indigo-500/10 ring-1 ring-blue-100 dark:ring-blue-900/30 flex flex-col h-full">
                <div class="flex items-start justify-between flex-1">
                    <div class="space-y-3">
                        <flux:text class="font-bold text-blue-600 dark:text-sky-400 uppercase tracking-widest text-[10px]">
                            Saldo Gateway</flux:text>
                        <div class="flex items-baseline gap-2">
                            <flux:heading size="xl" class="text-2xl font-bold tabular-nums">Rp
                                {{ number_format($this->totalGatewayBalance, 0, ',', '.') }}
                            </flux:heading>
                        </div>
                    </div>
                    <div
                        class="p-3 bg-blue-500/10 dark:bg-sky-500/20 rounded-2xl text-blue-600 dark:text-sky-400 group-hover:rotate-12 transition-transform duration-300">
                        <flux:icon name="globe-alt" variant="outline" class="w-6 h-6" />
                    </div>
                </div>
                <div class="absolute -bottom-2 -right-2 opacity-5 scale-150 rotate-12 text-blue-600 dark:text-sky-400">
                    <flux:icon name="globe-alt" class="w-20 h-20" />
                </div>
            </flux:card>

            <!-- Pending Deposits -->
            <flux:card
                class="relative overflow-hidden group hover:shadow-lg transition-all duration-300 border-none bg-gradient-to-br from-emerald-500/10 to-teal-500/5 dark:from-emerald-500/15 dark:to-teal-500/10 ring-1 ring-emerald-100 dark:ring-emerald-900/30 flex flex-col h-full">
                <div class="flex items-start justify-between flex-1">
                    <div class="space-y-3">
                        <flux:text
                            class="font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-widest text-[10px]">
                            Setoran Tertunda</flux:text>
                        <div class="flex items-baseline gap-2">
                            <flux:heading size="xl" class="text-2xl font-bold tabular-nums">Rp
                                {{ number_format($this->totalPendingDeposits, 0, ',', '.') }}
                            </flux:heading>
                        </div>
                    </div>
                    <div
                        class="p-3 bg-emerald-500/10 dark:bg-emerald-500/20 rounded-2xl text-emerald-600 dark:text-emerald-400 group-hover:rotate-12 transition-transform duration-300">
                        <flux:icon name="arrow-up-tray" variant="outline" class="w-6 h-6" />
                    </div>
                </div>
                <div
                    class="pt-4 border-t border-emerald-100 dark:border-emerald-900/30 mt-4 relative z-10 flex items-center justify-between">
                    <flux:text class="text-[10px] text-zinc-500 uppercase font-bold">Total Request</flux:text>
                    <flux:badge color="emerald" size="sm" class="font-bold">
                        {{ $this->pendingDepositsCount }}</flux:badge>
                </div>
                <div
                    class="absolute -bottom-2 -right-2 opacity-5 scale-150 rotate-12 text-emerald-600 dark:text-emerald-400">
                    <flux:icon name="arrow-up-tray" class="w-20 h-20" />
                </div>
            </flux:card>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Total Managed Balance (Volume) -->
            <flux:card
                class="relative overflow-hidden group hover:shadow-lg transition-all duration-300 border-none bg-gradient-to-br from-violet-500/10 to-purple-500/5 dark:from-violet-500/15 dark:to-purple-500/10 ring-1 ring-violet-100 dark:ring-violet-900/30 flex flex-col h-full">
                <div class="flex items-start justify-between flex-1">
                    <div class="space-y-3">
                        <flux:text
                            class="font-bold text-violet-600 dark:text-violet-400 uppercase tracking-widest text-[10px]">
                            Volume Transaksi</flux:text>
                        <div class="flex items-baseline gap-2">
                            <flux:heading size="xl" class="text-2xl font-bold tabular-nums">Rp
                                {{ number_format($this->totalManagedBalance, 0, ',', '.') }}
                            </flux:heading>
                        </div>
                    </div>
                    <div
                        class="p-3 bg-violet-500/10 dark:bg-violet-500/20 rounded-2xl text-violet-600 dark:text-violet-400 group-hover:rotate-12 transition-transform duration-300">
                        <flux:icon name="chart-bar" variant="outline" class="w-6 h-6" />
                    </div>
                </div>
                <div
                    class="grid grid-cols-2 gap-4 border-t border-violet-100 dark:border-violet-900/30 pt-4 mt-4 relative z-10">
                    <div class="space-y-1">
                        <flux:text class="text-[9px] uppercase tracking-wider text-zinc-500">Tagihan</flux:text>
                        <div class="font-bold text-zinc-800 dark:text-zinc-200 text-xs tabular-nums">
                            Rp {{ number_format($this->totalBillPaid, 0, ',', '.') }}
                        </div>
                    </div>
                    <div class="space-y-1">
                        <flux:text class="text-[9px] uppercase tracking-wider text-zinc-500">Top Up</flux:text>
                        <div class="font-bold text-zinc-800 dark:text-zinc-200 text-xs tabular-nums">
                            Rp {{ number_format($this->totalTopUp, 0, ',', '.') }}
                        </div>
                    </div>
                </div>
                <div class="absolute -bottom-2 -right-2 opacity-5 scale-150 rotate-12 text-violet-600 dark:text-violet-400">
                    <flux:icon name="chart-bar" class="w-20 h-20" />
                </div>
            </flux:card>

            <!-- Fees -->
            <flux:card
                class="relative overflow-hidden group hover:shadow-lg transition-all duration-300 border-none bg-gradient-to-br from-pink-500/10 to-rose-500/5 dark:from-pink-500/15 dark:to-rose-500/10 ring-1 ring-pink-100 dark:ring-pink-900/30 flex flex-col h-full">
                <div class="flex items-start justify-between flex-1">
                    <div class="space-y-3">
                        <flux:text class="font-bold text-pink-600 dark:text-pink-400 uppercase tracking-widest text-[10px]">
                            Platform Fee (Total)</flux:text>
                        <div class="flex items-baseline gap-2">
                            <flux:heading size="xl" class="text-2xl font-bold tabular-nums">Rp
                                {{ number_format($this->totalPlatformFee, 0, ',', '.') }}
                            </flux:heading>
                        </div>
                    </div>
                    <div
                        class="p-3 bg-pink-500/10 dark:bg-pink-500/20 rounded-2xl text-pink-600 dark:text-pink-400 group-hover:rotate-12 transition-transform duration-300">
                        <flux:icon name="receipt-percent" variant="outline" class="w-6 h-6" />
                    </div>
                </div>

                <div
                    class="grid grid-cols-2 gap-4 border-t border-pink-100 dark:border-pink-900/30 pt-4 mt-4 relative z-10">
                    <div class="space-y-1">
                        <flux:text class="text-[9px] uppercase tracking-wider text-zinc-500">Bagi Hasil (System)</flux:text>
                        <div class="font-bold text-zinc-800 dark:text-zinc-200 text-xs tabular-nums">
                            Rp {{ number_format($this->totalPlatformFee / 2, 0, ',', '.') }}
                        </div>
                    </div>
                    <div class="space-y-1">
                        <flux:text class="text-[9px] uppercase tracking-wider text-zinc-500">Bagi Hasil (School)</flux:text>
                        <div class="font-bold text-zinc-800 dark:text-zinc-200 text-xs tabular-nums">
                            Rp {{ number_format($this->totalPlatformFee / 2, 0, ',', '.') }}
                        </div>
                    </div>
                </div>

                <div class="absolute -bottom-2 -right-2 opacity-5 scale-150 rotate-12 text-pink-600 dark:text-pink-400">
                    <flux:icon name="receipt-percent" class="w-20 h-20" />
                </div>
            </flux:card>
        </div>
    </div>

    <!-- Main Chart -->
    <flux:card class="space-y-6">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">Trend Pemasukan (30 Hari Terakhir)</flux:heading>
            <flux:select variant="listbox" wire:model.live="chartFilter" class="w-32">
                <flux:select.option value="30_days">30 Hari</flux:select.option>
            </flux:select>
        </div>

        <div x-data="{
            chart: null,
            init() {
                if (this.chart) this.chart.destroy();
                const isDark = document.documentElement.classList.contains('dark');

                const options = {
                    series: @js($this->chartData['series']),
                    chart: {
                        type: 'area',
                        height: 320,
                        fontFamily: 'Inter, sans-serif',
                        toolbar: { show: false },
                        zoom: { enabled: false },
                        background: 'transparent'
                    },
                    colors: ['#34d399', '#a78bfa', '#f472b6'],
                    dataLabels: { enabled: false },
                    stroke: {
                        curve: 'smooth',
                        width: 2
                    },
                    fill: {
                        type: 'gradient',
                        gradient: {
                            shadeIntensity: 1,
                            opacityFrom: 0.45,
                            opacityTo: 0.05,
                            stops: [50, 100]
                        }
                    },
                    xaxis: {
                        categories: @js($this->chartData['categories']),
                        axisBorder: { show: false },
                        axisTicks: { show: false },
                        labels: {
                            style: {
                                colors: 'var(--color-zinc-400)',
                                fontSize: '11px'
                            }
                        }
                    },
                    yaxis: {
                        labels: {
                            style: {
                                colors: 'var(--color-zinc-500)',
                                fontSize: '11px'
                            },
                            formatter: (value) => {
                                return new Intl.NumberFormat('id-ID', { notation: 'compact' }).format(value)
                            }
                        }
                    },
                    grid: {
                        borderColor: isDark ? '#334155' : '#e2e8f0',
                        strokeDashArray: 4,
                    },
                    theme: {
                        mode: isDark ? 'dark' : 'light'
                    },
                    legend: {
                        position: 'top',
                        horizontalAlign: 'right',
                        labels: { colors: 'var(--color-zinc-500)' }
                    }
                };

                this.chart = new ApexCharts(this.$refs.chart, options);
                this.chart.render();

                // Watch for changes in data
                this.$watch('$wire.chartData', (val) => {
                     // Since public property is synced, we can look at val (which is the new chartData)
                     // Livewire usually syncs property changes to 'val'
                     if(val) {
                         this.chart.updateSeries(val.series);
                         this.chart.updateOptions({ xaxis: { categories: val.categories } });
                     }
                });
                
                // Also watch for manual refreshes if needed, but wire:model.live on filter should trigger fetch
            }
        }" wire:ignore class="w-full h-80">
            <div x-ref="chart" class="w-full h-full"></div>
        </div>
    </flux:card>
</div>