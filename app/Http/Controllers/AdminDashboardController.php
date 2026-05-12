<?php

namespace App\Http\Controllers;

use App\Models\AdminDeposit;
use App\Services\FinancialService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AdminDashboardController extends Controller
{
    public function __construct(
        protected FinancialService $financialService
    ) {}

    /**
     * Display the Local Admin financial dashboard
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $schoolId = $user->school_id;

        // Date Filter Logic
        $period = $request->input('period', 'this_month'); // Default to this month
        $startDate = null;
        $endDate = null;

        switch ($period) {
            case 'today':
                $startDate = now()->startOfDay();
                $endDate = now()->endOfDay();
                break;
            case 'this_month':
                $startDate = now()->startOfMonth();
                $endDate = now()->endOfMonth();
                break;
            case 'this_year':
                $startDate = now()->startOfYear();
                $endDate = now()->endOfYear();
                break;
            case 'custom':
                if ($request->filled('start_date') && $request->filled('end_date')) {
                    $startDate = \Carbon\Carbon::parse($request->input('start_date'))->startOfDay();
                    $endDate = \Carbon\Carbon::parse($request->input('end_date'))->endOfDay();
                }
                break;
            case 'all_time':
            default:
                // No filters
                break;
        }

        // Financial Overview Statistics
        $stats = [
            'cash_today' => $this->financialService->getCashCollectedToday($schoolId),
            'cash_this_month' => $this->financialService->getCashCollectedThisMonth($schoolId),
            'admin_wallet' => $this->financialService->getCurrentCashOnHand($schoolId), // Balance is always NOW
            'gateway_balance' => $this->financialService->getTotalGatewayBalance($schoolId), // Balance is always NOW
            'total_balance' => $this->financialService->getCurrentCashOnHand($schoolId) + $this->financialService->getTotalGatewayBalance($schoolId),
            'last_deposit' => $this->financialService->getLastDepositForSchool($schoolId),
            // Filtered Stats
            'cash_flow' => $this->financialService->getSchoolCashFlowStats($schoolId, $startDate, $endDate),
            'total_bill_revenue' => $this->financialService->getBillRevenueStats($schoolId, $startDate, $endDate),
            // Keeping monthly stat for comparison or remove? User wants Filtered.
            // Let's keep specific Month revenue as a secondary stat if needed, but for the MAIN card let's use the Filtered one.
            'month_bill_revenue' => $this->financialService->getBillRevenueStats($schoolId, now()->startOfMonth(), now()->endOfMonth()),
        ];

        // Deposit History
        $deposits = AdminDeposit::where('school_id', $schoolId)
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('admin.dashboard', compact('stats', 'deposits', 'period', 'startDate', 'endDate'));
    }

    /**
     * Store a new cash deposit
     */
    public function storeDeposit(Request $request)
    {
        $user = auth()->user();
        $schoolId = $user->school_id;

        // Get current admin wallet balance
        $currentBalance = $this->financialService->getCurrentCashOnHand($schoolId);

        // Validate the request
        $validated = $request->validate([
            'amount' => [
                'required',
                'numeric',
                'min:1',
                function ($attribute, $value, $fail) use ($currentBalance) {
                    if ($value > $currentBalance) {
                        $fail('Jumlah melebihi saldo Dompet Admin Anda (Rp ' . number_format($currentBalance, 0, ',', '.') . ')');
                    }
                },
            ],
            'proof_image' => 'nullable|image|max:2048',
            'notes' => 'nullable|string|max:500',
        ], [
            'amount.required' => 'Jumlah setoran harus diisi',
            'amount.numeric' => 'Jumlah harus berupa angka',
            'amount.min' => 'Jumlah minimal Rp 1',
            'proof_image.image' => 'File harus berupa gambar',
            'proof_image.max' => 'Ukuran gambar maksimal 2MB',
        ]);

        // Handle image upload
        $proofPath = null;
        if ($request->hasFile('proof_image')) {
            $proofPath = $request->file('proof_image')->store('deposits', 'public');
        }

        // Create deposit record
        $deposit = AdminDeposit::create([
            'school_id' => $schoolId,
            'user_id' => $user->id,
            'amount' => $validated['amount'],
            'proof_image' => $proofPath,
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending',
            'deposited_at' => now(),
        ]);

        return redirect()
            ->route('admin.dashboard')
            ->with('success', 'Setoran berhasil diajukan! Menunggu persetujuan Super Admin.');
    }
}
