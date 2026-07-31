<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? '1625 Auto Lab - Admin' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="antialiased selection:bg-brand-orange selection:text-white bg-brand-black text-brand-light font-sans flex h-screen overflow-hidden">
    <!-- Sidebar -->
    <aside class="w-64 bg-brand-gray border-r border-brand-light/10 flex flex-col hidden md:flex">
        <div class="h-20 flex items-center px-6 border-b border-brand-light/10">
            <div class="text-2xl font-display font-bold tracking-tighter text-white flex items-center gap-2">
                <svg class="w-6 h-6 text-brand-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                <a href="/admin">ADMIN <span class="text-brand-orange">PORTAL</span></a>
            </div>
        </div>
        <nav class="flex-1 p-4 space-y-2 overflow-y-auto">
            <a href="#" class="block px-4 py-2 rounded-sm bg-brand-orange text-white font-medium">Dashboard</a>
            <a href="#" class="block px-4 py-2 rounded-sm text-brand-light/70 hover:bg-brand-black hover:text-white transition-colors">Users & Roles</a>
            <a href="#" class="block px-4 py-2 rounded-sm text-brand-light/70 hover:bg-brand-black hover:text-white transition-colors">Builds</a>
            <a href="#" class="block px-4 py-2 rounded-sm text-brand-light/70 hover:bg-brand-black hover:text-white transition-colors">Settings</a>
        </nav>
    </aside>

    <div class="flex-1 flex flex-col overflow-hidden">
        <!-- Admin Header -->
        <header class="h-20 bg-brand-gray/50 backdrop-blur-md border-b border-brand-light/10 flex items-center justify-between px-6">
            <h1 class="text-xl font-display uppercase tracking-wider text-white">Admin Dashboard</h1>
            <div class="flex items-center gap-4">
                <span class="text-sm font-medium">Admin User</span>
                <button class="px-4 py-2 bg-brand-black border border-brand-light/10 rounded-sm hover:bg-brand-orange hover:border-brand-orange transition-colors">Logout</button>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto p-6 bg-asphalt animate-fade-in-up">
            {{ $slot }}
        </main>
    </div>
    @livewireScripts
</body>
</html>
