<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\WalletTransaction;
use App\Models\WalletTransactionItem;
use Carbon\Carbon;

class CooperativeReportController extends Controller
{
    public function export(Request $request)
    {
        $filter = $request->query('filter', 'today');
        $from = $request->query('from');
        $to = $request->query('to');

        $query = WalletTransactionItem::with(['transaction.operator', 'product'])
            ->whereHas('transaction', function ($q) {
                $q->where('school_id', auth()->user()->school_id)
                  ->where('category', 'PURCHASE')
                  ->where('operator_id', auth()->id()) // Only transactions processed by the logged-in admin
                  ->where('status', 'PAID');
            });

        $dateString = '';

        switch ($filter) {
            case 'today':
                $query->whereDate('created_at', Carbon::today());
                $dateString = Carbon::today()->format('d M Y');
                break;
            case 'week':
                $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                $dateString = Carbon::now()->startOfWeek()->format('d M Y') . ' - ' . Carbon::now()->endOfWeek()->format('d M Y');
                break;
            case 'month':
                $query->whereMonth('created_at', Carbon::now()->month)
                      ->whereYear('created_at', Carbon::now()->year);
                $dateString = Carbon::now()->format('F Y');
                break;
            case 'year':
                $query->whereYear('created_at', Carbon::now()->year);
                $dateString = 'Tahun ' . Carbon::now()->year;
                break;
            case 'custom':
                if ($from && $to) {
                    $query->whereDate('created_at', '>=', $from)
                          ->whereDate('created_at', '<=', $to);
                    $dateString = Carbon::parse($from)->format('d M Y') . ' - ' . Carbon::parse($to)->format('d M Y');
                }
                break;
        }

        $items = $query->orderBy('created_at', 'asc')->get();

        $reportData = [];
        $totalCost = 0;
        $totalSales = 0;
        $totalProfit = 0;
        
        $cashSales = 0;
        $cashProfit = 0;
        $nonCashSales = 0;
        $nonCashProfit = 0;

        foreach ($items as $item) {
            $purchasePrice = $item->product ? $item->product->purchase_price : 0;
            $sellingPrice = $item->price;
            
            $itemCost = $purchasePrice * $item->quantity;
            $itemSales = $sellingPrice * $item->quantity;
            $itemProfit = $itemSales - $itemCost;

            $reportData[] = [
                'tanggal' => $item->created_at->format('d/m/Y'),
                'nama_barang' => $item->product_name,
                'banyak' => $item->quantity,
                'harga_beli_total' => $itemCost,
                'harga_jual_total' => $itemSales,
                'diskon' => 0,
                'laba' => $itemProfit,
            ];

            $totalCost += $itemCost;
            $totalSales += $itemSales;
            $totalProfit += $itemProfit;

            if ($item->transaction && $item->transaction->payment_method === 'cash') {
                $cashSales += $itemSales;
                $cashProfit += $itemProfit;
            } else {
                $nonCashSales += $itemSales;
                $nonCashProfit += $itemProfit;
            }
        }

        $totals = [
            'cost' => $totalCost,
            'sales' => $totalSales,
            'discount' => 0,
            'profit' => $totalProfit,
            'cash_sales' => $cashSales,
            'cash_profit' => $cashProfit,
            'non_cash_sales' => $nonCashSales,
            'non_cash_profit' => $nonCashProfit,
        ];

        return view('pages.dashboard.cooperative.print-report', compact('reportData', 'totals', 'dateString'));
    }

    public function printReceipt($invoiceCode)
    {
        $transaction = WalletTransaction::with(['items', 'student', 'operator', 'school'])
            ->where('invoice_code', $invoiceCode)
            ->firstOrFail();

        return view('pages.dashboard.cooperative.receipt', compact('transaction'));
    }
}
