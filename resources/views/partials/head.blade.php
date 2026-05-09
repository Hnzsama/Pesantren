<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $title ?? config('app.name') }}</title>

<!-- Standard SEO Meta Tags -->
<meta name="description" content="{{ $description ?? 'Platform Manajemen Pesantren Terintegrasi' }}" />
<meta name="keywords" content="{{ $keywords ?? 'pesantren, manajemen, pendidikan, islam, santri' }}" />
<meta name="author" content="{{ config('app.name') }}" />
<meta name="robots" content="index, follow" />
<meta name="theme-color" content="#ffffff" />

<!-- Open Graph / Facebook / WhatsApp -->
<meta property="og:type" content="website" />
<meta property="og:url" content="{{ url()->current() }}" />
<meta property="og:title" content="{{ $title ?? config('app.name') }}" />
<meta property="og:description" content="{{ $description ?? 'Platform Manajemen Pesantren Terintegrasi' }}" />
<meta property="og:image" content="{{ asset('apple-touch-icon.png') }}" />
<meta property="og:site_name" content="{{ config('app.name') }}" />
<meta property="og:locale" content="id_ID" />

<!-- Twitter / X -->
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:url" content="{{ url()->current() }}" />
<meta name="twitter:title" content="{{ $title ?? config('app.name') }}" />
<meta name="twitter:description" content="{{ $description ?? 'Platform Manajemen Pesantren Terintegrasi' }}" />
<meta name="twitter:image" content="{{ asset('apple-touch-icon.png') }}" />

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance