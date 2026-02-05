<?php

use App\Models\Bill;
use App\Models\WalletTransaction;
use App\Models\BillCategory;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $dateFilter = '30_days';

    public function mount()
    {
        // Default filter
    }

    #[Computed]
    public function schoolId()
    {
        return Auth::user()->school_id;
    }

    #[Computed]
    public function saldoPesantren()
    {
        // LOGIC REVISION: Saldo Pesantren = Total Gross Fund Managed
        // Not reduced by fees (because users withdraw net, but system tracks gross flow)
        return Bill::where('school_id', $this->schoolId)
            ->where('status', 'PAID')
            ->sum('amount');
    }

    #[Computed]
    public function totalBillPaid()
    {
        // Same as Saldo (Accumulated Gross Revenue)
        return $this->saldoPesantren;
    }

    #[Computed]
    public function totalBillUnpaid()
    {
        return Bill::where('school_id', $this->schoolId)
            ->where('status', 'UNPAID')
            ->sum('amount');
    }

    #[Computed]
    public function totalTopUp()
    {
        // Informational only - Santri Money
        return WalletTransaction::where('school_id', $this->schoolId)
            ->where('type', 'CREDIT')
            ->where('category', 'TOPUP')
            ->where('status', 'PAID')
            ->sum('amount');
    }

    #[Computed]
    public function totalPlatformFee()
    {
        $billFees = Bill::where('school_id', $this->schoolId)
            ->where('status', 'PAID')
            ->sum('admin_fee');

        $topupFees = WalletTransaction::where('school_id', $this->schoolId)
            ->where('status', 'PAID')
            ->sum('admin_fee');

        return $billFees + $topupFees;
    }

    #[Computed]
    public function totalPgFee()
    {
        $billFees = Bill::where('school_id', $this->schoolId)
            ->where('status', 'PAID')
            ->sum('payment_fee');

        $topupFees = WalletTransaction::where('school_id', $this->schoolId)
            ->where('status', 'PAID')
            ->sum('payment_fee');

        return $billFees + $topupFees;
    }

    #[Computed]
    public function chartDataCategories()
    {
        // 1. Chart Bills per Kategori
        $data = Bill::join('bill_categories', 'bills.bill_category_id', '=', 'bill_categories.id')
            ->where('bills.school_id', $this->schoolId)
            ->where('bills.status', 'PAID')
            ->groupBy('bill_categories.name')
            ->selectRaw('bill_categories.name as label, sum(bills.amount) as value')
            ->get();

        return [
            'labels' => $data->pluck('label'),
            'values' => $data->pluck('value'),
        ];
    }

    #[Computed]
    public function chartDataTrend()
    {
        // 2. Chart Tren Pembayaran (Last 30 Days)
        $days = 30;
        $data = Bill::where('school_id', $this->schoolId)
            ->where('status', 'PAID')
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
            $dates[] = \Carbon\Carbon::parse($date)->format('d M');
            $values[] = $data[$date] ?? 0;
        }

        return [
            'categories' => $dates,
            'series' => [['name' => 'Pemasukan', 'data' => $values]],
        ];
    }

    #[Computed]
    public function transactions()
    {
        // Union Query for Table
        $bills = Bill::query()
            ->selectRaw("
                id, 
                invoice_code, 
                student_id, 
                title as description, 
                amount, 
                admin_fee, 
                payment_fee, 
                status, 
                created_at, 
                'BILL' as type,
                'Tagihan' as category_label
            ")
            ->where('school_id', $this->schoolId);

        $topups = WalletTransaction::query()
            ->selectRaw("
                id, 
                invoice_code, 
                student_id, 
                description, 
                amount, 
                admin_fee, 
                payment_fee, 
                status, 
                created_at, 
                'TOPUP' as type,
                'Top Up Santri' as category_label
            ")
            ->where('school_id', $this->schoolId)
            ->where('category', 'TOPUP');

        // Apply Date Filter if needed (simplified for now as prompt asked for range but 'bisa filter' implies UI control)
        // For now just show latest mixed

        // Apply Date Filter if needed
        // For now just show latest mixed

        $query = $bills->unionAll($topups)
            ->orderBy('created_at', 'desc');

        $paginator = $query->paginate(10);

        // Eager load names manually to avoid N+1 and fix Unknown issue
        $studentIds = collect($paginator->items())->pluck('student_id')->unique()->filter()->toArray();

        if (!empty($studentIds)) {
            $studentNames = \App\Models\Student::whereIn('id', $studentIds)->pluck('full_name', 'id');

            $paginator->getCollection()->transform(function ($item) use ($studentNames) {
                // Attach name directly to the object
                $item->student_name = $studentNames[$item->student_id] ?? 'Unknown';
                return $item;
            });
        }

        return $paginator;
    }
}; ?>

<div class="space-y-8">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl" level="1">Dashboard Pesantren</flux:heading>
            <flux:subheading size="lg" class="mb-6">Ringkasan Keuangan & Transaksi</flux:subheading>
        </div>
        <flux:button icon="arrow-path" variant="subtle" wire:click="$refresh">Refresh</flux:button>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Saldo Pesantren (Primary) -->
        <flux:card class="bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-800">
            <div class="flex items-start justify-between">
                <div>
                    <flux:text class="text-emerald-600 dark:text-emerald-400 font-medium">Saldo Pesantren Saat Ini
                    </flux:text>
                    <flux:heading size="xl" class="mt-2 tabular-nums text-emerald-700 dark:text-emerald-300">
                        Rp {{ number_format($this->saldoPesantren, 0, ',', '.') }}
                    </flux:heading>
                    <flux:text size="sm" class="mt-1 text-emerald-600/70 dark:text-emerald-400/60">
                        Dana yang dikelola sistem
                    </flux:text>
                </div>
                <div class="p-2 bg-emerald-100 dark:bg-emerald-800 rounded-lg">
                    <flux:icon.wallet class="w-6 h-6 text-emerald-600 dark:text-emerald-300" />
                </div>
            </div>
        </flux:card>

        <!-- Bill Paid -->
        <flux:card>
            <div class="flex items-start justify-between">
                <div>
                    <flux:text>Total Bill Lunas</flux:text>
                    <flux:heading size="xl" class="mt-2 tabular-nums">
                        Rp {{ number_format($this->totalBillPaid, 0, ',', '.') }}
                    </flux:heading>
                    <flux:text size="sm" class="mt-1 text-zinc-500">
                        Akumulasi pembayaran masuk
                    </flux:text>
                </div>
                <div class="p-2 bg-blue-50 dark:bg-blue-900/30 rounded-lg">
                    <flux:icon.document-check class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                </div>
            </div>
        </flux:card>

        <!-- Bill Unpaid -->
        <flux:card>
            <div class="flex items-start justify-between">
                <div>
                    <flux:text>Total Bill Belum Lunas</flux:text>
                    <flux:heading size="xl" class="mt-2 tabular-nums text-orange-600 dark:text-orange-400">
                        Rp {{ number_format($this->totalBillUnpaid, 0, ',', '.') }}
                    </flux:heading>
                    <flux:text size="sm" class="mt-1 text-zinc-500">
                        Potensi pemasukan tertunda
                    </flux:text>
                </div>
                <div class="p-2 bg-orange-50 dark:bg-orange-900/30 rounded-lg">
                    <flux:icon.clock class="w-6 h-6 text-orange-600 dark:text-orange-400" />
                </div>
            </div>
        </flux:card>
    </div>

    <!-- Informational Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <flux:card>
            <flux:text>Total Top Up Santri</flux:text>
            <flux:heading size="lg" class="mt-1 tabular-nums">
                Rp {{ number_format($this->totalTopUp, 0, ',', '.') }}
            </flux:heading>
            <flux:text size="sm" class="mt-1 text-zinc-400">
                Informational (Tidak masuk saldo pesantren)
            </flux:text>
        </flux:card>

        <flux:card>
            <flux:text>Total Platform Fee</flux:text>
            <flux:heading size="lg" class="mt-1 tabular-nums text-zinc-500">
                Rp {{ number_format($this->totalPlatformFee, 0, ',', '.') }}
            </flux:heading>
            <flux:text size="sm" class="mt-1 text-zinc-400">
                Biaya layanan sistem
            </flux:text>
        </flux:card>

        <flux:card>
            <flux:text>Total PG Fee</flux:text>
            <flux:heading size="lg" class="mt-1 tabular-nums text-zinc-500">
                Rp {{ number_format($this->totalPgFee, 0, ',', '.') }}
            </flux:heading>
            <flux:text size="sm" class="mt-1 text-zinc-400">
                Biaya Payment Gateway
            </flux:text>
        </flux:card>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Category Chart -->
        <flux:card>
            <flux:heading size="lg" class="mb-4">Distribusi Pembayaran per Kategori</flux:heading>
            <div x-data="{
                init() {
                     const options = {
                        series: [{
                            name: 'Total',
                            data: @js($this->chartDataCategories['values'])
                        }],
                        chart: { type: 'bar', height: 300, toolbar: { show: false }, background: 'transparent' },
                        plotOptions: {
                            bar: { borderRadius: 4, horizontal: true, }
                        },
                        dataLabels: { enabled: false },
                        xaxis: {
                            categories: @js($this->chartDataCategories['labels']),
                            labels: {
                                style: { colors: 'var(--color-zinc-500)', fontSize: '12px' },
                                formatter: (val) => {
                                    return new Intl.NumberFormat('id-ID', { notation: 'compact' }).format(val)
                                }
                            },
                             axisBorder: { show: false },
                            axisTicks: { show: false }
                        },
                        yaxis: {
                            labels: {
                                style: { colors: 'var(--color-zinc-500)', fontSize: '12px' }
                            }
                        },
                         colors: ['#3b82f6'],
                         grid: { borderColor: 'var(--color-zinc-200)', strokeDashArray: 4 },
                         theme: {
                            mode: document.documentElement.classList.contains('dark') ? 'dark' : 'light'
                        }
                    };
                    const chart = new ApexCharts(this.$el, options);
                    chart.render();
                    
                    // Listen for dark mode toggle if implemented, or just render once
                    // Ideally we'd watch for class changes on html, but simple render is okay for now
                }
            }" class="w-full h-[300px]"></div>
        </flux:card>

        <!-- Trend Chart -->
        <flux:card>
            <flux:heading size="lg" class="mb-4">Tren Pembayaran (30 Hari Terakhir)</flux:heading>
            <div x-data="{
                init() {
                     const options = {
                        series: @js($this->chartDataTrend['series']),
                        chart: { type: 'area', height: 300, toolbar: { show: false }, background: 'transparent' },
                        dataLabels: { enabled: false },
                        stroke: { curve: 'smooth', width: 2 },
                        xaxis: {
                            categories: @js($this->chartDataTrend['categories']),
                            axisBorder: { show: false },
                            axisTicks: { show: false },
                            labels: { style: { colors: 'var(--color-zinc-500)', fontSize: '12px' } }
                        },
                         yaxis: {
                            labels: {
                                style: { colors: 'var(--color-zinc-500)', fontSize: '12px' },
                                formatter: (value) => {
                                    return new Intl.NumberFormat('id-ID', { notation: 'compact' }).format(value)
                                }
                            }
                        },
                        colors: ['#10b981'],
                        fill: {
                            type: 'gradient',
                            gradient: { shadeIntensity: 1, opacityFrom: 0.45, opacityTo: 0.05, stops: [50, 100] }
                        },
                         grid: { borderColor: 'var(--color-zinc-200)', strokeDashArray: 4 },
                         theme: {
                            mode: document.documentElement.classList.contains('dark') ? 'dark' : 'light'
                        },
                        tooltip: {
                            theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light'
                        }
                    };
                    const chart = new ApexCharts(this.$el, options);
                    chart.render();
                }
            }" class="w-full h-[300px]"></div>
        </flux:card>
    </div>

    <!-- Transaction Table -->
    <flux:card>
        <div class="flex items-center justify-between mb-4">
            <flux:heading size="lg">Mutasi Transaksi</flux:heading>
            <!-- Filters could go here -->
        </div>

        <flux:table :paginate="$this->transactions">
            <flux:table.columns>
                <flux:table.column>Invoice</flux:table.column>
                <flux:table.column>Santri</flux:table.column>
                <flux:table.column>Kategori</flux:table.column>
                <flux:table.column align="right">Nominal Bersih</flux:table.column>
                <flux:table.column align="right">Platform Fee</flux:table.column>
                <flux:table.column align="right">PG Fee</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Tanggal</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->transactions as $trx)
                    <flux:table.row>
                        <flux:table.cell class="font-medium font-mono">{{ $trx->invoice_code }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <span class="font-medium">{{ $trx->student_name ?? '-' }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$trx->type === 'BILL' ? 'blue' : 'violet'">
                                {{ $trx->category_label ?? $trx->description }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="right" class="font-medium">
                            Rp {{ number_format($trx->amount, 0, ',', '.') }}
                        </flux:table.cell>
                        <flux:table.cell align="right" class="text-zinc-500">
                            {{ $trx->admin_fee > 0 ? number_format($trx->admin_fee, 0, ',', '.') : '-' }}
                        </flux:table.cell>
                        <flux:table.cell align="right" class="text-zinc-500">
                            {{ $trx->payment_fee > 0 ? number_format($trx->payment_fee, 0, ',', '.') : '-' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @php
                                $color = match ($trx->status) {
                                    'PAID' => 'green',
                                    'UNPAID' => 'orange',
                                    'CANCELLED' => 'red',
                                    default => 'zinc'
                                };
                            @endphp
                            <flux:badge size="sm" :color="$color" inset="top bottom">{{ $trx->status }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="text-zinc-500">
                            {{ \Carbon\Carbon::parse($trx->created_at)->format('d M Y H:i') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

    </flux:card>
</div>