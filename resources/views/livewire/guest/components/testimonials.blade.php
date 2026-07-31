<div>
    @if(count($testimonials) > 0)
    <section class="py-24 bg-brand-darker relative overflow-hidden">
      {{-- Background Elements --}}
      <div class="absolute top-0 left-0 w-full h-full overflow-hidden z-0">
        <div class="absolute top-[-10%] right-[-5%] w-96 h-96 bg-brand-orange/5 rounded-full blur-3xl"></div>
        <div class="absolute bottom-[-10%] left-[-5%] w-96 h-96 bg-brand-orange/5 rounded-full blur-3xl"></div>
      </div>

      <div class="container mx-auto px-4 md:px-6 relative z-10">
        <div class="text-center max-w-3xl mx-auto mb-16 space-y-4">
          <span class="text-brand-orange font-bold uppercase tracking-widest text-sm">
            Client Feedback
          </span>
          <h2 class="text-4xl md:text-5xl font-display font-black text-white uppercase tracking-tighter">
            What Our <span class="text-brand-orange">Clients Say</span>
          </h2>
          <div class="w-24 h-1 bg-brand-orange mx-auto mt-6"></div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          @foreach($testimonials as $testimonial)
            <div
              key="{{ $testimonial['id'] }}"
              class="bg-brand-dark border border-gray-800 p-8 rounded-sm hover:border-brand-orange/50 transition-colors duration-300 flex flex-col h-full"
            >
              <div class="flex items-center gap-4 mb-6">
                @if(!empty($testimonial['imageUrl']))
                  <img
                    src="{{ $testimonial['imageUrl'] }}"
                    alt="{{ $testimonial['name'] }}"
                    class="w-16 h-16 rounded-full object-cover border-2 border-gray-800"
                    referrerpolicy="no-referrer"
                    onerror="this.style.display='none'"
                  />
                @else
                  <div class="w-16 h-16 rounded-full bg-brand-orange/20 border-2 border-brand-orange/40 flex items-center justify-center shrink-0">
                    <span class="text-brand-orange font-black text-xl uppercase">
                      {{ substr($testimonial['name'], 0, 1) }}
                    </span>
                  </div>
                @endif
                <div>
                  <h3 class="text-white font-bold text-lg">{{ $testimonial['name'] }}</h3>
                  <p class="text-brand-orange text-sm font-bold uppercase tracking-widest">{{ $testimonial['role'] }}</p>
                </div>
              </div>

              <div class="flex gap-1 mb-4">
                @for($i = 0; $i < $testimonial['rating']; $i++)
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-brand-orange fill-brand-orange"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                @endfor
              </div>

              <p class="text-gray-400 italic leading-relaxed flex-grow">
                "{{ $testimonial['content'] }}"
              </p>
            </div>
          @endforeach
        </div>
      </div>
    </section>
    @endif
</div>
