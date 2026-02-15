<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kartu Santri - {{ $card->student->full_name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Courier+Prime:wght@700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body {
            margin: 0; padding: 0;
            width: 856px; height: 540px;
            overflow: hidden;
            background-color: transparent;
        }

        @if(request()->has('preview'))
            body {
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                width: 100vw;
                height: 100vh;
                margin: 0;
                background-color: transparent;
            }
            .card-container {
                transform: scale(min(calc((100vw - 20px) / 856), calc((100vh - 20px) / 540)));
            }
        @endif
    </style>
</head>
<body>
    <x-student-card 
        :card="$card" 
        :logoBase64="$logoBase64 ?? null" 
        :qrCodeSvg="$qrCodeSvg ?? null"
        :noBackground="$noBackground ?? false"
        :side="$side ?? 'front'"
    />
</body>
</html>
