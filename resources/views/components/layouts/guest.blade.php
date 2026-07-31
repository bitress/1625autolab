<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', '1625 Autolab') }}</title>

        <!-- Scripts & Styles -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rizmyabdulla/fontawesome-pro@main/releases/v7.3.0/css/fontawesome.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rizmyabdulla/fontawesome-pro@main/releases/v7.3.0/css/solid.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rizmyabdulla/fontawesome-pro@main/releases/v7.3.0/css/regular.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rizmyabdulla/fontawesome-pro@main/releases/v7.3.0/css/light.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rizmyabdulla/fontawesome-pro@main/releases/v7.3.0/css/thin.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rizmyabdulla/fontawesome-pro@main/releases/v7.3.0/css/brands.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rizmyabdulla/fontawesome-pro@main/releases/v7.3.0/css/sharp-solid.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rizmyabdulla/fontawesome-pro@main/releases/v7.3.0/css/sharp-regular.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rizmyabdulla/fontawesome-pro@main/releases/v7.3.0/css/sharp-light.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rizmyabdulla/fontawesome-pro@main/releases/v7.3.0/css/sharp-thin.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rizmyabdulla/fontawesome-pro@main/releases/v7.3.0/css/duotone.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rizmyabdulla/fontawesome-pro@main/releases/v7.3.0/css/duotone-regular.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rizmyabdulla/fontawesome-pro@main/releases/v7.3.0/css/duotone-light.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rizmyabdulla/fontawesome-pro@main/releases/v7.3.0/css/duotone-thin.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rizmyabdulla/fontawesome-pro@main/releases/v7.3.0/css/sharp-duotone-solid.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rizmyabdulla/fontawesome-pro@main/releases/v7.3.0/css/sharp-duotone-regular.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rizmyabdulla/fontawesome-pro@main/releases/v7.3.0/css/sharp-duotone-light.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rizmyabdulla/fontawesome-pro@main/releases/v7.3.0/css/sharp-duotone-thin.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rizmyabdulla/fontawesome-pro@main/releases/v7.3.0/css/chisel-regular.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rizmyabdulla/fontawesome-pro@main/releases/v7.3.0/css/etch-solid.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rizmyabdulla/fontawesome-pro@main/releases/v7.3.0/css/graphite-thin.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rizmyabdulla/fontawesome-pro@main/releases/v7.3.0/css/jelly-regular.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rizmyabdulla/fontawesome-pro@main/releases/v7.3.0/css/jelly-duo-regular.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rizmyabdulla/fontawesome-pro@main/releases/v7.3.0/css/jelly-fill-regular.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rizmyabdulla/fontawesome-pro@main/releases/v7.3.0/css/mosaic-solid.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rizmyabdulla/fontawesome-pro@main/releases/v7.3.0/css/notdog-solid.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rizmyabdulla/fontawesome-pro@main/releases/v7.3.0/css/notdog-duo-solid.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rizmyabdulla/fontawesome-pro@main/releases/v7.3.0/css/pixel-regular.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rizmyabdulla/fontawesome-pro@main/releases/v7.3.0/css/slab-regular.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rizmyabdulla/fontawesome-pro@main/releases/v7.3.0/css/slab-duo-regular.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rizmyabdulla/fontawesome-pro@main/releases/v7.3.0/css/slab-press-regular.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rizmyabdulla/fontawesome-pro@main/releases/v7.3.0/css/slab-press-duo-regular.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rizmyabdulla/fontawesome-pro@main/releases/v7.3.0/css/thumbprint-light.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rizmyabdulla/fontawesome-pro@main/releases/v7.3.0/css/utility-semibold.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rizmyabdulla/fontawesome-pro@main/releases/v7.3.0/css/utility-duo-semibold.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rizmyabdulla/fontawesome-pro@main/releases/v7.3.0/css/utility-fill-semibold.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rizmyabdulla/fontawesome-pro@main/releases/v7.3.0/css/vellum-solid.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rizmyabdulla/fontawesome-pro@main/releases/v7.3.0/css/whiteboard-semibold.css" />    </head>
    <body>
    <div class="min-h-screen bg-brand-dark font-sans text-brand-light selection:bg-brand-orange selection:text-white flex flex-col">
            
            <livewire:guest.header />

            <main class="flex-grow">
                {{ $slot }}
            </main>

            <livewire:guest.footer />

        </div>
    </body>
</html>
