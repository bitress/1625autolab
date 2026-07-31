<div>
    @if($offer)
        <section class="relative py-32 overflow-hidden bg-asphalt border-y-4 border-brand-orange">
            {{-- Background Image with Parallax Effect --}}
            <div class="absolute inset-0 z-0">
                <img
                    src="https://images.unsplash.com/photo-1611016186353-9af58c69a533?q=80&w=2071&auto=format&fit=crop"
                    alt="Car Headlights"
                    class="w-full h-full object-cover object-center opacity-20 mix-blend-luminosity"
                    style="transform: translateZ(-1px) scale(1.5)"
                    referrerpolicy="no-referrer"
                />
                <div class="absolute inset-0 bg-gradient-to-r from-brand-darker/90 to-brand-darker/60"></div>
            </div>

            <div class="container mx-auto px-4 md:px-6 relative z-10 text-center">
                <div class="max-w-4xl mx-auto space-y-8">
                    @if(!empty($offer['badgeText']))
                        <span class="inline-block px-4 py-1 bg-brand-orange text-white font-bold uppercase tracking-widest text-sm rounded-sm shadow-lg">
                            {{ $offer['badgeText'] }}
                        </span>
                    @endif

                    <h2 class="text-3xl sm:text-5xl md:text-7xl font-display font-black text-brand-orange uppercase leading-tight drop-shadow-2xl tracking-tighter text-outline">
                        {{ $offer['title'] }}
                    </h2>

                    @if(!empty($offer['subtitle']))
                        <p class="text-base md:text-lg text-brand-orange/80 font-bold uppercase tracking-widest">
                            {{ $offer['subtitle'] }}
                        </p>
                    @endif

                    @if(!empty($offer['description']))
                        <p class="text-xl md:text-2xl text-gray-300 font-medium max-w-2xl mx-auto">
                            {{ $offer['description'] }}
                        </p>
                    @endif

                    <div class="pt-8">
                        <a
                            href="{{ $offer['ctaUrl'] ?? '#contact' }}"
                            class="inline-block bg-brand-orange text-white font-display uppercase tracking-wider px-10 py-5 rounded-sm text-lg hover:bg-orange-600 transition-colors shadow-[0_10px_30px_rgba(243,111,33,0.3)] hover:-translate-y-1 transform duration-300"
                        >
                            {{ $offer['ctaText'] ?? 'Claim Your Offer' }}
                        </a>
                    </div>
                </div>
            </div>

            {{-- Decorative Diagonal Lines --}}
            <div class="absolute top-0 left-0 w-full h-full pointer-events-none overflow-hidden">
                <div class="absolute top-[-50%] left-[-10%] w-[120%] h-[200%] border-[40px] border-black/20 rotate-12"></div>
                <div class="absolute top-[-50%] right-[-10%] w-[120%] h-[200%] border-[20px] border-brand-orange/10 -rotate-12"></div>
            </div>
        </section>
    @endif
</div>
