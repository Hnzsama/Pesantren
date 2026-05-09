<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} - {{ $dateString }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #000;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        h2 {
            margin: 0 0 5px 0;
            text-transform: uppercase;
            font-size: 16px;
        }
        .period {
            font-size: 13px;
            margin: 0;
        }
        .info {
            margin-bottom: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #000;
            padding: 4px 6px;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
            text-align: center;
        }
        .text-center { text-align: center; }
        .text-bold { font-weight: bold; }
        .uppercase { text-transform: uppercase; }
        
        .status-present { color: green; font-weight: bold; }
        .status-permission { color: orange; font-weight: bold; }
        .status-absent { color: red; font-weight: bold; }

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
        
        @media print {
            body { padding: 0; }
            @page { 
                margin: 1cm; 
                size: A4 {{ count($activities) > 5 ? 'landscape' : 'portrait' }}; 
            }
            .print-btn { display: none; }
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
            font-weight: bold;
        }
    </style>
</head>
<body>

    <div class="header">
        <h2>{{ $title }}</h2>
        <p class="period">{{ $dateString }}</p>
    </div>

    <div class="info">
        <table>
            <tr style="border:none">
                <td style="border:none; width: 80px;">Jenjang/Kelas</td>
                <td style="border:none; width: 10px;">:</td>
                <td style="border:none;"><strong>{{ $level->name ?? 'Semua Kelas' }}</strong></td>
            </tr>
        </table>
    </div>

    <table>
        <thead>
            <tr>
                <th width="30">No.</th>
                <th width="150">Nama Santri</th>
                @foreach($activities as $activity)
                    <th>{{ $activity->name }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($students as $index => $student)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>
                    <div class="text-bold">{{ $student->full_name }}</div>
                    <div style="font-size: 9px; color: #666;">NIS: {{ $student->nis }}</div>
                </td>
                @foreach($activities as $activity)
                    @php
                        $status = $matrix[$student->id][$activity->id] ?? 'absent';
                        $display = match($status) {
                            'present' => 'H',
                            'permission' => 'I',
                            'sick' => 'S',
                            default => 'A'
                        };
                        $class = match($status) {
                            'present' => 'status-present',
                            'permission' => 'status-permission',
                            'sick' => 'status-permission',
                            default => 'status-absent'
                        };
                    @endphp
                    <td class="text-center {{ $class }}">{{ $display }}</td>
                @endforeach
            </tr>
            @endforeach
        </tbody>
    </table>

    <div style="margin-top: 10px; font-size: 9px;">
        <strong>Keterangan:</strong> H = Hadir, I = Izin, S = Sakit, A = Alpha (Tanpa Keterangan)
    </div>

    <div class="signatures">
        <table style="width: 100%;">
            <tr>
                <td style="width: 33%;">Mengetahui,</td>
                <td style="width: 33%;"></td>
                <td style="width: 33%;">Dicetak pada, {{ now()->translatedFormat('d F Y H:i') }}</td>
            </tr>
            <tr>
                <td style="height: 60px;"></td>
                <td></td>
                <td>Pencatat,</td>
            </tr>
            <tr>
                <td>( .................................... )</td>
                <td></td>
                <td>( {{ auth()->user()->name }} )</td>
            </tr>
        </table>
    </div>

    <button class="print-btn" onclick="window.print()">CETAK LAPORAN</button>

    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        }
    </script>
</body>
</html>
