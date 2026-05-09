<?php

namespace App\Services;

use App\Models\AdminDeposit;
use App\Models\Bill;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\Cache;

class FinancialService
{
    /**
     * Calculate total balance from gateway payments for a school
     */
    public function getTotalGatewayBalance(string $schoolId): float
    {
        // Cache for 5 minutes as this doesn't change instantly usually, or if it does, 5 mins is acceptable lag for a "Total" stat
        return Cache::remember('finance.gateway_balance.' . $schoolId, 300, function () use ($schoolId) {
            $gatewayTopups = WalletTransaction::where('school_id', $schoolId)
                ->where('category', 'TOPUP')
                ->where('payment_method', 'gateway')
                ->sum('amount');

            $gatewayBills = Bill::where('school_id', $schoolId)
                ->where('status', 'PAID')
                ->where(function ($q) {
                    $q->where('payment_method', 'gateway')
                        ->orWhere('payment_method', 'like', 'payment_gateway%');
                })
                ->sum('amount');

            return $gatewayTopups + $gatewayBills;
        });
    }

    /**
     * Calculate current cash on hand for a school
     * Formula: Total Cash Credits (Topups) - Total Cash Debits (Withdrawals) - Total Confirmed Deposits to HQ
     */
    public function getCurrentCashOnHand(string $schoolId): float
    {
        // This is high velocity/critical. 5 minutes might be too long. Let's do 1 minute.
        // Or invalidation on transaction? For now, short TTL.
        return Cache::remember('finance.cash_on_hand.' . $schoolId, 60, function () use ($schoolId) {
            $totalCashCredits = WalletTransaction::where('school_id', $schoolId)
                ->where('payment_method', 'cash')
                ->where('type', 'CREDIT') // Topups
                ->sum('amount');

            $totalCashSales = WalletTransaction::where('school_id', $schoolId)
                ->where('payment_method', 'cash')
                ->where('category', 'PURCHASE')
                ->sum('amount');

            $totalManualBillPayments = Bill::where('school_id', $schoolId)
                ->where('status', 'PAID')
                ->where('payment_method', 'MANUAL')
                ->sum('amount');

            $totalCashCredits += $totalManualBillPayments + $totalCashSales;

            $totalCashDebits = WalletTransaction::where('school_id', $schoolId)
                ->where('payment_method', 'cash')
                ->where('category', 'WITHDRAWAL')
                ->where('type', 'DEBIT') // Withdrawals
                ->sum('amount');

            $totalConfirmedDeposits = AdminDeposit::where('school_id', $schoolId)
                ->where('status', 'confirmed')
                ->sum('amount');

            return $totalCashCredits - $totalCashDebits - $totalConfirmedDeposits;
        });
    }

    /**
     * Get total pending deposits for a school
     */
    public function getTotalPendingDeposits(string $schoolId): float
    {
        return AdminDeposit::where('school_id', $schoolId)
            ->where('status', 'pending')
            ->sum('amount');
    }

    /**
     * Get comprehensive financial summary for a school
     */
    public function getFinancialSummary(string $schoolId): array
    {
        $gatewayBalance = $this->getTotalGatewayBalance($schoolId);
        $cashOnHand = $this->getCurrentCashOnHand($schoolId);
        $pendingDeposits = $this->getTotalPendingDeposits($schoolId);

        return [
            'gateway_balance' => $gatewayBalance,
            'cash_on_hand' => $cashOnHand,
            'pending_deposits' => $pendingDeposits,
            'total_assets' => $gatewayBalance + $cashOnHand,
        ];
    }

    /**
     * Get total system revenue (all gateway + all cash credits)
     * Optionally filtered by school
     */
    public function getTotalSystemRevenue(?string $schoolId = null): float
    {
        $key = 'finance.total_system_revenue.' . ($schoolId ?? 'global');
        
        return Cache::remember($key, 600, function () use ($schoolId) {
            $totalTopups = WalletTransaction::query()
                ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
                ->where('category', 'TOPUP')
                ->where('type', 'CREDIT')
                ->sum('amount');

            $totalBills = Bill::query()
                ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
                ->where('status', 'PAID')
                ->sum('amount');

            return $totalTopups + $totalBills;
        });
    }

