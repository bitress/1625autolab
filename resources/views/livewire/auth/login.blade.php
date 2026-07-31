<div class="min-h-screen bg-brand-darker flex items-center justify-center px-4 pt-24 pb-16">
    <div class="w-full max-w-md">

        {{-- Page Header --}}
        <div class="text-center mb-10">
            <h1 class="text-4xl font-display font-black text-white uppercase tracking-tighter mb-2">
                Sign <span class="text-brand-orange">In</span>
            </h1>
            <p class="text-gray-400">Welcome back. Enter your credentials to continue.</p>
        </div>

        <div class="bg-brand-dark border border-gray-800 rounded-sm p-8 shadow-2xl">

            {{-- Email Verification Notice --}}
            @if ($verifyNotice || $resendMsg)
                <div class="flex items-center justify-between gap-3 bg-blue-500/10 border border-blue-500/30 text-blue-300 px-4 py-3 rounded-sm mb-6 text-sm">
                    <span>{{ $resendMsg ?: $verifyNotice }}</span>
                    @if ($verifyNotice && ! $resendMsg)
                        <button
                            wire:click="resendVerification"
                            wire:loading.attr="disabled"
                            type="button"
                            class="shrink-0 text-xs font-bold uppercase tracking-wider text-brand-orange hover:text-orange-300 disabled:opacity-50"
                        >
                            <span wire:loading wire:target="resendVerification">Sending...</span>
                            <span wire:loading.remove wire:target="resendVerification">
                                {{ $resendBusy ? 'Sending...' : 'Resend' }}
                            </span>
                        </button>
                    @endif
                </div>
            @endif

            {{-- Validation / Auth Error --}}
            @if ($errors->any())
                <div class="flex items-center gap-3 bg-red-500/10 border border-red-500/30 text-red-400 px-4 py-3 rounded-sm mb-6 text-sm">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4m0 4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                    </svg>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form wire:submit="login" class="space-y-5">

                {{-- Email --}}
                <div class="space-y-2">
                    <label for="login-email" class="block text-xs font-bold uppercase tracking-widest text-gray-400">
                        Email Address
                    </label>
                    <input
                        id="login-email"
                        type="email"
                        wire:model="email"
                        autocomplete="email"
                        placeholder="Please enter your email"
                        class="w-full bg-brand-darker border text-white px-4 py-3 focus:outline-none transition-colors rounded-sm
                               {{ $errors->has('email') ? 'border-red-500 focus:border-red-400' : 'border-gray-700 focus:border-brand-orange' }}"
                    />
                </div>

                {{-- Password --}}
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <label for="login-password" class="block text-xs font-bold uppercase tracking-widest text-gray-400">
                            Password
                        </label>
                        <a href="{{ route('login') }}"
                            class="text-xs text-brand-orange hover:text-orange-400 transition-colors">
                            Forgot Password?
                        </a>
                    </div>

                    <div class="relative">
                        <input
                            id="login-password"
                            type="{{ $showPassword ? 'text' : 'password' }}"
                            wire:model="password"
                            autocomplete="current-password"
                            placeholder="Please enter your password"
                            class="w-full bg-brand-darker border text-white px-4 py-3 pr-12 focus:outline-none transition-colors rounded-sm
                                   {{ $errors->has('password') ? 'border-red-500 focus:border-red-400' : 'border-gray-700 focus:border-brand-orange' }}"
                        />
                        <button
                            type="button"
                            wire:click="togglePassword"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300 transition-colors"
                            aria-label="Toggle password visibility"
                        >
                            @if ($showPassword)
                                {{-- Eye-off --}}
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13.875 18.825A10.05 10.05 0 0 1 12 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 0 1 4.31-5.192m2.33-1.606A9.956 9.956 0 0 1 12 5c4.478 0 8.268 2.943 9.543 7a9.975 9.975 0 0 1-1.804 3.282M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0zM3 3l18 18" />
                                </svg>
                            @else
                                {{-- Eye --}}
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            @endif
                        </button>
                    </div>
                </div>

                {{-- Submit --}}
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    class="w-full bg-brand-orange text-white font-bold uppercase tracking-widest py-4 hover:bg-orange-600 transition-colors disabled:opacity-60 disabled:cursor-not-allowed flex items-center justify-center gap-2 rounded-sm mt-2"
                >
                    {{-- Spinner while submitting --}}
                    <span wire:loading wire:target="login"
                        class="animate-spin border-2 border-white border-t-transparent rounded-full w-5 h-5">
                    </span>

                    {{-- Default label --}}
                    <span wire:loading.remove wire:target="login" class="flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3h7a3 3 0 0 1 3 3v1" />
                        </svg>
                        Sign In
                    </span>
                </button>
            </form>

            <p class="text-center text-gray-500 text-sm mt-6">
                Don't have an account?
                <a
                    href="{{ request()->query('redirect') ? '/register?redirect=' . urlencode(request()->query('redirect')) : '/register' }}"
                    class="text-brand-orange hover:text-orange-400 font-bold transition-colors"
                >
                    Create one
                </a>
            </p>

        </div>
    </div>
</div>
