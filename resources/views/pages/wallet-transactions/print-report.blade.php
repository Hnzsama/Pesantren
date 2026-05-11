<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Transaksi Dompet - {{ $categoryLabel }} - {{ $dateString }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
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
            font-size: 11px;
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
        .font-mono { font-family: monospace; }
        
        .summary-row td {
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            padding-top: 10px;
            padding-bottom: 10px;
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

        .badge {
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
            border: 1px solid #ccc;
        }
    </style>
</head>
<body>

    <h2>Laporan Transaksi Dompet</h2>
    <p class="period">Kategori: {{ $categoryLabel }} | Tanggal Cetak: {{ $dateString }}</p>

    <table>
        <thead>
            <tr>
                <th width="30">No.</th>
                <th width="100">Tanggal</th>
                <th width="100">Invoice</th>
                <th>Nama Santri</th>
                <th width="80">Kategori</th>
                <th width="60">Metode</th>
                <th width="100">Masuk (Kredit)</th>
                <th width="100">Keluar (Debit)</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @php 
                $totalCredit = 0;
                $totalDebit = 0;
            @endphp
            @foreach($transactions as $index => $txn)
            @php
                if ($txn->type === 'CREDIT') $totalCredit += $txn->amount;
                else $totalDebit += $txn->amount;
            @endphp
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td class="text-center">{{ $txn->created_at->format('d/m/Y H:i') }}</td>
                <td class="text-center font-mono">{{ $txn->invoice_code }}</td>
                <td class="uppercase">{{ $txn->student?->full_name ?? '-' }}</td>
                <td class="text-center">
                    {{ match ($txn->category) {
                        'TOPUP' => 'Top Up',
                        'WITHDRAWAL' => 'Penarikan',
                        'PURCHASE' => 'Pembelian',
                        'DEPOSIT_TO_HQ' => 'Setor HQ',
                        default => $txn->category
                    } }}
                </td>
                <td class="text-center">
                    {{ $txn->payment_method === 'cash' ? 'Tunai' : 'Digital' }}
                </td>
                <td class="text-right">
                    {{ $txn->type === 'CREDIT' ? number_format($txn->amount, 0, ',', '.') : '-' }}
                </td>
                <td class="text-right">
                    {{ $txn->type === 'DEBIT' ? number_format($txn->amount, 0, ',', '.') : '-' }}
                </td>
                <td>{{ $txn->description }}</td>
            </tr>
            @endforeach

            <!-- Total Row -->
            <tr class="summary-row text-bold">
                <td colspan="6" class="text-center">TOTAL</td>
                <td class="text-right">Rp {{ number_format($totalCredit, 0, ',', '.') }}</td>
                <td class="text-right">Rp {{ number_format($totalDebit, 0, ',', '.') }}</td>
                <td class="bg-zinc-100"></td>
            </tr>
            <tr class="text-bold">
                <td colspan="6" class="text-center">SALDO AKHIR (NET)</td>
                <td colspan="2" class="text-center" style="font-size: 14px;">
                    Rp {{ number_format($totalCredit - $totalDebit, 0, ',', '.') }}
                </td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <div class="signatures">
        <table>
            <tr>
                <td width="70%"></td>
                <td width="30%">Dicetak Oleh,</td>
            </tr>
            <tr>
                <td height="80"></td>
                <td></td>
            </tr>
            <tr>
                <td></td>
                <td>( {{ auth()->user()->name }} )</td>
            </tr>
        </table>
    </div>

    <button class="print-btn" onclick="window.print()">Cetak Laporan (Ctrl+P)</button>

    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        }
    </script>
</body>
</html>
