<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? '1625 Auto Lab - Client Portal' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="antialiased selection:bg-brand-orange selection:text-white bg-asphalt text-brand-light font-sans min-h-screen flex flex-col">
    <!-- Client Header -->
    <header class="h-20 bg-brand-black border-b border-brand-gray flex items-center justify-between px-6 sm:px-12">
        <div class="text-2xl font-display font-bold tracking-tighter text-white flex items-center gap-2">
            <svg class="w-6 h-6 text-brand-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
            <a href="/client">CLIENT <span class="text-brand-orange">PORTAL</span></a>
        </div>
        <nav class="hidden md:flex gap-6">
            <a href="#" class="text-brand-orange font-medium border-b-2 border-brand-orange pb-1">My Builds</a>
            <a href="#" class="text-brand-light/70 hover:text-white transition-colors">Invoices</a>
            <a href="#" class="text-brand-light/70 hover:text-white transition-colors">Support</a>
        </nav>
        <div class="flex items-center gap-4">
            <button class="px-4 py-2 bg-brand-gray rounded-sm text-sm font-medium hover:bg-brand-orange hover:text-white transition-colors shadow-md shadow-brand-black">Logout</button>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 w-full max-w-7xl mx-auto p-6 sm:p-12 animate-slide-up">
        {{ $slot }}
    </main>

    <footer class="bg-brand-black border-t border-brand-gray py-6 mt-auto">
        <div class="text-center text-brand-light/50 text-sm">
            &copy; {{ date('Y') }} 1625 Auto Lab.
        </div>
    </footer>
    @livewireScripts
</body>
</html>
