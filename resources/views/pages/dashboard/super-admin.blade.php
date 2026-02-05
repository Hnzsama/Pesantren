<?php

use App\Models\Bill;
use App\Models\WalletTransaction;
use App\Models\School;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
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
    public function totalPlatformFee()
    {
        $billFees = Bill::where('status', 'PAID')
            ->where('admin_fee', '>', 0)
            ->when($this->schoolId, fn($q) => $q->where('school_id', $this->schoolId))
            ->sum('admin_fee');

        $walletFees = WalletTransaction::where('status', 'PAID')
            ->where('admin_fee', '>', 0)
            ->when($this->schoolId, fn($q) => $q->where('school_id', $this->schoolId))
            ->sum('admin_fee');

        return $billFees + $walletFees;
    }

    #[Computed]
    public function totalManagedBalance()
    {
        // This represents the total value flowing through the system verified by payment gateway?
        // OR the sum of funds currently held by ALL schools (which we might not track cleanly if there's no withdrawal yet).
        // Since "Pesantren having saldo" comes from Bill Payments + Topups,
        // essentially Total Inflow (gross). Let's use that definition for "Accumulated Value".
        // Or if we strictly mean "Current Balance", we simply sum up wallet balances if we had them.
        // Given wallet transactions table, we can sum (CREDITS - DEBITS).

        // Sum all CREDITS (Topup + Inflow) - DEBITS (Withdrawal/Usage)
        // But for dashboard simplicity, let's track "Gross Revenue Processed" (Bill + Topup)
        // because we don't handle withdrawals yet.
        return $this->totalBillPaid + $this->totalTopUp;
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

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Total Managed Balance -->
        <flux:card class="overflow-hidden min-w-[12rem]">
            <flux:text>Total Managed Balance</flux:text>
            <flux:heading size="xl" class="mt-2 tabular-nums">Rp
                {{ number_format($this->totalManagedBalance, 0, ',', '.') }}
            </flux:heading>
            <flux:chart class="-mx-8 -mb-8 h-[3rem]" :value="$this->sparklines['bills']">
                <flux:chart.svg gutter="0">
                    <flux:chart.line class="text-sky-200 dark:text-sky-400" />
                    <flux:chart.area class="text-sky-100 dark:text-sky-400/30" />
                </flux:chart.svg>
            </flux:chart>
        </flux:card>

        <!-- Bill Paid -->
        <flux:card class="overflow-hidden min-w-[12rem]">
            <flux:text>Total Bill Paid</flux:text>
            <flux:heading size="xl" class="mt-2 tabular-nums">Rp {{ number_format($this->totalBillPaid, 0, ',', '.') }}
            </flux:heading>
            <flux:chart class="-mx-8 -mb-8 h-[3rem]" :value="$this->sparklines['bills']">
                <flux:chart.svg gutter="0">
                    <flux:chart.line class="text-emerald-200 dark:text-emerald-400" />
                    <flux:chart.area class="text-emerald-100 dark:text-emerald-400/30" />
                </flux:chart.svg>
            </flux:chart>
        </flux:card>

        <!-- Top Up -->
        <flux:card class="overflow-hidden min-w-[12rem]">
            <flux:text>Total Top Up Santri</flux:text>
            <flux:heading size="xl" class="mt-2 tabular-nums">Rp {{ number_format($this->totalTopUp, 0, ',', '.') }}
            </flux:heading>
            <flux:chart class="-mx-8 -mb-8 h-[3rem]" :value="$this->sparklines['topups']">
                <flux:chart.svg gutter="0">
                    <flux:chart.line class="text-violet-200 dark:text-violet-400" />
                    <flux:chart.area class="text-violet-100 dark:text-violet-400/30" />
                </flux:chart.svg>
            </flux:chart>
        </flux:card>

        <!-- Fees -->
        <flux:card class="overflow-hidden min-w-[12rem]">
            <flux:text>Total Platform Fee</flux:text>
            <flux:heading size="xl" class="mt-2 tabular-nums">Rp
                {{ number_format($this->totalPlatformFee, 0, ',', '.') }}
            </flux:heading>
            <!-- Using bills sparkline as proxy for now or flat line -->
            <flux:chart class="-mx-8 -mb-8 h-[3rem]" :value="$this->sparklines['bills']">
                <flux:chart.svg gutter="0">
                    <flux:chart.line class="text-pink-200 dark:text-pink-400" />
                    <flux:chart.area class="text-pink-100 dark:text-pink-400/30" />
                </flux:chart.svg>
            </flux:chart>
        </flux:card>
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
                                colors: '#9ca3af',
                                fontSize: '12px'
                            }
                        }
                    },
                    yaxis: {
                        labels: {
                            style: {
                                colors: '#9ca3af',
                                fontSize: '12px'
                            },
                            formatter: (value) => {
                                return new Intl.NumberFormat('id-ID', { notation: 'compact' }).format(value)
                            }
                        }
                    },
                    grid: {
                        borderColor: '#f3f4f6',
                        strokeDashArray: 4,
                    },
                    theme: {
                        mode: document.documentElement.classList.contains('dark') ? 'dark' : 'light'
                    },
                    legend: {
                        position: 'top',
                        horizontalAlign: 'right'
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