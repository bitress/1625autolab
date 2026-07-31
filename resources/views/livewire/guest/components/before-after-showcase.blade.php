<div>
    @if(count($allCases) > 0)
    <section class="relative py-24 bg-[#0a0a0a] overflow-hidden border-y border-white/5">
      {{-- Background Decorative Elements --}}
      <div class="absolute top-0 left-0 w-full h-full pointer-events-none">
        <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-brand-orange/5 blur-[120px] rounded-full"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-white/5 blur-[120px] rounded-full"></div>
      </div>

      <div class="container mx-auto px-4 md:px-6 relative z-10">
        <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-8 mb-12">
          <div class="max-w-2xl">
            <div class="flex items-center gap-2 mb-4">
              <span class="w-8 h-[2px] bg-brand-orange"></span>
              <span class="text-brand-orange text-xs font-bold uppercase tracking-[0.3em]">
                Transformation Gallery
              </span>
            </div>
            <h2 class="text-white text-4xl md:text-6xl font-display font-black uppercase tracking-tighter leading-[0.9]">
              Visual <span class="text-brand-orange">Precision</span>
            </h2>
            <p class="text-gray-400 text-sm md:text-base mt-6 leading-relaxed max-w-xl">
              Drag the interactive slider to analyze the meticulous attention to detail 
              and technical superiority of our automotive finishes.
            </p>
          </div>

          <div class="hidden lg:flex items-baseline gap-2 font-display italic">
            <span class="text-brand-orange text-6xl font-black">{{ $activeIndex + 1 }}</span>
            <span class="text-gray-700 text-3xl font-black">/ {{ count($cases) }}</span>
          </div>
        </div>

        {{-- Vehicle Search Filter --}}
        <div class="mb-8 flex items-center gap-3 max-w-md">
          <div class="relative flex-1">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500 pointer-events-none"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <input
              type="text"
              wire:model.live="searchQuery"
              placeholder="Search by vehicle (e.g. Honda Civic, Fortuner…)"
              class="w-full bg-white/[0.04] border border-white/10 rounded-sm pl-9 pr-8 py-2.5 text-sm text-white placeholder-gray-600 focus:outline-none focus:border-brand-orange/50"
            />
            @if($searchQuery)
              <button
                type="button"
                wire:click="clearSearch"
                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-500 hover:text-white transition-colors"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
              </button>
            @endif
          </div>
          @if($searchQuery)
            <span class="text-xs text-gray-500 whitespace-nowrap">
              {{ count($cases) }} result{{ count($cases) !== 1 ? 's' : '' }}
            </span>
          @endif
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-12 gap-8 items-stretch">
          @if(!$active)
            <div class="xl:col-span-12 py-16 text-center text-gray-500">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-8 h-8 mx-auto mb-3 opacity-30"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
              <p class="text-sm">No builds found for "{{ $searchQuery }}". Try a different vehicle.</p>
            </div>
          @else
          {{-- Main Slider Area --}}
          <div class="xl:col-span-8 flex flex-col">
            <div class="relative aspect-[16/10] xl:aspect-video rounded-sm border border-white/10 bg-black overflow-hidden shadow-2xl group">
              {{-- Labels --}}
              <div class="absolute top-6 left-6 z-30 pointer-events-none">
                <div class="bg-black/60 backdrop-blur-md border border-white/10 px-4 py-2 rounded-sm">
                  <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">In-Take State</p>
                </div>
              </div>
              <div class="absolute top-6 right-6 z-30 pointer-events-none">
                <div class="bg-brand-orange/80 backdrop-blur-md border border-white/20 px-4 py-2 rounded-sm shadow-lg">
                  <p class="text-[10px] font-bold text-white uppercase tracking-widest">Final Finish</p>
                </div>
              </div>

              {{-- Slider Images --}}
              <div class="absolute inset-0 w-full h-full">
                <img
                  src="{{ $active['beforeUrl'] }}"
                  alt="Original"
                  class="w-full h-full object-cover"
                  draggable="false"
                />
              </div>

              <div
                class="absolute inset-0 z-10 overflow-hidden border-r-2 border-white/50"
                style="width: {{ $splitPercent }}%"
              >
                <img
                  src="{{ $active['afterUrl'] }}"
                  alt="Transformed"
                  class="absolute inset-0 w-full h-full object-cover max-w-none"
                  style="width: {{ $splitPercent > 0 ? (100 * (100 / $splitPercent)) : 1000 }}%"
                  draggable="false"
                />
              </div>

              {{-- Slider Handle --}}
              <div
                class="absolute top-0 bottom-0 z-20 pointer-events-none flex items-center justify-center"
                style="left: {{ $splitPercent }}%"
              >
                <div class="relative flex items-center justify-center w-1 h-full">
                   <div class="absolute w-12 h-12 rounded-full border-2 border-white bg-brand-orange text-white flex items-center justify-center shadow-[0_0_30px_rgba(243,111,33,0.6)] group-hover:scale-110 transition-transform cursor-col-resize pointer-events-auto">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><polyline points="18 8 22 12 18 16"/><polyline points="6 8 2 12 6 16"/><line x1="2" x2="22" y1="12" y2="12"/></svg>
                    {{-- Pulsing effect --}}
                    <div class="absolute inset-0 rounded-full bg-brand-orange animate-ping opacity-20"></div>
                  </div>
                </div>
              </div>

              {{-- Hidden Range Input --}}
              <input
                type="range"
                min="0"
                max="100"
                wire:model.live="splitPercent"
                aria-label="Drag to compare before and after"
                class="absolute inset-0 w-full h-full opacity-0 cursor-col-resize z-40"
              />
            </div>

            {{-- Case Info Footer --}}
            <div class="mt-6 p-6 bg-white/[0.02] border border-white/5 rounded-sm">
              <div class="flex items-start justify-between gap-4 flex-wrap">
                <h3 class="text-white text-xl font-bold uppercase tracking-wide">{{ $active['title'] }}</h3>
                @if(!empty($active['vehicleMake']) || !empty($active['vehicleModel']))
                  <span class="text-[10px] font-bold uppercase tracking-widest text-brand-orange/80 bg-brand-orange/10 border border-brand-orange/20 px-2 py-1 rounded-sm shrink-0">
                    {{ implode(' ', array_filter([$active['vehicleMake'] ?? '', $active['vehicleModel'] ?? ''])) }}
                  </span>
                @endif
              </div>
              @if(!empty($active['description']))
                <p class="text-gray-500 text-sm mt-2 leading-relaxed">{{ $active['description'] }}</p>
              @endif
            </div>
          </div>

          {{-- Sidebar Project List --}}
          <div class="xl:col-span-4 flex flex-col gap-4">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-widest px-2">Project Selection</p>
            <div class="flex flex-col gap-3 max-h-[600px] overflow-y-auto pr-2 custom-scrollbar">
              @foreach($cases as $idx => $item)
                @php $isSelected = $idx === $activeIndex; @endphp
                <button
                  type="button"
                  wire:click="selectCase({{ $idx }})"
                  class="group flex items-center gap-4 p-3 rounded-sm border transition-all duration-300 {{ $isSelected ? 'bg-brand-orange border-brand-orange' : 'bg-white/[0.03] border-white/5 hover:border-white/20' }}"
                >
                  <div class="relative w-20 h-14 shrink-0 overflow-hidden rounded-sm">
                    <img
                      src="{{ $item['afterUrl'] }}"
                      alt=""
                      class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110 {{ !$isSelected ? 'opacity-60' : '' }}"
                    />
                  </div>
                  <div class="flex-1 text-left">
                    <p class="text-xs font-bold uppercase tracking-wide truncate {{ $isSelected ? 'text-white' : 'text-gray-300 group-hover:text-white' }}">
                      {{ $item['title'] }}
                    </p>
                    <p class="text-[10px] mt-1 {{ $isSelected ? 'text-white/70' : 'text-gray-500' }}">
                      @if(!empty($item['vehicleMake']) && !empty($item['vehicleModel']))
                        {{ $item['vehicleMake'] }} {{ $item['vehicleModel'] }}
                      @else
                        Case Study 0{{ $idx + 1 }}
                      @endif
                    </p>
                  </div>
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 transition-transform {{ $isSelected ? 'text-white translate-x-1' : 'text-gray-700' }}"><path d="m9 18 6-6-6-6"/></svg>
                </button>
              @endforeach
            </div>
          </div>
          @endif
        </div>
      </div>

      <style>
        .custom-scrollbar::-webkit-scrollbar {
          width: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
          background: rgba(255, 255, 255, 0.02);
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
          background: rgba(243, 111, 33, 0.3);
          border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
          background: rgba(243, 111, 33, 0.5);
        }
      </style>
    </section>
    @endif
</div>
