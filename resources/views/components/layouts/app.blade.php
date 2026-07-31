<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? '1625 Auto Lab' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="antialiased selection:bg-brand-orange selection:text-white overflow-x-hidden bg-brand-black text-brand-light font-sans">
    <header class="fixed top-0 w-full z-50 bg-brand-black/80 backdrop-blur-md border-b border-brand-gray animate-slide-up">
        <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
            <div class="text-3xl font-display font-bold tracking-tighter text-white flex items-center gap-2">
                <svg class="w-8 h-8 text-brand-orange animate-wiggle" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                <a href="/">1625 <span class="text-brand-orange">AUTO LAB</span></a>
            </div>
            <div class="flex gap-4">
                <a href="/login" class="px-6 py-2 rounded-sm bg-brand-gray text-brand-light font-medium hover:bg-white hover:text-black transition-colors duration-300">Login</a>
                <a href="/register" class="px-6 py-2 rounded-sm bg-brand-orange text-white font-medium hover:bg-orange-600 transition-colors duration-300 shadow-md shadow-brand-orange/20">Register</a>
            </div>
        </div>
    </header>

    <main class="pt-20">
        {{ $slot }}
    </main>

    <footer class="bg-brand-black border-t border-brand-gray py-12 mt-20">
        <div class="max-w-7xl mx-auto px-6 flex flex-col md:flex-row justify-between items-center">
            <div class="text-2xl font-display font-bold tracking-tighter text-white mb-4 md:mb-0">
                1625 <span class="text-brand-orange">AUTO LAB</span>
            </div>
            <div class="text-brand-light/50 text-sm">
                &copy; {{ date('Y') }} 1625 Auto Lab. All rights reserved.
            </div>
        </div>
    </footer>
    @livewireScripts
</body>
</html>
