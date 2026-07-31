<div>
    <section id="builds" class="py-24 bg-brand-dark">
      <style>
        .recent-builds-track::-webkit-scrollbar {
          display: none;
        }
        @keyframes rb-popout {
          0%   { box-shadow: 0 0 0 0 rgba(249,115,22,0);   transform: scale(1);     }
          25%  { box-shadow: 0 0 0 8px rgba(249,115,22,0.55); transform: scale(1.018); }
          60%  { box-shadow: 0 0 0 4px rgba(249,115,22,0.25); transform: scale(1.012); }
          100% { box-shadow: 0 0 0 0 rgba(249,115,22,0);   transform: scale(1);     }
        }
        .rb-popout-active {
          animation: rb-popout 1.4s cubic-bezier(0.22,1,0.36,1) forwards;
        }
      </style>
      <div class="container mx-auto px-4 md:px-6">
        <div class="flex flex-col md:flex-row justify-between items-end mb-16 gap-6">
          <div class="space-y-4 max-w-2xl">
            <span class="text-brand-orange font-bold uppercase tracking-widest text-sm">
              Our Portfolio
            </span>
            <h2 class="text-4xl md:text-5xl font-display font-bold text-white uppercase">
              Recent <span class="text-brand-orange">Builds</span>
            </h2>
            <div class="w-24 h-1 bg-brand-orange mt-6"></div>
          </div>

          <a href="{{ url('/portfolio') }}" class="group inline-flex items-center gap-2 text-white font-display uppercase tracking-wider text-sm hover:text-brand-orange transition-colors">
            View All Projects
            <div class="w-8 h-[1px] bg-white group-hover:bg-brand-orange transition-colors"></div>
          </a>
        </div>

        @if(count($posts) === 0)
          <p class="text-gray-500 text-center py-16">No portfolio items yet — check back soon!</p>
        @elseif(count($posts) > 0 && $search !== '' && count($visiblePosts) === 0)
          <p class="text-gray-500 text-center py-16">No matching recent builds for "{{ $search }}".</p>
        @elseif(count($visiblePosts) > 0)
          <div class="relative" wire:mouseenter="pauseAuto" wire:mouseleave="resumeAuto">
            <div class="relative h-[420px] md:h-[520px] overflow-hidden rounded-sm border bg-brand-gray transition-colors duration-300 border-gray-800" @if(!$autoPaused) wire:poll.4200ms="autoNext" @endif>
              @foreach($visiblePosts as $index => $post)
                @php
                    $images = $post['images'] ?? [];
                    $title = $post['title'] ?? '';
                    $postUrl = $post['url'] ?? '#';
                    $active = $index === $activeIndex;
                @endphp
                <a
                  key="{{ $post['id'] }}"
                  href="{{ $postUrl }}"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="absolute inset-0 block transition-opacity duration-700 {{ $active ? 'opacity-100 z-20' : 'opacity-0 z-10 pointer-events-none' }}"
                >
                  <img
                    src="{{ $images[0] ?? '' }}"
                    alt="{{ $title }}"
                    class="w-full h-full object-cover"
                    referrerpolicy="no-referrer"
                    loading="{{ $active ? 'eager' : 'lazy' }}"
                  />

                  <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/35 to-black/10"></div>

                  <div class="absolute bottom-0 left-0 right-0 p-6 md:p-10">
                    <div class="inline-flex items-center gap-2 bg-brand-orange/90 text-white text-[10px] font-bold uppercase tracking-widest px-3 py-1.5 rounded-sm mb-4">
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg> Featured Build
                    </div>

                    <h3 class="text-2xl md:text-4xl font-display font-bold text-white uppercase tracking-wide max-w-4xl leading-tight">
                      {{ $title }}
                    </h3>
                  </div>
                </a>
              @endforeach

              @if(count($visiblePosts) > 1)
                  <button
                    type="button"
                    wire:click="goPrev"
                    class="absolute left-4 md:left-6 top-1/2 -translate-y-1/2 z-30 w-11 h-11 rounded-full bg-black/45 border border-white/25 text-white hover:border-brand-orange hover:text-brand-orange transition-colors inline-flex items-center justify-center"
                    aria-label="Previous slide"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><path d="m15 18-6-6 6-6"/></svg>
                  </button>

                  <button
                    type="button"
                    wire:click="goNext"
                    class="absolute right-4 md:right-6 top-1/2 -translate-y-1/2 z-30 w-11 h-11 rounded-full bg-black/45 border border-white/25 text-white hover:border-brand-orange hover:text-brand-orange transition-colors inline-flex items-center justify-center"
                    aria-label="Next slide"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><path d="m9 18 6-6-6-6"/></svg>
                  </button>
              @endif
            </div>

            @if(count($visiblePosts) > 1)
              <div class="flex items-center justify-center gap-2 mt-4">
                @foreach($visiblePosts as $index => $post)
                  <button
                    key="{{ $post['id'] }}-dot"
                    type="button"
                    wire:click="setActiveIndex({{ $index }})"
                    class="h-2.5 rounded-full transition-all {{ $index === $activeIndex ? 'w-8 bg-brand-orange' : 'w-2.5 bg-gray-600 hover:bg-gray-400' }}"
                    aria-label="Go to slide {{ $index + 1 }}"
                  ></button>
                @endforeach
              </div>
            @endif
          </div>
        @endif
      </div>
    </section>
</div>
