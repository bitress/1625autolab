<div>
    @if(count($items) > 0)
    <section class="py-24 bg-brand-dark relative overflow-hidden">
      {{-- Subtle background accents --}}
      <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-[-5%] left-[-5%] w-96 h-96 bg-brand-orange/5 rounded-full blur-3xl"></div>
        <div class="absolute bottom-[-5%] right-[-5%] w-96 h-96 bg-brand-orange/5 rounded-full blur-3xl"></div>
      </div>

      <div class="container mx-auto px-4 md:px-6 relative z-10">

        {{-- Section header --}}
        <div class="text-center max-w-3xl mx-auto mb-16 space-y-4">
          <span class="text-brand-orange font-bold uppercase tracking-widest text-sm">
            Got Questions?
          </span>
          <h2 class="text-4xl md:text-5xl font-display font-black text-white uppercase tracking-tighter">
            Frequently Asked <span class="text-brand-orange">Questions</span>
          </h2>
          <div class="w-24 h-1 bg-brand-orange mx-auto mt-6"></div>
        </div>

        {{-- Accordion --}}
        <div class="max-w-3xl mx-auto space-y-3 mb-12">
          @foreach($preview as $faq)
            <div
              key="{{ $faq['id'] }}"
              class="bg-brand-darker border border-gray-800 rounded-sm overflow-hidden hover:border-gray-700 transition-colors"
            >
              <button
                type="button"
                class="w-full flex items-center justify-between gap-4 px-6 py-5 text-left"
                wire:click="toggle({{ $faq['id'] }})"
              >
                <span class="text-white font-bold leading-snug">{{ $faq['question'] }}</span>
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 text-brand-orange shrink-0 transition-transform duration-200 {{ $openId === $faq['id'] ? 'rotate-180' : '' }}"><path d="m6 9 6 6 6-6"/></svg>
              </button>
              @if($openId === $faq['id'])
                <div class="px-6 pb-5 border-t border-gray-800">
                  <p class="text-gray-400 leading-relaxed pt-4">{{ $faq['answer'] }}</p>
                </div>
              @endif
            </div>
          @endforeach
        </div>

        {{-- CTA --}}
        <div class="text-center space-y-4">
          @if($totalFaqs > 5)
            <p class="text-gray-500 text-sm">
              Showing 5 of {{ $totalFaqs }} questions
            </p>
          @endif
          <a
            href="{{ url('/faq') }}"
            class="inline-flex items-center gap-2 bg-brand-orange text-white px-8 py-3 font-bold uppercase tracking-widest hover:bg-orange-600 transition-colors rounded-sm"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>
            View All FAQs
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
          </a>
        </div>

      </div>
    </section>
    @endif
</div>
