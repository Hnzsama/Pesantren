<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kwitansi - {{ $transaction->invoice_code }}</title>
    <style>
        @page {
            size: A5 landscape;
            margin: 0;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            margin: 0;
            padding: 20px 30px;
            color: #333;
            line-height: 1.4;
            background: #fff;
        }
        .container {
            width: 100%;
            position: relative;
            height: 100%;
        }
        .header {
            display: flex;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        .logo-container {
            width: 80px;
            height: 80px;
            margin-right: 20px;
        }
        .logo {
            width: 100%;
            height: auto;
        }
        .title-container {
            flex-grow: 1;
            text-align: center;
        }
        .title {
            font-size: 24px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-top: 5px;
        }
        .invoice-info {
            margin-top: 5px;
            font-size: 12px;
        }
        .content {
            margin-top: 15px;
            font-size: 14px;
        }
        .content-row {
            display: flex;
            margin-bottom: 10px;
        }
        .label {
            width: 140px;
            flex-shrink: 0;
        }
        .separator {
            width: 20px;
            flex-shrink: 0;
        }
        .value {
            flex-grow: 1;
            border-bottom: 1px dotted #ccc;
            min-height: 20px;
            padding-bottom: 1px;
            font-weight: 500;
        }
        .terbilang-box {
            font-style: italic;
            background: #f9f9f9;
            padding: 3px 8px;
            border-radius: 4px;
            display: inline-block;
            margin-top: -3px;
        }
        .footer {
            margin-top: 25px;
            display: flex;
            justify-content: flex-end;
        }
        .signature-area {
            text-align: center;
            width: 220px;
        }
        .date {
            margin-bottom: 10px;
            font-size: 13px;
        }
        .signer-label {
            margin-bottom: 50px;
            font-size: 13px;
        }
        .signer-name {
            font-weight: bold;
            text-decoration: underline;
            font-size: 14px;
        }
        .nominal-box-container {
            position: absolute;
            bottom: 5px;
            left: 0;
        }
        .nominal-box {
            background: #fff;
            border: 2px solid #333;
            padding: 8px 25px;
            font-size: 20px;
            font-weight: 900;
            font-style: italic;
            display: inline-block;
            transform: skew(-15deg);
        }
        .nominal-text {
            transform: skew(15deg);
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo-container">
                @if($transaction->school->logo_path)
                    <img src="{{ storage_path('app/public/' . $transaction->school->logo_path) }}" class="logo">
                @else
                    <div style="width: 80px; height: 80px; background: #eee; border-radius: 50%;"></div>
                @endif
            </div>
            <div class="title-container">
                <div class="title">KUITANSI</div>
            </div>
        </div>

        <div class="invoice-info">
            <div class="content-row" style="margin-bottom: 20px;">
                <div class="label" style="width: auto;">NO. {{ $transaction->invoice_code }}</div>
            </div>
        </div>

        @php
            // The amount received from parent is Nominal + (Platform Fee * 2)
            // This applies to both TOPUP and MANUAL BILL payments that have an admin fee
            $isTopup = str_starts_with($transaction->invoice_code, 'TOPUP-');
            $schoolAdminFee = ($transaction->admin_fee > 0) ? ($transaction->admin_fee * 2) : 0;
            $totalReceived = $transaction->amount + $schoolAdminFee;
        @endphp

        <div class="content">
            <div class="content-row">
                <div class="label">Sudah Terima Dari</div>
                <div class="separator">:</div>
                <div class="value">{{ strtoupper($transaction->student->full_name) }}</div>
            </div>
            
            @if($schoolAdminFee > 0)
            @if($isTopup)
            <div class="content-row">
                <div class="label">Nominal Top Up</div>
                <div class="separator">:</div>
                <div class="value">Rp {{ number_format($transaction->amount, 0, ',', '.') }}</div>
            </div>
            @else
            <div class="content-row">
                <div class="label">Nominal Tagihan</div>
                <div class="separator">:</div>
                <div class="value">Rp {{ number_format($transaction->amount, 0, ',', '.') }}</div>
            </div>
            @endif
            <div class="content-row">
                <div class="label">Biaya Admin</div>
                <div class="separator">:</div>
                <div class="value">Rp {{ number_format($schoolAdminFee, 0, ',', '.') }}</div>
            </div>
            @endif

            <div class="content-row">
                <div class="label">Sejumlah Uang</div>
                <div class="separator">:</div>
                <div class="value">
                    <span class="terbilang-box">
                        {{ \App\Helpers\TerbilangHelper::make($totalReceived) }} Rupiah
                    </span>
                </div>
            </div>
            <div class="content-row">
                <div class="label">Untuk Pembayaran</div>
                <div class="separator">:</div>
                <div class="value">
                    {{ $transaction->description ?: ($isTopup ? 'Top Up Saldo Manual' : 'Pembayaran Tagihan Manual') }}
                </div>
            </div>
        </div>

        <div class="footer">
            <div class="signature-area">
                <div class="date">
                    {{ $transaction->school->address ? explode(',', $transaction->school->address)[0] : 'Jayapura' }}, 
                    {{ $transaction->created_at->translatedFormat('d F Y') }}
                </div>
                <div class="signer-label">Yang Menerima</div>
                <div class="signer-name">{{ $transaction->operator->name }}</div>
            </div>
        </div>

        <div class="nominal-box-container">
            <div class="nominal-box">
                <span class="nominal-text">Rp {{ number_format($totalReceived, 0, ',', '.') }}</span>
            </div>
        </div>
    </div>
</body>
</html>
