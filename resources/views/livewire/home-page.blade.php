    <main class="bg-brand-dark">

        {{-- Hero --}}
        <section class="relative min-h-screen flex items-center justify-center pt-24 overflow-hidden">

            {{-- Background Image with Overlay --}}
            <div class="absolute inset-0 z-0 bg-asphalt">
                <img
                    src="https://images.unsplash.com/photo-1584345611124-287a5085e648?q=80&w=2015&auto=format&fit=crop"
                    alt="Dark automotive garage"
                    class="w-full h-full object-cover object-center mix-blend-overlay opacity-40"
                    referrerpolicy="no-referrer"
                />
                <div class="absolute inset-0 bg-gradient-to-r from-brand-darker/95 via-brand-darker/80 to-transparent"></div>
            </div>

            <div class="container mx-auto px-4 md:px-6 relative z-10 grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                {{-- Text Content --}}
                <div class="space-y-8 max-w-2xl">

                    <div class="inline-flex items-center gap-2 px-3 py-1 bg-brand-orange/10 border border-brand-orange/30 rounded-full">
                        <span class="w-2 h-2 rounded-full bg-brand-orange animate-pulse"></span>
                        <span class="text-brand-orange text-xs font-bold uppercase tracking-widest">
                            Premium Auto Retrofitting
                        </span>
                    </div>

                    <img
                        src="https://cdn.1625autolab.com/1625autolab/logos/1625%20Autolab%20logo.png"
                        alt="1625 Autolab"
                        class="w-auto max-w-[320px] sm:max-w-[420px] md:max-w-[520px] object-contain"
                        referrerpolicy="no-referrer"
                    />

                    <p class="text-gray-400 text-lg md:text-xl max-w-lg leading-relaxed border-l-4 border-brand-orange pl-4">
                        Specializing in custom headlight retrofit and android headunit. We don't just fix cars; we upgrade them.
                    </p>

                    <div class="flex flex-col sm:flex-row gap-4 pt-4">
                        <a href="{{ route('services') }}"
                           class="group relative inline-flex items-center justify-center gap-2 bg-brand-orange text-white font-display uppercase tracking-wider px-8 py-4 rounded-sm overflow-hidden transition-all hover:shadow-[0_0_30px_rgba(255,106,0,0.4)]">
                            <span class="relative z-10 flex items-center gap-2">
                                Explore Services
                                <i class="fa-solid fa-arrow-right w-5 h-5 group-hover:translate-x-1 transition-transform"></i>
                            </span>
                            <div class="absolute inset-0 bg-white/20 translate-y-full group-hover:translate-y-0 transition-transform duration-300 ease-in-out"></div>
                        </a>

                        <a href="{{ route('booking') }}"
                           class="group inline-flex items-center justify-center gap-2 bg-transparent border border-gray-600 text-white font-display uppercase tracking-wider px-8 py-4 rounded-sm hover:border-white hover:bg-white/5 transition-all">
                            Book The Lab
                            <i class="fa-solid fa-chevron-right w-5 h-5 group-hover:translate-x-1 transition-transform"></i>
                        </a>
                    </div>

                </div>
            </div>

            {{-- Decorative Elements --}}
            <div class="absolute top-1/2 right-0 -translate-y-1/2 translate-x-1/3 opacity-5 pointer-events-none overflow-hidden">
                <span class="font-display text-[20rem] font-bold leading-none text-white whitespace-nowrap">
                    <span class="font-fasthand">1625</span>
                </span>
            </div>

            <div class="absolute bottom-0 left-0 w-full h-32 bg-gradient-to-t from-brand-dark to-transparent z-100"></div>


        </section>

        {{-- Quick Links Bar --}}
        <section class="relative border-y border-gray-800 bg-brand-darker/80">
            <div class="pointer-events-none absolute inset-0"
                 style="background: radial-gradient(circle at 20% 50%, rgba(243,111,33,0.12), transparent 45%), radial-gradient(circle at 80% 50%, rgba(243,111,33,0.08), transparent 40%);">
            </div>
            <div class="container mx-auto px-4 md:px-6 py-6 relative">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-3">

                    <a href="#services"
                       class="group rounded-lg border border-gray-700 bg-brand-dark/70 px-4 py-4 transition-colors hover:border-brand-orange">
                        <p class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-widest text-brand-orange">
                            <i class="fa-solid fa-sparkles h-4 w-4"></i> Popular Upgrades
                        </p>
                        <p class="mt-2 text-sm text-gray-300 group-hover:text-white">
                            Browse retrofit packages and core service options.
                        </p>
                    </a>

                    <a href="{{ route('booking') }}"
                       class="group rounded-lg border border-gray-700 bg-brand-dark/70 px-4 py-4 transition-colors hover:border-brand-orange">
                        <p class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-widest text-brand-orange">
                            <i class="fa-solid fa-calendar-clock h-4 w-4"></i> Fast Booking
                        </p>
                        <p class="mt-2 text-sm text-gray-300 group-hover:text-white">
                            Schedule your slot in minutes with live availability.
                        </p>
                    </a>

                    <a href="#builds"
                       class="group rounded-lg border border-gray-700 bg-brand-dark/70 px-4 py-4 transition-colors hover:border-brand-orange">
                        <p class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-widest text-brand-orange">
                            <i class="fa-solid fa-shield-check h-4 w-4"></i> Real Build Proof
                        </p>
                        <p class="mt-2 text-sm text-gray-300 group-hover:text-white">
                            See recent shop work, details, and finish quality.
                        </p>
                    </a>

                </div>
            </div>
        </section>

        <livewire:guest.components.services-grid />
        <livewire:guest.components.promo-banner />
        <livewire:guest.components.recent-builds />
        <livewire:guest.components.before-after-showcase />
        <livewire:guest.components.testimonials />
        <livewire:guest.components.home-faq-section />
    </main>