    /**
     * Get total cash currently held by local admins (not yet deposited)
     * This is a critical risk metric for Super Admin
     */
    public function getTotalCashAtAgents(?string $schoolId = null): float
    {
        // Risk metric, calculate freshly or short cache
        return Cache::remember('finance.risk.cash_at_agents.' . ($schoolId ?? 'global'), 120, function () use ($schoolId) {
            $cashCredits = WalletTransaction::query()
                ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
                ->where('payment_method', 'cash')
                ->where('type', 'CREDIT')
                ->sum('amount');

            $cashSales = WalletTransaction::query()
                ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
                ->where('payment_method', 'cash')
                ->where('category', 'PURCHASE')
                ->sum('amount');

            $cashDebits = WalletTransaction::query()
                ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
                ->where('payment_method', 'cash')
                ->where('category', 'WITHDRAWAL')
                ->where('type', 'DEBIT')
                ->sum('amount');

            $confirmedDeposits = AdminDeposit::query()
                ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
                ->where('status', 'confirmed')
                ->sum('amount');

            $manualBillRevenue = Bill::query()
                ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
                ->where('status', 'PAID')
                ->where('payment_method', 'MANUAL')
                ->sum('amount');

            return ($cashCredits + $manualBillRevenue + $cashSales) - $cashDebits - $confirmedDeposits;
        });
    }

    /**
     * Get count of pending deposits
     */
    public function getPendingDepositsCount(?string $schoolId = null): int
    {
        return AdminDeposit::query()
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->where('status', 'pending')
            ->count();
    }

    /**
     * Get query builder for pending deposits list with relations
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getPendingDepositsList(?string $schoolId = null)
    {
        return AdminDeposit::query()
            ->with(['school', 'admin'])
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->where('status', 'pending')
            ->orderBy('deposited_at', 'desc');
    }

    /**
     * Get comprehensive global statistics for Super Admin dashboard
     */
    public function getGlobalStats(?string $schoolId = null, $startDate = null, $endDate = null): array
    {
        // These calls now use the cached methods internally where possible
        
        $totalRevenue = $this->getTotalSystemRevenue($schoolId);

        $gatewayBalance = Cache::remember('finance.global.gateway.' . ($schoolId ?? 'global'), 300, function() use ($schoolId) {
             return WalletTransaction::query()
                ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
                ->gateway()
                ->where('type', 'CREDIT')
                ->sum('amount');
        });

        $confirmedDeposits = Cache::remember('finance.global.deposits.' . ($schoolId ?? 'global'), 300, function() use ($schoolId) {
             return AdminDeposit::query()
                ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
                ->where('status', 'confirmed')
                ->sum('amount');
        });

        $totalSafeBalance = $gatewayBalance + $confirmedDeposits;

        $cashAtAgents = $this->getTotalCashAtAgents($schoolId);
        $pendingCount = $this->getPendingDepositsCount($schoolId);
        
        // Pending amount uses builder which is cheap until executed
        $pendingAmount = AdminDeposit::query()
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->where('status', 'pending')
            ->sum('amount');

        return [
            'total_system_revenue' => $totalRevenue,
            'total_gateway_balance' => $totalSafeBalance, // Now includes confirmed deposits!
            'total_cash_at_agents' => $cashAtAgents,
            'pending_deposits_count' => $pendingCount,
            'pending_deposits_amount' => $pendingAmount,
            'cash_flow' => $this->getSchoolCashFlowStats($schoolId, $startDate, $endDate), // Using filter
        ];
    }

    /**
     * Get cash collected today for a school
     */
    public function getCashCollectedToday(string $schoolId): float
    {
        // Cache for 1-2 minutes. "Today" changes as new tx come in.
        return Cache::remember('finance.cash_today.' . $schoolId, 60, function () use ($schoolId) {
            $walletCash = WalletTransaction::where('school_id', $schoolId)
                ->where('payment_method', 'cash')
                ->whereIn('category', ['TOPUP', 'PURCHASE'])
                ->whereDate('created_at', today())
                ->sum('amount');

            $billCash = Bill::where('school_id', $schoolId)
                ->where('status', 'PAID')
                ->where('payment_method', 'MANUAL')
                ->whereDate('updated_at', today()) 
                ->sum('amount');

            return $walletCash + $billCash;
        });
    }

