<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        @include('partials.fonts')

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="curtain"></div>
        <div class="flex min-h-[calc(100vh-10px)] flex-col items-center px-4 pt-10 sm:justify-center sm:pt-0">
            <a href="/" class="font-logo text-4xl tracking-wider text-white">どこでも大喜利</a>

            <div class="w-full sm:max-w-md mt-6 px-6 py-6 mekuri overflow-hidden text-sumi !font-sans !font-normal">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
