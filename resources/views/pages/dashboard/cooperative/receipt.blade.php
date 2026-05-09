<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Pembelian - {{ $transaction->invoice_code }}</title>
    <style>
        @page {
            margin: 0;
            size: 80mm 200mm; /* Standard thermal paper size */
        }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            line-height: 1.2;
            width: 72mm; /* Printable width */
            margin: 0 auto;
            padding: 10px 2mm;
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
        .items td {
            padding: 2px 0;
            vertical-align: top;
        }
        .total-row td {
            padding-top: 5px;
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
        <div class="bold" style="margin-top: 5px;">STRUK PEMBELIAN KOPERASI</div>
    </div>

    <div class="info">
        <div class="dashed-line"></div>
        <table>
            <tr>
                <td>No. Invoice</td>
                <td>: {{ $transaction->invoice_code }}</td>
            </tr>
            <tr>
                <td>Tanggal</td>
                <td>: {{ $transaction->created_at->format('d/m/Y H:i') }}</td>
            </tr>
            <tr>
                <td>Pelanggan</td>
                <td>: {{ $transaction->student->full_name ?? 'Pelanggan Umum' }}</td>
            </tr>
            <tr>
                <td>Kasir</td>
                <td>: {{ $transaction->operator->name }}</td>
            </tr>
        </table>
        <div class="dashed-line"></div>
    </div>

    <table class="items">
        @foreach($transaction->items as $item)
            <tr>
                <td colspan="2">{{ $item->product_name }}</td>
            </tr>
            <tr>
                <td style="padding-left: 5px;">{{ $item->quantity }} x {{ number_format($item->price, 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
            </tr>
        @endforeach
    </table>

    <div class="dashed-line"></div>

    <table class="totals">
        <tr class="bold">
            <td>TOTAL</td>
            <td class="text-right">Rp {{ number_format($transaction->amount, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Metode Bayar</td>
            <td class="text-right uppercase">{{ $transaction->payment_method === 'wallet' ? 'Dompet' : 'Tunai' }}</td>
        </tr>
    </table>

    <div class="footer text-center">
        <div class="dashed-line"></div>
        <p>Terima Kasih Atas Kunjungan Anda</p>
        <p>Barang yang sudah dibeli tidak dapat ditukar/dikembalikan</p>
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
