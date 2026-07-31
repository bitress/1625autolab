<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? '1625 Auto Lab - Auth' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="antialiased selection:bg-brand-orange selection:text-white bg-asphalt text-brand-light font-sans h-screen flex items-center justify-center overflow-hidden">
    <div class="absolute inset-0 bg-brand-black/40 z-0"></div>
    <main class="relative z-10 w-full max-w-md p-6 bg-brand-gray/80 backdrop-blur-lg border border-brand-light/10 rounded-sm shadow-2xl animate-slide-up">
        <div class="text-center mb-8">
            <div class="text-3xl font-display font-bold tracking-tighter text-white flex items-center justify-center gap-2 mb-2">
                <svg class="w-8 h-8 text-brand-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                <a href="/">1625 <span class="text-brand-orange">AUTO LAB</span></a>
            </div>
            <p class="text-brand-light/70 font-light text-sm">Sign in to your portal</p>
        </div>
        {{ $slot }}
    </main>
    @livewireScripts
</body>
</html>
