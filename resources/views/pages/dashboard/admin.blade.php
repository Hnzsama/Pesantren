<?php

use App\Models\Bill;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy] class extends Component
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

    use WithPagination;

    #[Url]
    public $search = '';

    #[Url]
    public $studentFilter = '';

    #[Url]
    public $typeFilter = ''; // ALL, IN, OUT

    #[Url]
    public $statusFilter = '';

    #[Url]
    public $categoryFilter = '';

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
    public function totalDigitalBalance()
    {
        // Combined balance of all digital/gateway money (Revenue + Trust Fund)
        // Formula: Total Paid Bills (Manual + Gateway) + Total Digital Topups - Total HQ Withdrawals (Committed)
        
        $totalHQWithdrawn = \App\Models\Withdrawal::where('school_id', $this->schoolId)
            ->whereIn('status', ['APPROVED', 'PAID'])
            ->sum('amount');

        $totalDigitalTopups = WalletTransaction::where('school_id', $this->schoolId)
            ->where('type', 'CREDIT')
            ->where('category', 'TOPUP')
            ->where('status', 'PAID')
            ->where('payment_method', '!=', 'cash')
            ->sum('amount');

        $totalPlatformFees = WalletTransaction::where('school_id', $this->schoolId)
            ->where('payment_method', 'cash')
            ->where('category', 'TOPUP')
            ->sum('admin_fee');

        return $this->totalBillPaid + $totalDigitalTopups - $totalHQWithdrawn - $totalPlatformFees;
    }

    #[Computed]
    public function totalBillPaid()
    {
        // FIXED: Historical accumulation - immutable, never decreases
        // This is the TOTAL income, NOT the balance
        return Bill::where('school_id', $this->schoolId)
            ->where('status', 'PAID')
            ->sum('amount');
    }

    #[Computed]
    public function totalPenarikanRevenueCommitted()
    {
        // For DISPLAY: Shows committed REVENUE withdrawals
        return \App\Models\Withdrawal::where('school_id', $this->schoolId)
            ->where('source_type', 'REVENUE')
            ->whereIn('status', ['APPROVED', 'PAID'])
            ->sum('amount');
    }

    #[Computed]
    public function totalPenarikanTrustCommitted()
    {
        // For DISPLAY: Shows committed TRUST_FUND withdrawals
        return \App\Models\Withdrawal::where('school_id', $this->schoolId)
            ->where('source_type', 'TRUST_FUND')
            ->whereIn('status', ['APPROVED', 'PAID'])
            ->sum('amount');
    }

    #[Computed]
    public function totalPenarikanDanaPaid()
    {
        // For BALANCE: Only withdrawals that have actually been paid
        // Money that has actually left the account
        return \App\Models\Withdrawal::where('school_id', $this->schoolId)
            ->where('status', 'PAID')
            ->sum('amount');
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
        // Gross historical top-ups (Digital/Gateway Only)
        // Cash top-ups go to cashOnHand instead
        return WalletTransaction::where('school_id', $this->schoolId)
            ->where('type', 'CREDIT')
            ->where('category', 'TOPUP')
            ->where('status', 'PAID')
            ->where('payment_method', '!=', 'cash')
            ->sum('amount');
    }

    #[Computed]
    public function totalPenarikanSantriPaid()
    {
        // Historical cash-out by students (The only thing that reduces Wallet B Cash Pool)
        return WalletTransaction::where('school_id', $this->schoolId)
            ->where('type', 'DEBIT')
            ->where('category', 'WITHDRAWAL')
            ->where('status', 'PAID')
            ->sum('amount');
    }

    #[Computed]
    public function cashOnHand()
    {
        // Formula: Total Cash Credits (Topups) - Total Cash Debits (Withdrawals) - Total Confirmed Deposits to HQ
        $totalCashCredits = WalletTransaction::where('school_id', $this->schoolId)
            ->where('payment_method', 'cash')
            ->where('type', 'CREDIT')
            ->sum('amount');

        $totalCashSales = WalletTransaction::where('school_id', $this->schoolId)
            ->where('payment_method', 'cash')
            ->where('category', 'PURCHASE')
            ->sum('amount');

        $totalManualBillPayments = Bill::where('school_id', $this->schoolId)
            ->where('status', 'PAID')
            ->where('payment_method', 'MANUAL')
            ->sum('amount');

        $totalCashCredits += $totalManualBillPayments + $totalCashSales;

        $totalCashDebits = WalletTransaction::where('school_id', $this->schoolId)
            ->where('payment_method', 'cash')
            ->where('category', 'WITHDRAWAL')
            ->where('type', 'DEBIT')
            ->sum('amount');

        $totalConfirmedDeposits = \App\Models\AdminDeposit::where('school_id', $this->schoolId)
            ->where('status', 'confirmed')
            ->sum('amount');

        return $totalCashCredits - $totalCashDebits - $totalConfirmedDeposits;
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
    public function students()
    {
        return \App\Models\Student::where('school_id', $this->schoolId)
            ->orderBy('full_name')
            ->get();
    }

    #[Computed]
    public function billCategories()
    {
        return \App\Models\BillCategory::where('school_id', $this->schoolId)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function transactions()
    {
        // 1. BILLS (IN)
        $bills = Bill::query()
            ->selectRaw("
                id, invoice_code, student_id, title as description, amount, 
                admin_fee, payment_fee, status, created_at, 
                'IN' as flow_type, 'Tagihan' as category_label, 'BILL' as type,
                null as requested_by_id
            ")
            ->where('school_id', $this->schoolId);

        // 2. TOPUPS (IN)
        $topups = WalletTransaction::query()
            ->selectRaw("
                id, invoice_code, student_id, description, amount, 
                admin_fee, payment_fee, status, created_at, 
                'IN' as flow_type, 'Top Up' as category_label, 'TOPUP' as type,
                null as requested_by_id
            ")
            ->where('school_id', $this->schoolId)
            ->where('category', 'TOPUP');

        // 3. STUDENT WITHDRAWALS (OUT)
        $studentWithdrawals = WalletTransaction::query()
            ->selectRaw("
                id, invoice_code, student_id, description, amount, 
                admin_fee, payment_fee, status, created_at, 
                'OUT' as flow_type, 'Tarik Tunai' as category_label, 'WITHDRAWAL' as type,
                null as requested_by_id
            ")
            ->where('school_id', $this->schoolId)
            ->where('category', 'WITHDRAWAL');

        // 4. STUDENT PURCHASES (OUT)
        $studentPurchases = WalletTransaction::query()
            ->selectRaw("
                id, invoice_code, student_id, description, amount, 
                admin_fee, payment_fee, status, created_at, 
                'OUT' as flow_type, 'Pembelanjaan' as category_label, 'PURCHASE' as type,
                null as requested_by_id
            ")
            ->where('school_id', $this->schoolId)
            ->where('category', 'PURCHASE');

        // 5. HQ WITHDRAWALS (OUT)
        // Note: HQ withdrawals don't have invoice_code or student_id, we map them for compatibility
        $hqWithdrawals = \App\Models\Withdrawal::query()
            ->selectRaw("
                id, '-' as invoice_code, null as student_id, notes as description, amount, 
                0 as admin_fee, 0 as payment_fee, status, created_at, 
                'OUT' as flow_type, 'Penarikan HQ' as category_label, 'HQ_WITHDRAWAL' as type,
                requested_by as requested_by_id
            ")
            ->where('school_id', $this->schoolId);

        // --- APPLY FILTERS ---

        // Wrap queries to allow filtering before union
        $applyFilters = function ($q) {
            return $q->when($this->studentFilter, fn ($query) => $query->where('student_id', $this->studentFilter))
                ->when($this->statusFilter, fn ($query) => $query->where('status', $this->statusFilter))
                ->when($this->search, function ($query) {
                    $query->where(function ($sub) {
                        $sub->where('invoice_code', 'like', "%{$this->search}%")
                            ->orWhere('description', 'like', "%{$this->search}%");
                    });
                });
        };

        $bills = $applyFilters($bills);
        $topups = $applyFilters($topups);
        $studentWithdrawals = $applyFilters($studentWithdrawals);
        $studentPurchases = $applyFilters($studentPurchases);
        // HQ Withdrawals only gets search on description/id as it has no student/invoice
        $hqWithdrawals = $hqWithdrawals->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->search, fn ($q) => $q->where('notes', 'like', "%{$this->search}%"));

        // Final Query Construction
        $query = collect();

        if (! $this->typeFilter || $this->typeFilter === 'IN') {
            if (! $this->categoryFilter || $this->categoryFilter === 'BILL') {
                $query->push($bills);
            }
            if (! $this->categoryFilter || $this->categoryFilter === 'TOPUP') {
                $query->push($topups);
            }
        }

        if (! $this->typeFilter || $this->typeFilter === 'OUT') {
            if (! $this->categoryFilter || $this->categoryFilter === 'WITHDRAWAL') {
                $query->push($studentWithdrawals);
            }
            if (! $this->categoryFilter || $this->categoryFilter === 'PURCHASE') {
                $query->push($studentPurchases);
            }
            if (! $this->categoryFilter || $this->categoryFilter === 'HQ_WITHDRAWAL') {
                $query->push($hqWithdrawals);
            }
        }

        if ($query->isEmpty()) {
            // Return empty set if everything filtered out or invalid
            return Bill::whereRaw('1=0')->paginate(10);
        }

        $finalQuery = $query->shift();
        foreach ($query as $q) {
            $finalQuery = $finalQuery->unionAll($q);
        }

        $paginator = $finalQuery->orderBy('created_at', 'desc')->paginate(10);

        // Eager load names manually to avoid N+1 and fix Unknown issue
        $studentIds = collect($paginator->items())->pluck('student_id')->unique()->filter()->toArray();
        $adminIds = collect($paginator->items())->pluck('requested_by_id')->unique()->filter()->toArray();

        $studentNames = ! empty($studentIds) ? \App\Models\Student::whereIn('id', $studentIds)->pluck('full_name', 'id') : collect();
        $adminNames = ! empty($adminIds) ? \App\Models\User::whereIn('id', $adminIds)->pluck('name', 'id') : collect();

        $paginator->getCollection()->transform(function ($item) use ($studentNames, $adminNames) {
            if ($item->type === 'HQ_WITHDRAWAL') {
                $item->display_name = $adminNames[$item->requested_by_id] ?? 'Unknown Admin';
            } else {
                $item->display_name = $studentNames[$item->student_id] ?? 'Unknown Santri';
            }

            return $item;
        });

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
        <div class="flex gap-2">
            <flux:button href="{{ route('dashboard.admin.manual-bill-payment') }}" icon="banknotes" variant="primary">Bayar Tagihan Manual</flux:button>
            <flux:button icon="arrow-path" variant="subtle" wire:click="$refresh">Refresh</flux:button>
        </div>
    </div>

    <!-- ROW 1: CORE FINANCIAL (3 columns) -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Total Saldo di Pusat (Digital) -->
        <flux:card
            class="relative overflow-hidden group hover:shadow-lg transition-all duration-300 border-none bg-gradient-to-br from-emerald-500/10 to-teal-500/5 dark:from-emerald-500/15 dark:to-teal-500/10 ring-1 ring-emerald-100 dark:ring-emerald-900/30">
            <div class="flex items-start justify-between">
                <div class="space-y-3">
                    <flux:text
                        class="font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-widest text-[10px]">
                        Total Saldo di Pusat (Sistem)</flux:text>
                    <div class="flex items-baseline gap-2">
                        <flux:heading size="xl"
                            class="text-2xl font-bold tabular-nums text-emerald-700 dark:text-emerald-300">Rp
                            {{ number_format($this->totalDigitalBalance, 0, ',', '.') }}
                        </flux:heading>
                    </div>
                    <flux:text size="xs"
                        class="font-medium text-emerald-600/70 dark:text-emerald-400/60 transition-opacity group-hover:opacity-100 italic">
                        Gabungan Dana Utama & Titipan</flux:text>
                </div>
                <div
                    class="p-3 bg-emerald-500/10 dark:bg-emerald-500/20 rounded-2xl text-emerald-600 dark:text-emerald-400 group-hover:rotate-12 transition-transform duration-300">
                    <flux:icon name="globe-alt" variant="outline" class="w-6 h-6" />
                </div>
            </div>
            <div
                class="absolute -bottom-2 -right-2 opacity-5 scale-150 rotate-12 text-emerald-600 dark:text-emerald-400">
                <flux:icon name="globe-alt" class="w-20 h-20" />
            </div>
        </flux:card>

        <!-- Cash on Hand (Admin) -->
        <flux:card
            class="relative overflow-hidden group hover:shadow-lg transition-all duration-300 border-none bg-gradient-to-br from-amber-500/10 to-orange-500/5 dark:from-amber-500/15 dark:to-orange-500/10 ring-1 ring-amber-100 dark:ring-amber-900/30">
            <div class="flex items-start justify-between">
                <div class="space-y-3">
                    <flux:text
                        class="font-bold text-amber-600 dark:text-amber-400 uppercase tracking-widest text-[10px]">
                        Uang Tunai di Tangan Admin</flux:text>
                    <div class="flex items-baseline gap-2">
                        <flux:heading size="xl"
                            class="text-2xl font-bold tabular-nums text-amber-700 dark:text-amber-300">Rp
                            {{ number_format($this->cashOnHand, 0, ',', '.') }}
                        </flux:heading>
                    </div>
                    <flux:text size="xs"
                        class="font-medium text-amber-600/70 dark:text-amber-400/60 transition-opacity group-hover:opacity-100 italic">
                        Wajib disetor ke Pusat</flux:text>
                </div>
                <div
                    class="p-3 bg-amber-500/10 dark:bg-amber-500/20 rounded-2xl text-amber-600 dark:text-amber-400 group-hover:rotate-12 transition-transform duration-300">
                    <flux:icon name="identification" variant="outline" class="w-6 h-6" />
                </div>
            </div>
            <div
                class="absolute -bottom-2 -right-2 opacity-5 scale-150 rotate-12 text-amber-600 dark:text-amber-400">
                <flux:icon name="identification" class="w-20 h-20" />
            </div>
        </flux:card>

        <!-- Bill Paid -->
        <flux:card
            class="relative overflow-hidden group hover:shadow-lg transition-all duration-300 border-none bg-gradient-to-br from-blue-500/10 to-indigo-500/5 dark:from-sky-500/15 dark:to-indigo-500/10 ring-1 ring-blue-100 dark:ring-blue-900/30">
            <div class="flex items-start justify-between">
                <div class="space-y-3">
                    <flux:text class="font-bold text-blue-600 dark:text-sky-400 uppercase tracking-widest text-[10px]">
                        Total Bill Lunas</flux:text>
                    <div class="flex items-baseline gap-2">
                        <flux:heading size="xl"
                            class="text-2xl font-bold tabular-nums text-blue-700 dark:text-blue-300">Rp
                            {{ number_format($this->totalBillPaid, 0, ',', '.') }}
                        </flux:heading>
                    </div>
                    <flux:text size="xs"
                        class="font-medium text-blue-600/70 dark:text-blue-400/60 transition-opacity group-hover:opacity-100 uppercase tracking-tight">
                        Historis</flux:text>
                </div>
                <div
                    class="p-3 bg-blue-500/10 dark:bg-sky-500/20 rounded-2xl text-blue-600 dark:text-sky-400 group-hover:rotate-12 transition-transform duration-300">
                    <flux:icon name="document-check" variant="outline" class="w-6 h-6" />
                </div>
            </div>
            <div class="absolute -bottom-2 -right-2 opacity-5 scale-150 rotate-12 text-blue-600 dark:text-sky-400">
                <flux:icon name="document-check" class="w-20 h-20" />
            </div>
        </flux:card>

        <!-- Bill Unpaid -->
        <flux:card
            class="relative overflow-hidden group hover:shadow-lg transition-all duration-300 border-none bg-gradient-to-br from-amber-500/10 to-orange-500/5 dark:from-amber-500/15 dark:to-orange-500/10 ring-1 ring-amber-100 dark:ring-amber-900/30">
            <div class="flex items-start justify-between">
                <div class="space-y-3">
                    <flux:text
                        class="font-bold text-amber-600 dark:text-amber-400 uppercase tracking-widest text-[10px]">Total
                        Bill Belum Lunas</flux:text>
                    <div class="flex items-baseline gap-2">
                        <flux:heading size="xl"
                            class="text-2xl font-bold tabular-nums text-amber-700 dark:text-amber-300">Rp
                            {{ number_format($this->totalBillUnpaid, 0, ',', '.') }}
                        </flux:heading>
                    </div>
                </div>
                <div
                    class="p-3 bg-amber-500/10 dark:bg-amber-500/20 rounded-2xl text-amber-600 dark:text-amber-400 group-hover:rotate-12 transition-transform duration-300">
                    <flux:icon name="clock" variant="outline" class="w-6 h-6" />
                </div>
            </div>
            <div class="absolute -bottom-2 -right-2 opacity-5 scale-150 rotate-12 text-amber-600 dark:text-amber-400">
                <flux:icon name="clock" class="w-20 h-20" />
            </div>
        </flux:card>
    </div>

    <!-- ROW 2: OTHER STATS -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Total Penarikan ke Rekening -->
        <flux:card
            class="relative overflow-hidden group hover:shadow-lg transition-all duration-300 border-none bg-gradient-to-br from-red-500/10 to-rose-500/5 dark:from-red-500/15 dark:to-rose-500/10 ring-1 ring-red-100 dark:ring-red-900/30">
            <div class="flex items-start justify-between">
                <div class="space-y-3">
                    <flux:text class="font-bold text-red-600 dark:text-red-400 uppercase tracking-widest text-[10px]">
                        Total Penarikan ke Rekening</flux:text>
                    <div class="flex items-baseline gap-2">
                        <flux:heading size="lg" class="tabular-nums text-red-600 dark:text-red-400">Rp
                            {{ number_format(\App\Models\Withdrawal::where('school_id', $this->schoolId)->whereIn('status', ['APPROVED', 'PAID'])->sum('amount'), 0, ',', '.') }}
                        </flux:heading>
                    </div>
                </div>
                <div
                    class="p-3 bg-red-500/10 dark:bg-red-900/10 rounded-2xl text-red-600 dark:text-red-400 group-hover:rotate-12 transition-transform duration-300">
                    <flux:icon name="arrow-trending-down" variant="outline" class="w-6 h-6" />
                </div>
            </div>
            <div class="absolute -bottom-2 -right-2 opacity-5 scale-150 rotate-12 text-red-600 dark:text-red-400">
                <flux:icon name="arrow-trending-down" class="w-20 h-20" />
            </div>
        </flux:card>

        <!-- Total Penarikan Tunai Santri -->
        <flux:card
            class="relative overflow-hidden group hover:shadow-lg transition-all duration-300 border-none bg-gradient-to-br from-zinc-500/10 to-slate-500/5 dark:from-zinc-500/15 dark:to-slate-500/10 ring-1 ring-zinc-200 dark:ring-zinc-800">
            <div class="flex items-start justify-between">
                <div class="space-y-3">
                    <flux:text class="font-bold text-zinc-600 dark:text-zinc-400 uppercase tracking-widest text-[10px]">
                        Total Penarikan Santri</flux:text>
                    <div class="flex items-baseline gap-2">
                        <flux:heading size="lg" class="tabular-nums text-zinc-700 dark:text-zinc-300">Rp
                            {{ number_format($this->totalPenarikanSantriPaid, 0, ',', '.') }}
                        </flux:heading>
                    </div>
                </div>
                <div
                    class="p-3 bg-zinc-500/10 dark:bg-zinc-800 rounded-2xl text-zinc-600 dark:text-zinc-400 group-hover:rotate-12 transition-transform duration-300">
                    <flux:icon name="user-minus" variant="outline" class="w-6 h-6" />
                </div>
            </div>
            <div class="absolute -bottom-2 -right-2 opacity-5 scale-150 rotate-12 text-zinc-600 dark:text-zinc-400">
                <flux:icon name="user-minus" class="w-20 h-20" />
            </div>
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
                                style: { colors: 'var(--color-zinc-400)', fontSize: '11px' },
                                formatter: (val) => {
                                    return new Intl.NumberFormat('id-ID', { notation: 'compact' }).format(val)
                                }
                            },
                             axisBorder: { show: false },
                            axisTicks: { show: false }
                        },
                        yaxis: {
                            labels: {
                                style: { colors: 'var(--color-zinc-500)', fontSize: '11px' }
                            }
                        },
                         colors: ['#3b82f6'],
                        grid: { 
                            borderColor: document.documentElement.classList.contains('dark') ? '#334155' : '#e2e8f0', 
                            strokeDashArray: 4 
                        },
                        theme: {
                            mode: document.documentElement.classList.contains('dark') ? 'dark' : 'light'
                        }
                    };
                    const chart = new ApexCharts(this.$el, options);
                    chart.render();
                }
            }" class="w-full h-[300px]"></div>
        </flux:card>

        <!-- Trend Chart -->
        <flux:card>
            <flux:heading size="lg" class="mb-4">Tren Pembayaran (30 Hari Terakhir)</flux:heading>
            <div x-data="{
                init() {
                     const isDark = document.documentElement.classList.contains('dark');
                     const options = {
                        series: @js($this->chartDataTrend['series']),
                        chart: { type: 'area', height: 300, toolbar: { show: false }, background: 'transparent' },
                        dataLabels: { enabled: false },
                        stroke: { curve: 'smooth', width: 2 },
                        xaxis: {
                            categories: @js($this->chartDataTrend['categories']),
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
                        colors: ['#10b981'],
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
            }" class="w-full h-[300px]"></div>
        </flux:card>
    </div>

    <!-- Transaction Table -->
    <flux:card>
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <flux:heading size="lg">Mutasi Transaksi</flux:heading>

            <div class="flex flex-wrap items-center gap-2">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari invoice/ket..."
                    icon="magnifying-glass" class="w-full md:w-64" />

                <flux:select wire:model.live="studentFilter" variant="listbox" searchable placeholder="Semua Santri"
                    class="w-full md:w-48">
                    <flux:select.option value="">Semua Santri</flux:select.option>
                    @foreach($this->students as $student)
                        <flux:select.option value="{{ $student->id }}">{{ $student->full_name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="typeFilter" variant="listbox" placeholder="Semua Tipe"
                    class="w-full md:w-32">
                    <flux:select.option value="">Semua Tipe</flux:select.option>
                    <flux:select.option value="IN">Masuk</flux:select.option>
                    <flux:select.option value="OUT">Keluar</flux:select.option>
                </flux:select>

                <flux:select wire:model.live="categoryFilter" variant="listbox" placeholder="Semua Kategori"
                    class="w-full md:w-40">
                    <flux:select.option value="">Semua Kategori</flux:select.option>
                    <flux:select.option value="BILL">Tagihan</flux:select.option>
                    <flux:select.option value="TOPUP">Top Up</flux:select.option>
                    <flux:select.option value="WITHDRAWAL">Tarik Tunai</flux:select.option>
                    <flux:select.option value="PURCHASE">Pembelanjaan</flux:select.option>
                    <flux:select.option value="HQ_WITHDRAWAL">Penarikan HQ</flux:select.option>
                </flux:select>

                <flux:select wire:model.live="statusFilter" variant="listbox" placeholder="Semua Status"
                    class="w-full md:w-32">
                    <flux:select.option value="">Semua Status</flux:select.option>
                    <flux:select.option value="PAID">PAID</flux:select.option>
                    <flux:select.option value="UNPAID">UNPAID</flux:select.option>
                    <flux:select.option value="REQUESTED">REQUESTED</flux:select.option>
                    <flux:select.option value="APPROVED">APPROVED</flux:select.option>
                    <flux:select.option value="REJECTED">REJECTED</flux:select.option>
                    <flux:select.option value="CANCELLED">CANCELLED</flux:select.option>
                </flux:select>
            </div>
        </div>

        <flux:table :paginate="$this->transactions">
            <flux:table.columns>
                <flux:table.column>Invoice</flux:table.column>
                <flux:table.column>Santri</flux:table.column>
                <flux:table.column>Kategori</flux:table.column>
                <flux:table.column align="right">Nominal Bersih</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Tanggal</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->transactions as $trx)
                    <flux:table.row>
                        <flux:table.cell class="font-medium font-mono text-xs">
                            {{ $trx->invoice_code }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-col">
                                <span class="font-medium text-sm">{{ $trx->display_name }}</span>
                                @if($trx->description)
                                    <span class="text-xs text-zinc-500 truncate max-w-[200px]">{{ $trx->description }}</span>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                @if($trx->flow_type === 'IN')
                                    <flux:icon.arrow-down-left class="w-4 h-4 text-emerald-500" />
                                @else
                                    <flux:icon.arrow-up-right class="w-4 h-4 text-orange-500" />
                                @endif
                                <flux:badge size="sm" :color="$trx->flow_type === 'IN' ? 'emerald' : 'orange'"
                                    variant="subtle">
                                    {{ $trx->category_label }}
                                </flux:badge>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell align="right" class="font-bold">
                            <span
                                class="{{ $trx->flow_type === 'IN' ? 'text-emerald-600 dark:text-emerald-400' : 'text-orange-600 dark:text-orange-400' }}">
                                {{ $trx->flow_type === 'IN' ? '+' : '-' }} Rp {{ number_format($trx->amount, 0, ',', '.') }}
                            </span>
                        </flux:table.cell>
                        <flux:table.cell>
                            @php
                                $color = match ($trx->status) {
                                    'PAID', 'APPROVED' => 'green',
                                    'UNPAID', 'REQUESTED' => 'orange',
                                    'CANCELLED', 'REJECTED' => 'red',
                                    default => 'zinc'
                                };
                            @endphp
                            <flux:badge size="sm" :color="$color" inset="top bottom">{{ $trx->status }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="text-xs text-zinc-500 whitespace-nowrap">
                            {{ \Carbon\Carbon::parse($trx->created_at)->format('d/m/y H:i') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

    </flux:card>
</div>