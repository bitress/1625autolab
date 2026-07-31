<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;

#[Layout('components.layouts.guest')]
class Login extends Component
{
    #[Rule('required|email')]
    public string $email = '';

    #[Rule('required|string|min:6')]
    public string $password = '';

    /** Toggle password visibility — server-side so no Alpine needed. */
    public bool $showPassword = false;

    /** Flash a notice about email verification (mirrors React's verifyMsg). */
    public string $verifyNotice = '';

    public bool $resendBusy = false;

    public string $resendMsg = '';

    public function mount(): void
    {
        // Pre-fill email from query string (mirrors React's `params.get('email')`)
        $this->email = request()->query('email', '');

        if (request()->query('verify_notice') === '1') {
            $this->verifyNotice = 'Registration complete. Please verify your email before signing in.';
        }
    }

    public function togglePassword(): void
    {
        $this->showPassword = ! $this->showPassword;
    }

    public function login(): void
    {
        $this->validate();

        $key = 'login:'.Str::lower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            $this->addError('email', "Too many login attempts. Please wait {$seconds} seconds.");

            return;
        }

        $credentials = [
            'email' => $this->email,
            'password' => $this->password,
        ];

        if (! Auth::attempt($credentials, remember: true)) {
            RateLimiter::hit($key, 60);
            $this->addError('email', 'These credentials do not match our records.');
            $this->password = '';

            return;
        }

        RateLimiter::clear($key);
        request()->session()->regenerate();

        $user = Auth::user();

        session()->flash('toast_success', 'Welcome back, '.explode(' ', $user->name)[0].'!');

        $redirect = request()->query('redirect', '');

        if ($redirect) {
            $this->redirect($redirect, navigate: true);

            return;
        }

        $this->redirect(
            $user->role === 'client' ? '/client/dashboard' : '/admin',
            navigate: true,
        );
    }

    public function resendVerification(): void
    {
        $this->validate(['email' => 'required|email']);

        $this->resendBusy = true;

        try {
            $user = User::where('email', $this->email)->first();

            if (! $user) {
                $this->addError('email', 'No account found with that email address.');

                return;
            }

            if ($user->hasVerifiedEmail()) {
                $this->resendMsg = 'Your email is already verified. You can sign in now.';

                return;
            }

            $user->sendEmailVerificationNotification();
            $this->resendMsg = 'Verification email sent. Please check your inbox.';
        } finally {
            $this->resendBusy = false;
        }
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
