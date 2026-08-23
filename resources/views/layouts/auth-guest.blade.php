<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Masuk') | el Craft</title>

    <!-- Self-hosted fonts: Inter + Material Symbols Outlined -->
    <link rel="preload" href="/fonts/inter-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/fonts/fonts.css">

    <!-- Vite assets (Tailwind CSS + Alpine.js) -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        [x-cloak] { display: none !important; }

        /* Auth page — full-viewport centered shell */
        .auth-shell {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #F5F0EE;
            padding: 24px 16px;
            box-sizing: border-box;
            margin: 0;
        }

        /* Single unified card */
        .auth-card {
            width: 100%;
            max-width: 380px;
            background: #fff;
            border-radius: 20px;
            border: 1px solid rgba(232, 180, 172, 0.4);
            padding: 28px 26px 26px;
            box-shadow: 0 4px 20px rgba(193, 123, 111, 0.06);
            box-sizing: border-box;
        }
    </style>
</head>
<body class="auth-shell font-sans antialiased">

    <div class="auth-card">
        @isset($slot)
            {{ $slot }}
        @else
            @yield('content')
        @endisset
    </div>

</body>
</html>
