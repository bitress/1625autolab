<div
    id="site-header"
    class="fixed top-0 left-0 right-0 z-50 transition-all duration-300 py-2 {{ $mobileOpen ? 'bg-brand-darker/95 backdrop-blur-md py-3 shadow-lg border-b border-gray-800' : 'bg-transparent py-5' }}"
>
    <div class="container mx-auto px-4 md:px-6 flex items-center justify-between">

        {{-- Logo --}}
        <a href="{{ url('/') }}" wire:click="closeMobileMenu" class="flex items-center justify-center hover:opacity-80 transition-opacity z-50">
            <img
                src="https://cdn.1625autolab.com/1625autolab/logos/logo.png"
                alt="1625 Autolab Logo"
                class="h-8 md:h-10 w-auto object-contain transition-all duration-300"
                referrerpolicy="no-referrer"
            />
        </a>

        {{-- Desktop Nav --}}
        <nav class="hidden lg:flex items-center gap-6 xl:gap-8">
            @foreach ($navLinks as $link)
                <a
                    href="{{ url($link['href']) }}"
                    class="text-sm font-bold uppercase tracking-widest transition-colors {{ request()->is(trim($link['href'], '/') ?: '/') ? 'text-brand-orange' : 'text-gray-300 hover:text-brand-orange' }}"
                >
                    {{ $link['name'] }}
                </a>
            @endforeach
        </nav>

        {{-- Desktop Auth/Actions --}}
        <div class="hidden lg:flex items-center gap-4">

            @guest
                <a href="{{ route('login') }}" class="text-sm font-bold uppercase tracking-widest text-gray-300 hover:text-brand-orange transition-colors">
                    Login
                </a>
            @endguest

            @auth
                <div class="flex items-center gap-3">
                    <livewire:notification-bell />

                    <div class="relative" wire:click.away="closeDropdown">
                        <button
                            wire:click="toggleDropdown"
                            type="button"
                            class="flex items-center gap-2 bg-brand-dark border border-gray-700 hover:border-brand-orange px-3 py-2 rounded-sm transition-colors"
                        >
                            <div class="w-7 h-7 rounded-full bg-brand-orange/20 border border-brand-orange/40 flex items-center justify-center overflow-hidden shrink-0">
                                <img
                                    src="{{ $this->avatarUrl($user) }}"
                                    alt="User avatar"
                                    class="w-full h-full object-cover"
                                    referrerpolicy="no-referrer"
                                />
                            </div>
                            <span class="text-white text-sm font-bold max-w-[120px] truncate">{{ explode(' ', $user->name)[0] }}</span>
                            <svg class="w-4 h-4 text-gray-400 transition-transform {{ $dropdownOpen ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        @if ($dropdownOpen)
                            <div class="absolute right-0 mt-2 w-52 bg-brand-dark border border-gray-700 rounded-sm shadow-2xl py-1 z-50">
                                <div class="px-4 py-3 border-b border-gray-800">
                                    <p class="text-white font-bold text-sm truncate">{{ $user->name }}</p>
                                    <p class="text-gray-500 text-xs truncate">{{ $user->email }}</p>
                                </div>

                                @if ($user->role !== 'client')
                                    <a href="{{ url('/admin') }}" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-300 hover:text-white hover:bg-gray-800 transition-colors">
                                        @include('livewire.partials.icon', ['name' => 'dashboard', 'class' => 'w-4 h-4 text-brand-orange'])
                                        Admin Panel
                                    </a>
                                @else
                                    @foreach ($clientMenu as $item)
                                        <a href="{{ url($item['href']) }}" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-300 hover:text-white hover:bg-gray-800 transition-colors">
                                            @include('livewire.partials.icon', ['name' => $item['icon'], 'class' => 'w-4 h-4 text-brand-orange'])
                                            {{ $item['label'] }}
                                        </a>
                                    @endforeach
                                @endif

                                <div class="border-t border-gray-800 mt-1">
                                    <button wire:click="logout" type="button" class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-gray-400 hover:text-red-400 transition-colors">
                                        @include('livewire.partials.icon', ['name' => 'logout', 'class' => 'w-4 h-4'])
                                        Sign Out
                                    </button>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endauth
        </div>

        {{-- Mobile Toggle Button --}}
        <div class="lg:hidden flex items-center gap-4 z-50">
            <a href="{{ url('/cart') }}" class="relative text-white p-1" aria-label="Open cart">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.29 2.29c-.63.63-.18 1.71.71 1.71H17m-10 4a1 1 0 100 2 1 1 0 000-2zm10 0a1 1 0 100 2 1 1 0 000-2z" />
                </svg>
                @if ($cartItemsCount > 0)
                    <span class="absolute -top-1 -right-1 min-w-4 h-4 px-1 rounded-full bg-brand-orange text-white text-[9px] font-bold flex items-center justify-center">
                        {{ $cartItemsCount }}
                    </span>
                @endif
            </a>

            <form wire:submit="search" class="flex-1 max-w-[180px] sm:max-w-[220px]">
                <div class="relative">
                    <svg class="w-4 h-4 text-gray-500 absolute left-2.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
                    </svg>
                    <input
                        type="text"
                        wire:model="buildSearch"
                        placeholder="Search car"
                        class="w-full bg-brand-dark/95 border border-gray-700 text-white pl-8 pr-2.5 py-2 rounded-sm text-[16px] focus:outline-none focus:border-brand-orange"
                    />
                </div>
            </form>

            @auth
                <livewire:notification-bell />
            @endauth

            <button
                wire:click="toggleMobileMenu"
                type="button"
                class="text-white p-1"
                aria-label="Toggle menu"
                aria-expanded="{{ $mobileOpen ? 'true' : 'false' }}"
            >
                @if ($mobileOpen)
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                @else
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                @endif
            </button>
        </div>
    </div>

    {{-- Mobile Menu Overlay --}}
    <div class="lg:hidden absolute top-full left-0 w-full bg-brand-darker border-b border-gray-800 shadow-2xl transition-all duration-300 overflow-hidden {{ $mobileOpen ? 'max-h-[calc(100vh-60px)] opacity-100' : 'max-h-0 opacity-0' }}">
        <div class="px-4 py-6 flex flex-col gap-2 overflow-y-auto max-h-[calc(100vh-60px)]">
            @foreach ($navLinks as $link)
                <a
                    href="{{ url($link['href']) }}"
                    wire:click="closeMobileMenu"
                    class="text-base font-bold uppercase tracking-widest transition-colors py-3 px-2 border-b border-gray-800/50 {{ request()->is(trim($link['href'], '/') ?: '/') ? 'text-brand-orange' : 'text-gray-300 hover:text-brand-orange' }}"
                >
                    {{ $link['name'] }}
                </a>
            @endforeach

            @guest
                <div class="flex flex-col gap-3 mt-6">
                    <a
                        href="{{ route('login') }}"
                        class="text-center border border-gray-700 text-white font-bold uppercase tracking-widest px-6 py-3 rounded-sm hover:border-brand-orange transition-colors"
                    >
                        Login
                    </a>
                </div>
            @endguest

            @auth
                <div class="mt-4 border-t border-gray-800 pt-4 space-y-1">
                    <div class="flex items-center gap-2 px-2 mb-3">
                        <div class="w-8 h-8 rounded-full bg-brand-orange/20 border border-brand-orange/40 flex items-center justify-center overflow-hidden shrink-0">
                            <img
                                src="{{ $this->avatarUrl($user) }}"
                                alt="User avatar"
                                class="w-full h-full object-cover"
                                referrerpolicy="no-referrer"
                            />
                        </div>
                        <p class="text-xs font-bold uppercase tracking-widest text-gray-500">{{ $user->name }}</p>
                    </div>

                    @if ($user->role !== 'client')
                        <a
                            href="{{ url('/admin') }}"
                            wire:click="closeMobileMenu"
                            class="flex items-center gap-3 px-2 py-3 text-sm text-gray-300 hover:text-brand-orange transition-colors font-bold uppercase tracking-widest"
                        >
                            @include('livewire.partials.icon', ['name' => 'dashboard', 'class' => 'w-5 h-5'])
                            Admin Panel
                        </a>
                    @else
                        @foreach ($clientMenu as $item)
                            <a
                                href="{{ url($item['href']) }}"
                                wire:click="closeMobileMenu"
                                class="flex items-center gap-3 px-2 py-3 text-sm text-gray-300 hover:text-brand-orange transition-colors font-bold uppercase tracking-widest"
                            >
                                @include('livewire.partials.icon', ['name' => $item['icon'], 'class' => 'w-5 h-5'])
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    @endif

                    <button
                        wire:click="logout"
                        type="button"
                        class="w-full flex items-center gap-3 px-2 py-3 mt-2 text-sm text-gray-400 hover:text-red-400 transition-colors font-bold uppercase tracking-widest"
                    >
                        @include('livewire.partials.icon', ['name' => 'logout', 'class' => 'w-5 h-5'])
                        Sign Out
                    </button>
                </div>
            @endauth
        </div>
    </div>

    {{-- Scroll shadow + mobile body scroll-lock. Pure JS, no Alpine. --}}
    @once
        <script>
            (function () {
                const header = document.getElementById('site-header');
                const scrolledClasses = ['bg-brand-darker/95', 'backdrop-blur-md', 'py-3', 'shadow-lg', 'border-b', 'border-gray-800'];
                const topClasses = ['bg-transparent', 'py-5'];

                function applyScrollState() {
                    if (!header) return;
                    // Don't fight Livewire's own render when the mobile menu is forcing the "scrolled" look.
                    if (header.dataset.mobileOpen === 'true') return;

                    if (window.scrollY > 20) {
                        header.classList.remove(...topClasses);
                        header.classList.add(...scrolledClasses);
                    } else {
                        header.classList.remove(...scrolledClasses);
                        header.classList.add(...topClasses);
                    }
                }

                window.addEventListener('scroll', applyScrollState, { passive: true });
                document.addEventListener('livewire:navigated', applyScrollState);
                applyScrollState();

                window.addEventListener('mobile-menu-toggled', (event) => {
                    const open = event.detail?.open ?? event.detail?.[0]?.open;
                    document.body.style.overflow = open ? 'hidden' : '';
                    if (header) header.dataset.mobileOpen = open ? 'true' : 'false';
                });
            })();
        </script>
    @endonce
</div>