<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Transaksi - {{ $transaction->invoice_code }}</title>
    <style>
        @page {
            margin: 0;
            size: 58mm auto; /* 58mm is the more compact standard */
        }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 11px; /* Slightly smaller font */
            line-height: 1.2;
            width: 50mm; /* Printable width for 58mm paper */
            margin: 0 auto;
            padding: 5px 1mm;
            color: #000;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }
        .dashed-line {
            border-top: 1px dashed #000;
            margin: 5px 0;
        }
        .header {
            margin-bottom: 10px;
        }
        .header .school-name {
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .info {
            font-size: 11px;
            margin-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        .footer {
            margin-top: 15px;
            font-size: 10px;
        }
        @media print {
            .no-print { display: none; }
        }
        .print-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #000;
            color: #fff;
            border: none;
            cursor: pointer;
            font-family: sans-serif;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="header text-center">
        <div class="school-name">{{ $transaction->school->name }}</div>
        <div>{{ $transaction->school->address }}</div>
        @if($transaction->school->phone)
            <div>Telp: {{ $transaction->school->phone }}</div>
        @endif
        <div class="bold" style="margin-top: 5px;">STRUK TOP UP SALDO</div>
    </div>

    <div class="info">
        <div class="dashed-line"></div>
        <table>
            <tr>
                <td width="40%">No. Invoice</td>
                <td>: {{ $transaction->invoice_code }}</td>
            </tr>
            <tr>
                <td>Tanggal</td>
                <td>: {{ $transaction->created_at->format('d/m/Y H:i') }}</td>
            </tr>
            <tr>
                <td>Santri</td>
                <td>: {{ $transaction->student->full_name }}</td>
            </tr>
            <tr>
                <td>Petugas</td>
                <td>: {{ $transaction->operator->name }}</td>
            </tr>
        </table>
        <div class="dashed-line"></div>
    </div>

    <table class="totals">
        @php
            $schoolAdminFee = $transaction->admin_fee * 2;
            $totalReceived = $transaction->amount + $schoolAdminFee;
        @endphp
        <tr>
            <td>Nominal Top Up</td>
            <td class="text-right">{{ number_format($transaction->amount, 0, ',', '.') }}</td>
        </tr>
        @if($schoolAdminFee > 0)
        <tr>
            <td>Biaya Admin</td>
            <td class="text-right">{{ number_format($schoolAdminFee, 0, ',', '.') }}</td>
        </tr>
        @endif
        <tr class="bold">
            <td>TOTAL TERIMA</td>
            <td class="text-right">Rp {{ number_format($totalReceived, 0, ',', '.') }}</td>
        </tr>
    </table>

    <div class="footer text-center">
        <div class="dashed-line"></div>
        <p>Saldo Akhir Dompet:</p>
        <p class="bold" style="font-size: 14px;">Rp {{ number_format($transaction->student->wallet_balance, 0, ',', '.') }}</p>
        <div class="dashed-line"></div>
        <p>Terima Kasih</p>
        <p>Simpan struk ini sebagai bukti pembayaran yang sah</p>
    </div>

    <button class="print-btn no-print" onclick="window.print()">Cetak (Ctrl+P)</button>

    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        }
    </script>
</body>
</html>
