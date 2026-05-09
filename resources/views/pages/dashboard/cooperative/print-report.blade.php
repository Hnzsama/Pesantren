<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Penjualan Koperasi - {{ $dateString }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #000;
            margin: 0;
            padding: 20px;
        }
        h2 {
            text-align: center;
            margin-bottom: 5px;
            font-size: 16px;
            text-transform: uppercase;
        }
        p.period {
            text-align: center;
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #000;
            padding: 6px 8px;
            text-align: left;
            vertical-align: middle;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
            text-align: center;
            white-space: nowrap;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-bold { font-weight: bold; }
        .uppercase { text-transform: uppercase; }
        
        .no-border-left { border-left: none !important; }
        .no-border-right { border-right: none !important; }
        .no-border-top { border-top: none !important; }
        .no-border-bottom { border-bottom: none !important; }
        
        .summary-row td {
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            padding-top: 10px;
            padding-bottom: 10px;
        }

        .payment-methods td {
            border: none;
            padding: 2px 8px;
        }

        .signatures {
            margin-top: 50px;
            width: 100%;
        }
        .signatures table {
            border: none;
        }
        .signatures th, .signatures td {
            border: none;
            text-align: center;
        }
        
        /* Print Styles */
        @media print {
            body { padding: 0; }
            @page { margin: 1cm; size: landscape; }
            button.print-btn { display: none; }
        }

        .print-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>

    <h2>Laporan Penjualan Koperasi</h2>
    <p class="period">Periode: {{ $dateString }}</p>

    <table>
        <thead>
            <tr>
                <th width="30">No.</th>
                <th width="80">Tanggal</th>
                <th>Nama Barang</th>
                <th width="50">Banyak</th>
                <th width="100">Harga Beli<br>x Banyak</th>
                <th width="100">Harga Jual<br>x Banyak</th>
                <th width="80">Diskon</th>
                <th width="100">Laba Penjualan</th>
            </tr>
        </thead>
        <tbody>
            @foreach($reportData as $index => $row)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td class="text-center">{{ $row['tanggal'] }}</td>
                <td class="uppercase">{{ $row['nama_barang'] }}</td>
                <td class="text-center">{{ $row['banyak'] }}</td>
                <td class="text-right">{{ number_format($row['harga_beli_total'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($row['harga_jual_total'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($row['diskon'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($row['laba'], 0, ',', '.') }}</td>
            </tr>
            @endforeach

            <!-- Total Row -->
            <tr class="summary-row text-bold">
                <td colspan="4" class="text-center">Total</td>
                <td class="text-right">{{ number_format($totals['cost'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($totals['sales'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($totals['discount'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($totals['profit'], 0, ',', '.') }}</td>
            </tr>
            
            <!-- Payment Methods: Tunai -->
            <tr class="payment-methods text-bold">
                <td colspan="4" class="text-left" style="padding-left: 20%;">Tunai</td>
                <td class="text-right">-</td>
                <td class="text-right">{{ number_format($totals['cash_sales'], 0, ',', '.') }}</td>
                <td class="text-right">-</td>
                <td class="text-right">{{ number_format($totals['cash_profit'], 0, ',', '.') }}</td>
            </tr>
            
            <!-- Payment Methods: Non-Tunai -->
            <tr class="payment-methods text-bold" style="border-bottom: 2px solid #000;">
                <td colspan="4" class="text-left" style="padding-left: 20%; padding-bottom: 10px;">Non-Tunai</td>
                <td class="text-right" style="padding-bottom: 10px;">-</td>
                <td class="text-right" style="padding-bottom: 10px;">{{ number_format($totals['non_cash_sales'], 0, ',', '.') }}</td>
                <td class="text-right" style="padding-bottom: 10px;">-</td>
                <td class="text-right" style="padding-bottom: 10px;">{{ number_format($totals['non_cash_profit'], 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <div class="signatures">
        <table>
            <tr>
                <td width="70%"></td>
                <td width="15%">Mengetahui,</td>
                <td width="15%">Kasir,</td>
            </tr>
            <tr>
                <td height="80"></td>
                <td></td>
                <td></td>
            </tr>
            <tr>
                <td></td>
                <td>( .................................... )</td>
                <td>( {{ auth()->user()->name }} )</td>
            </tr>
        </table>
    </div>

    <button class="print-btn" onclick="window.print()">Cetak Laporan (Ctrl+P)</button>

    <script>
        // Auto-trigger print dialog when opened
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        }
    </script>
</body>
</html>
