<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public string $buildSearch = '';
    public int $cartItemsCount = 0;

    public function mount()
    {
        $this->buildSearch = request()->query('buildSearch', request()->query('portfolioSearch', ''));
        // Mock cart logic for now, in a real application you would fetch this from session or database
        // $this->cartItemsCount = app(CartService::class)->count();
    }

    public function handleBuildSearchSubmit()
    {
        $query = trim($this->buildSearch);
        if ($query !== '') {
            $this->redirect('/?buildSearch=' . urlencode($query), navigate: true);
        } else {
            $this->redirect('/', navigate: true);
        }
    }

    public function logout()
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        $this->redirect('/', navigate: true);
    }
}; ?>

<header 
    x-data="{ 
        isScrolled: false, 
        isMobileMenuOpen: false, 
        isDropdownOpen: false 
    }"
    @scroll.window="isScrolled = window.scrollY > 20"
    x-effect="document.body.style.overflow = isMobileMenuOpen ? 'hidden' : ''"
    :class="isScrolled || isMobileMenuOpen ? 'bg-brand-darker/95 backdrop-blur-md py-3 shadow-lg border-b border-gray-800' : 'bg-transparent py-5'"
    class="fixed top-0 left-0 right-0 z-50 transition-all duration-300"
>
  <div class="container mx-auto px-4 md:px-6 flex items-center justify-between">
    <!-- Logo -->
    <a href="/" wire:navigate class="flex items-center justify-center hover:opacity-80 transition-opacity z-50">
      <img src="https://cdn.1625autolab.com/1625autolab/logos/logo.png" alt="1625 Autolab Logo" class="h-8 md:h-10 w-auto object-contain transition-all duration-300" />
    </a>

    <!-- Desktop Nav -->
    <nav class="hidden lg:flex items-center gap-6 xl:gap-8">
      @php
        $navLinks = [
            ['name' => 'Home', 'href' => '/'],
            ['name' => 'Services', 'href' => '/services'],
            ['name' => 'Products', 'href' => '/products'],
            ['name' => 'Portfolio', 'href' => '/portfolio'],
            ['name' => 'About', 'href' => '/about'],
            ['name' => 'Contact', 'href' => '/contact'],
        ];
      @endphp
      @foreach($navLinks as $link)
        <a href="{{ $link['href'] }}" wire:navigate
           class="text-sm font-bold uppercase tracking-widest transition-colors {{ request()->is(ltrim($link['href'], '/')) || (request()->is('/') && $link['href'] === '/') ? 'text-brand-orange' : 'text-gray-300 hover:text-brand-orange' }}">
          {{ $link['name'] }}
        </a>
      @endforeach
    </nav>

    <!-- Desktop Auth/Actions -->
    <div class="hidden lg:flex items-center gap-4">
      <form wire:submit="handleBuildSearchSubmit" class="hidden xl:flex items-center gap-2">
        <div class="relative">
          <svg class="w-4 h-4 text-gray-500 absolute left-3 top-1/2 -translate-y-1/2" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
          <input
            wire:model="buildSearch"
            placeholder="Search your car make and model"
            class="w-72 bg-brand-dark border border-gray-700 text-white pl-9 pr-3 py-2.5 rounded-sm text-sm focus:outline-none focus:border-brand-orange"
          />
        </div>
      </form>

      <a href="/cart" wire:navigate class="relative inline-flex items-center justify-center w-10 h-10 rounded-sm border transition-colors {{ request()->is('cart') ? 'border-brand-orange text-brand-orange bg-brand-orange/10' : 'border-gray-700 text-gray-300 hover:text-white hover:border-brand-orange' }}" aria-label="Open cart">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
        @if($cartItemsCount > 0)
          <span class="absolute -top-1.5 -right-1.5 min-w-5 h-5 px-1 rounded-full bg-brand-orange text-white text-[10px] font-bold flex items-center justify-center">
            {{ $cartItemsCount }}
          </span>
        @endif
      </a>

      @guest
        <a href="/login" wire:navigate class="text-sm font-bold uppercase tracking-widest text-gray-300 hover:text-brand-orange transition-colors">
          Login
        </a>
      @endguest

      @auth
        <div class="flex items-center gap-3">
          <!-- Notification Bell placeholder -->
          <button class="text-gray-300 hover:text-white">
            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
          </button>
          
          <div class="relative" @click.outside="isDropdownOpen = false">
            <button
              @click="isDropdownOpen = !isDropdownOpen"
              class="flex items-center gap-2 bg-brand-dark border border-gray-700 hover:border-brand-orange px-3 py-2 rounded-sm transition-colors"
            >
              <div class="w-7 h-7 rounded-full bg-brand-orange/20 border border-brand-orange/40 flex items-center justify-center overflow-hidden shrink-0">
                <img src="{{ auth()->user()->avatar_url ?? 'https://api.dicebear.com/7.x/initials/svg?seed='.urlencode(auth()->user()->name) }}" alt="User avatar" class="w-full h-full object-cover" />
              </div>
              <span class="text-white text-sm font-bold max-w-[120px] truncate">{{ explode(' ', auth()->user()->name)[0] ?? auth()->user()->name }}</span>
              <svg class="w-4 h-4 text-gray-400 transition-transform" :class="isDropdownOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
            </button>

            <div x-cloak x-show="isDropdownOpen" x-transition class="absolute right-0 mt-2 w-52 bg-brand-dark border border-gray-700 rounded-sm shadow-2xl py-1 z-50">
              <div class="px-4 py-3 border-b border-gray-800">
                <p class="text-white font-bold text-sm truncate">{{ auth()->user()->name }}</p>
                <p class="text-gray-500 text-xs truncate">{{ auth()->user()->email }}</p>
              </div>
              
              @if(auth()->user()->role !== 'client')
                <a href="/admin" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-300 hover:text-white hover:bg-gray-800 transition-colors">
                  <svg class="w-4 h-4 text-brand-orange" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
                  Admin Panel
                </a>
              @else
                <a href="/client/dashboard" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-300 hover:text-white hover:bg-gray-800 transition-colors">
                  <svg class="w-4 h-4 text-brand-orange" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
                  Dashboard
                </a>
                <a href="/client/bookings" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-300 hover:text-white hover:bg-gray-800 transition-colors">
                  <svg class="w-4 h-4 text-brand-orange" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                  My Bookings
                </a>
                <a href="/client/orders" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-300 hover:text-white hover:bg-gray-800 transition-colors">
                  <svg class="w-4 h-4 text-brand-orange" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                  My Orders
                </a>
                <a href="/client/profile" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-300 hover:text-white hover:bg-gray-800 transition-colors">
                  <svg class="w-4 h-4 text-brand-orange" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                  Profile
                </a>
              @endif
              <div class="border-t border-gray-800 mt-1">
                <button wire:click="logout" class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-gray-400 hover:text-red-400 transition-colors">
                  <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
                  Sign Out
                </button>
              </div>
            </div>
          </div>
        </div>
      @endauth
    </div>

    <!-- Mobile Toggle Button -->
    <div class="lg:hidden flex items-center gap-4 z-50">
      <a href="/cart" wire:navigate class="relative text-white p-1" aria-label="Open cart">
        <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
        @if($cartItemsCount > 0)
          <span class="absolute -top-1 -right-1 min-w-4 h-4 px-1 rounded-full bg-brand-orange text-white text-[9px] font-bold flex items-center justify-center">
            {{ $cartItemsCount }}
          </span>
        @endif
      </a>

      <form wire:submit="handleBuildSearchSubmit" class="flex-1 max-w-[180px] sm:max-w-[220px]">
        <div class="relative">
          <svg class="w-4 h-4 text-gray-500 absolute left-2.5 top-1/2 -translate-y-1/2" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
          <input
            wire:model="buildSearch"
            placeholder="Search car"
            class="w-full bg-brand-dark/95 border border-gray-700 text-white pl-8 pr-2.5 py-2 rounded-sm text-[16px] focus:outline-none focus:border-brand-orange"
          />
        </div>
      </form>

      @auth
        <button class="text-gray-300 hover:text-white">
          <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
        </button>
      @endauth

      <button class="text-white p-1" @click="isMobileMenuOpen = !isMobileMenuOpen" aria-label="Toggle menu" :aria-expanded="isMobileMenuOpen">
        <svg x-show="!isMobileMenuOpen" class="w-7 h-7" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/></svg>
        <svg x-cloak x-show="isMobileMenuOpen" class="w-7 h-7" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
      </button>
    </div>
  </div>

  <!-- Mobile Menu Overlay -->
  <div class="lg:hidden absolute top-full left-0 w-full bg-brand-darker border-b border-gray-800 shadow-2xl transition-all duration-300 overflow-hidden"
       :class="isMobileMenuOpen ? 'max-h-[calc(100vh-60px)] opacity-100' : 'max-h-0 opacity-0'">
    <div class="px-4 py-6 flex flex-col gap-2 overflow-y-auto max-h-[calc(100vh-60px)]">
      @foreach($navLinks as $link)
        <a href="{{ $link['href'] }}" wire:navigate
           class="text-base font-bold uppercase tracking-widest transition-colors py-3 px-2 border-b border-gray-800/50 {{ request()->is(ltrim($link['href'], '/')) || (request()->is('/') && $link['href'] === '/') ? 'text-brand-orange' : 'text-gray-300 hover:text-brand-orange' }}">
          {{ $link['name'] }}
        </a>
      @endforeach

      @guest
        <div class="flex flex-col gap-3 mt-6">
          <a href="/login" wire:navigate class="text-center border border-gray-700 text-white font-bold uppercase tracking-widest px-6 py-3 rounded-sm hover:border-brand-orange transition-colors">
            Login
          </a>
        </div>
      @endguest

      @auth
        <div class="mt-4 border-t border-gray-800 pt-4 space-y-1">
          <div class="flex items-center gap-2 px-2 mb-3">
            <div class="w-8 h-8 rounded-full bg-brand-orange/20 border border-brand-orange/40 flex items-center justify-center overflow-hidden shrink-0">
              <img src="{{ auth()->user()->avatar_url ?? 'https://api.dicebear.com/7.x/initials/svg?seed='.urlencode(auth()->user()->name) }}" alt="User avatar" class="w-full h-full object-cover" />
            </div>
            <p class="text-xs font-bold uppercase tracking-widest text-gray-500">{{ auth()->user()->name }}</p>
          </div>
          
          @if(auth()->user()->role !== 'client')
            <a href="/admin" class="flex items-center gap-3 px-2 py-3 text-sm text-gray-300 hover:text-brand-orange transition-colors font-bold uppercase tracking-widest">
              <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
              Admin Panel
            </a>
          @else
            <a href="/client/dashboard" class="flex items-center gap-3 px-2 py-3 text-sm text-gray-300 hover:text-brand-orange transition-colors font-bold uppercase tracking-widest">
              <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
              Dashboard
            </a>
            <a href="/client/bookings" class="flex items-center gap-3 px-2 py-3 text-sm text-gray-300 hover:text-brand-orange transition-colors font-bold uppercase tracking-widest">
              <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
              My Bookings
            </a>
            <a href="/client/orders" class="flex items-center gap-3 px-2 py-3 text-sm text-gray-300 hover:text-brand-orange transition-colors font-bold uppercase tracking-widest">
              <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
              My Orders
            </a>
            <a href="/client/profile" class="flex items-center gap-3 px-2 py-3 text-sm text-gray-300 hover:text-brand-orange transition-colors font-bold uppercase tracking-widest">
              <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              Profile
            </a>
          @endif
          
          <button wire:click="logout" class="w-full flex items-center gap-3 px-2 py-3 mt-2 text-sm text-gray-400 hover:text-red-400 transition-colors font-bold uppercase tracking-widest">
            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
            Sign Out
          </button>
        </div>
      @endauth
    </div>
  </div>
</header>
