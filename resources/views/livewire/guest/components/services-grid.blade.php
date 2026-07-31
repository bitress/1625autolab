<div>
        {{-- Services Grid --}}
        <section id="services" class="py-24 bg-asphalt relative overflow-hidden border-t border-gray-800">
            <div class="container mx-auto px-4 md:px-6 relative z-10">

                {{-- Header --}}
                <div class="text-center max-w-3xl mx-auto mb-16 space-y-4">
                    <span class="text-brand-orange font-bold uppercase tracking-widest text-sm">
                        What We Do
                    </span>
                    <h2 class="text-4xl md:text-5xl font-display font-black text-white uppercase tracking-tighter">
                        The <span class="text-brand-orange">Lab</span> Services
                    </h2>
                    <div class="w-24 h-1 bg-brand-orange mx-auto mt-6 rounded-full"></div>
                </div>

                {{-- Grid --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">

                    @foreach ($services as $service)
                    <a href="{{ route('services.show', $service['slug']) }}"
                       class="group relative flex flex-col h-full bg-brand-darker/80 backdrop-blur-sm border border-gray-800 p-8 transition-all duration-300 hover:-translate-y-1 hover:border-brand-orange hover:bg-brand-dark hover:shadow-[0_8px_30px_rgba(243,111,33,0.15)]">

                        {{-- Decorative Corner --}}
                        <div class="absolute top-0 right-0 w-12 h-12 border-t-2 border-r-2 border-transparent group-hover:border-brand-orange transition-colors duration-300 rounded-tr-sm"></div>

                        {{-- Icon --}}
                        <div class="mb-6 inline-flex items-center justify-center w-16 h-16 bg-brand-dark border border-gray-700 rounded-sm group-hover:bg-brand-orange group-hover:border-brand-orange transition-all duration-300 group-hover:shadow-[0_0_15px_rgba(243,111,33,0.6)] group-hover:scale-110 shrink-0">
                            @if ($service['icon'] === 'Lightbulb')
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-8 h-8 text-brand-orange group-hover:text-white transition-colors duration-300"><path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.9 1.3 1.5 1.5 2.5"/><path d="M9 18h6"/><path d="M10 22h4"/></svg>
                            @elseif ($service['icon'] === 'MonitorPlay')
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-8 h-8 text-brand-orange group-hover:text-white transition-colors duration-300"><path d="m10 7 5 3-5 3Z"/><rect width="20" height="14" x="2" y="3" rx="2"/><path d="M12 17v4"/><path d="M8 21h8"/></svg>
                            @elseif ($service['icon'] === 'Zap')
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-8 h-8 text-brand-orange group-hover:text-white transition-colors duration-300"><path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"/></svg>
                            @elseif ($service['icon'] === 'ShieldAlert')
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-8 h-8 text-brand-orange group-hover:text-white transition-colors duration-300"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2-1 4-2 7-2 2.5 0 4.5 1 6.5 2a1 1 0 0 1 1 1z"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
                            @elseif ($service['icon'] === 'CarFront')
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-8 h-8 text-brand-orange group-hover:text-white transition-colors duration-300"><path d="m21 8-2 2-1.5-3.7A2 2 0 0 0 15.646 5H8.4a2 2 0 0 0-1.903 1.257L5 10 3 8"/><path d="M7 14h.01"/><path d="M17 14h.01"/><rect width="18" height="8" x="3" y="10" rx="2"/><path d="M5 18v2"/><path d="M19 18v2"/></svg>
                            @else
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-8 h-8 text-brand-orange group-hover:text-white transition-colors duration-300"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                            @endif
                        </div>

                        {{-- Text Content --}}
                        <h3 class="text-2xl font-display font-bold text-white mb-3 uppercase tracking-wide group-hover:text-brand-orange transition-colors duration-300">
                            {{ $service['title'] }}
                        </h3>

                        <p class="text-gray-400 leading-relaxed mb-4 line-clamp-3">
                            {{ $service['description'] }}
                        </p>

                        {{-- Price & Duration Spacer --}}
                        <div class="flex-grow flex flex-col justify-end">
                            @if ($service['startingPrice'])
                                <p class="text-brand-orange font-bold text-sm bg-brand-orange/10 self-start px-3 py-1.5 rounded-sm">
                                    {{ $service['startingPrice'] }}
                                    @if ($service['duration'])
                                        <span class="text-gray-400 font-normal ml-1">· {{ $service['duration'] }}</span>
                                    @endif
                                </p>
                            @endif
                        </div>

                        {{-- Action Link --}}
                        <div class="mt-8 pt-6 border-t border-gray-800 flex items-center gap-3 text-sm font-bold uppercase tracking-widest text-gray-500 group-hover:text-brand-orange transition-colors duration-300 w-full">
                            <span>Learn More</span>
                            <div class="h-[1px] bg-gray-500 group-hover:bg-brand-orange flex-grow transition-all duration-300"></div>
                        </div>

                    </a>
                    @endforeach

                </div>
            </div>
        </section>
</div>
