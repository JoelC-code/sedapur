<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#4c610d">
<meta name="description" content="Kelola kulkas bersama, habiskan bahan sebelum kedaluwarsa, dan lihat berapa makanan serta uang yang terbuang.">
<title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>

<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<link rel="icon" href="{{ asset('icon.svg') }}" type="image/svg+xml">
<link rel="apple-touch-icon" href="{{ asset('icon.svg') }}">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800" rel="stylesheet">

@vite(['resources/css/app.css', 'resources/js/app.js'])