    /**
     * Get cash collected this month for a school
     */
    public function getCashCollectedThisMonth(string $schoolId): float
    {
        return Cache::remember('finance.cash_month.' . $schoolId, 300, function () use ($schoolId) {
            $walletCash = WalletTransaction::where('school_id', $schoolId)
                ->where('payment_method', 'cash')
                ->whereIn('category', ['TOPUP', 'PURCHASE'])
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->sum('amount');

            $billCash = Bill::where('school_id', $schoolId)
                ->where('status', 'PAID')
                ->where('payment_method', 'MANUAL')
                ->whereYear('updated_at', now()->year)
                ->whereMonth('updated_at', now()->month)
                ->sum('amount');

            return $walletCash + $billCash;
        });
    }

    /**
     * Get the last deposit record for a school
     */
    public function getLastDepositForSchool(string $schoolId): ?AdminDeposit
    {
        return AdminDeposit::where('school_id', $schoolId)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Get Detailed Cash Flow Stats (In, Out, Net)
     * Supports optional School ID for Global View
     */
    public function getSchoolCashFlowStats(?string $schoolId = null, $startDate = null, $endDate = null): array
    {
        // Generate cache key based on args
        $key = 'finance.cash_flow.' . ($schoolId ?? 'global') . '.' . ($startDate ?? 'x') . '.' . ($endDate ?? 'x');

        return Cache::remember($key, 600, function () use ($schoolId, $startDate, $endDate) {
            // Total Cash In (Topups)
            $totalCashIn = WalletTransaction::query()
                ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
                ->when($startDate && $endDate, fn ($q) => $q->whereBetween('created_at', [$startDate, $endDate]))
                ->where('payment_method', 'cash')
                ->whereIn('category', ['TOPUP', 'PURCHASE'])
                ->sum('amount');

            // Cash from Manual Bill Payments
            $totalBillCashIn = Bill::query()
                ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
                ->when($startDate && $endDate, fn ($q) => $q->whereBetween('updated_at', [$startDate, $endDate]))
                ->where('status', 'PAID')
                ->where('payment_method', 'MANUAL')
                ->sum('amount');

            $totalCashIn += $totalBillCashIn;

            // Total Cash Out (Withdrawals)
            $totalCashOut = WalletTransaction::query()
                ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
                ->when($startDate && $endDate, fn ($q) => $q->whereBetween('created_at', [$startDate, $endDate]))
                ->where('category', 'WITHDRAWAL')
                ->where('type', 'DEBIT')
                ->where('payment_method', 'cash')
                ->sum('amount');

            // Total Deposited to HQ (Reset)
            // Use AdminDeposit source to match the visual history
            $totalDeposited = AdminDeposit::query()
                ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
                ->when($startDate && $endDate, fn ($q) => $q->whereBetween('deposited_at', [$startDate, $endDate]))
                ->where('status', 'confirmed')
                ->sum('amount');

            // Cash currently held by ALL admins
            $currentCashOnHands = $totalCashIn - $totalCashOut - $totalDeposited;

            return [
                'total_cash_in' => $totalCashIn, // Arus Masuk
                'total_cash_out' => $totalCashOut, // Arus Keluar
                'total_deposited_hq' => $totalDeposited,
                'current_cash_on_hands' => $currentCashOnHands,
            ];
        });
    }

    public function getBillRevenueStats(?string $schoolId = null, $startDate = null, $endDate = null): float
    {
        $key = 'finance.bill_revenue.' . ($schoolId ?? 'global') . '.' . ($startDate ?? 'x') . '.' . ($endDate ?? 'x');
        
        return Cache::remember($key, 600, function () use ($schoolId, $startDate, $endDate) {
            return Bill::query()
                ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
                ->when($startDate && $endDate, fn ($q) => $q->whereBetween('updated_at', [$startDate, $endDate]))
                ->where('status', 'PAID')
                ->sum('amount');
        });
    }

    /**
     * Get total balance currently held in all student wallets for a school
     */
    public function getTotalStudentWalletBalance(?string $schoolId = null): float
    {
        // Liability can be cached for a bit
        return Cache::remember('finance.liability.students.' . ($schoolId ?? 'global'), 300, function () use ($schoolId) {
            return \App\Models\Student::query()
                ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
                ->sum('wallet_balance');
        });
    }

    /**
     * Get Leaderboard of Admins/Schools holding the most Cash
     * Returns collection sorted by cash_on_hand desc
     */
    public function getRiskLeaderboard(int $limit = 5)
    {
        // This might be heavy if there are thousands of schools.
        // For SaaS MVP, iterating schools is acceptable.
        
        return Cache::remember('finance.risk_leaderboard.' . $limit, 300, function () use ($limit) {
            $schools = \App\Models\School::all();
            
            $leaderboard = $schools->map(function ($school) {
                return [
                    'id' => $school->id,
                    'name' => $school->name,
                    'slug' => $school->slug,
                    'cash_on_hand' => $this->getCurrentCashOnHand($school->id),
                ];
            })->sortByDesc('cash_on_hand')->take($limit);
            
            return $leaderboard;
        });
    }

    /**
     * Get 6-month Trend of Digital vs Cash Transactions
     */
    public function getDigitalVsCashTrend(?string $schoolId = null)
    {
        // Expensive historical query -> Cache it!
        return Cache::remember('finance.trend.' . ($schoolId ?? 'global'), 3600, function () use ($schoolId) {
            // We want last 6 months
            $months = collect(range(0, 5))->map(function ($i) {
                $date = now()->subMonths($i);
                return [
                    'month' => $date->month,
                    'year' => $date->year,
                    'label' => $date->isoFormat('MMM Y'),
                    'sort' => $date->format('Ym')
                ];
            })->sortBy('sort')->values();

            $data = [
                'labels' => $months->pluck('label')->toArray(),
                'digital' => [],
                'cash' => []
            ];

            foreach ($months as $monthInfo) {
                $subStart = \Carbon\Carbon::create($monthInfo['year'], $monthInfo['month'], 1)->startOfMonth();
                $subEnd = $subStart->copy()->endOfMonth();

                // Cash: Wallet Topups (Cash) + Manual Bill Payments
                $cashTopups = WalletTransaction::query()
                    ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
                    ->whereBetween('created_at', [$subStart, $subEnd])
                    ->where('category', 'TOPUP')->where('type', 'CREDIT')->where('payment_method', 'cash')->sum('amount');

                $cashBills = Bill::query()
                    ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
                    ->whereBetween('updated_at', [$subStart, $subEnd])
                    ->where('status', 'PAID')->where('payment_method', 'MANUAL')->sum('amount');
                
                $data['cash'][] = $cashTopups + $cashBills;

                // Digital: Wallet Topups (Gateway) + Online Bill Payments
                $digitalTopups = WalletTransaction::query()
                    ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
                    ->whereBetween('created_at', [$subStart, $subEnd])
                    ->where('category', 'TOPUP')->where('type', 'CREDIT')->where('payment_method', 'gateway')->sum('amount');

                $digitalBills = Bill::query()
                    ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
                    ->whereBetween('updated_at', [$subStart, $subEnd])
                    ->where('status', 'PAID')
                    ->where(fn($q) => $q->where('payment_method', 'gateway')->orWhere('payment_method', 'GATEWAY')) // Handle case sensitivity
                    ->sum('amount');

                $data['digital'][] = $digitalTopups + $digitalBills;
            }

            return $data;
        });
    }
    /**
     * Process a purchase transaction for a student
     * 
     * @throws \Exception
     */
    public function processPurchase(\App\Models\Student $student, float $amount, string $pin, string $description = 'Koperasi Purchase'): WalletTransaction
    {
        // 1. Validate PIN
        if (! $student->wallet_pin) {
            throw new \Exception('PIN belum diatur. Harap hubungi administrator.');
        }

        if (!\Illuminate\Support\Facades\Hash::check($pin, $student->wallet_pin)) {
             throw new \Exception('PIN salah.');
        }

        // 2. Validate Balance
        if ($student->wallet_balance < $amount) {
            throw new \Exception('Saldo tidak mencukupi. Saldo saat ini: Rp ' . number_format($student->wallet_balance, 0, ',', '.'));
        }

        // 3. Database Transaction
        return \Illuminate\Support\Facades\DB::transaction(function () use ($student, $amount, $description) {
            // Deduct balance
            $student->wallet_balance -= $amount;
            $student->save();

            // Create Transaction Record
            return WalletTransaction::create([
                'school_id' => $student->school_id,
                'student_id' => $student->id,
                'user_id' => auth()->id(), // Admin/Operator who scanned
                'type' => 'DEBIT',
                'category' => WalletTransaction::CATEGORY_PURCHASE,
                'amount' => $amount,
                'payment_method' => 'pos', // System/POS
                'status' => 'SUCCESS',
                'description' => $description,
                'created_at' => now(),
            ]);
        });
    }
}

