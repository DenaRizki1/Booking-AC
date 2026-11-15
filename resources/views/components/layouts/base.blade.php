@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ \App\Models\CompanySettings::companyName() }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-era.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo-era.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    <meta name="theme-color" content="#00084D">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{ $head ?? '' }}
</head>
<body x-data {{ $attributes }}>
    {{ $slot }}
</body>
</html>
