<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\WalletTransaction;

class WalletTransactionPrintController extends Controller
{
    public function printReceipt($invoiceCode)
    {
        $transaction = WalletTransaction::with(['student', 'operator', 'school'])
            ->where('invoice_code', $invoiceCode)
            ->firstOrFail();

        // If it's a Top Up, use the regular thermal receipt
        if ($transaction->category === 'TOPUP') {
            return view('pages.wallet-transactions.receipt', compact('transaction'));
        }

        // For other manual transactions (Bills), use the professional PDF kuitansi
        $html = view('pages.wallet-transactions.receipt-pdf', compact('transaction'))->render();

        $pdf = \Spatie\Browsershot\Browsershot::html($html)
            ->setIncludePath('/home/akuma/.nvm/versions/node/v24.13.1/bin')
            ->setOption('args', ['--no-sandbox', '--disable-setuid-sandbox'])
            ->format('A5')
            ->landscape()
            ->pdf();

        return response($pdf)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="kuitansi-' . $invoiceCode . '.pdf"');
    }
}
