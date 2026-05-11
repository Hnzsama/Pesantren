<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WalletTransactionExportController extends Controller
{
    public function export(Request $request)
    {
        $category = $request->query('category');
        $schoolId = auth()->user()->school_id;

        $query = WalletTransaction::with(['student'])
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->when($category, fn($q) => $q->where('category', $category))
            ->orderBy('created_at', 'desc');

        $filename = 'transaksi-dompet-' . ($category ?: 'semua') . '-' . now()->format('YmdHis') . '.csv';

        $response = new StreamedResponse(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // Add UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Header
            fputcsv($handle, [
                'Tanggal',
                'No. Invoice',
                'Nama Santri',
                'NIS',
                'Kategori',
                'Tipe',
                'Nominal',
                'Biaya Admin',
                'Biaya PG',
                'Total Transaksi',
                'Metode Pembayaran',
                'Status',
                'Keterangan'
            ]);

            $query->chunk(100, function ($transactions) use ($handle) {
                foreach ($transactions as $txn) {
                    fputcsv($handle, [
                        $txn->created_at->format('d/m/Y H:i'),
                        $txn->invoice_code,
                        $txn->student?->full_name ?? '-',
                        $txn->student?->nis ?? '-',
                        $txn->category,
                        $txn->type === 'CREDIT' ? 'Kredit (Masuk)' : 'Debit (Keluar)',
                        $txn->amount,
                        $txn->admin_fee,
                        $txn->payment_fee,
                        $txn->amount + $txn->admin_fee + $txn->payment_fee,
                        $txn->payment_method === 'cash' ? 'Tunai' : 'Gateway',
                        $txn->status,
                        $txn->description
                    ]);
                }
            });

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);

        return $response;
    }

    public function report(Request $request)
    {
        $category = $request->query('category');
        $schoolId = auth()->user()->school_id;

        $query = WalletTransaction::with(['student'])
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->when($category, fn($q) => $q->where('category', $category))
            ->where('status', 'PAID') // Only paid transactions for reports
            ->orderBy('created_at', 'asc');

        $transactions = $query->get();

        $categoryLabel = match ($category) {
            'TOPUP' => 'Top Up',
            'WITHDRAWAL' => 'Penarikan',
            'PURCHASE' => 'Pembelian',
            'DEPOSIT_TO_HQ' => 'Setor ke Pusat',
            default => 'Semua Kategori'
        };

        $dateString = now()->format('d M Y');

        return view('pages.wallet-transactions.print-report', compact('transactions', 'categoryLabel', 'dateString'));
    }
}
