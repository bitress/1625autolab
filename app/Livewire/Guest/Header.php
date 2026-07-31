<?php

namespace App\Livewire\Guest;

use App\Models\User;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class Header extends Component
{
    /**
     * Bound to the "search your car make/model" input.
     * Mirrors the React version's `buildSearch` state, seeded from the
     * `buildSearch` (or legacy `portfolioSearch`) query string param.
     */
    public string $buildSearch = '';

    /**
     * Item count shown on the cart badge (desktop + mobile).
     */
    public int $cartItemsCount = 0;

    /**
     * Mobile nav drawer + account dropdown open state.
     * Handled server-side via wire:click / wire:click.away instead of Alpine.
     */
    public bool $mobileOpen = false;

    public bool $dropdownOpen = false;

    /**
     * Primary nav links, equivalent to the React `navLinks` array.
     */
    public array $navLinks = [
        ['name' => 'Home', 'href' => '/'],
        ['name' => 'Services', 'href' => '/services'],
        ['name' => 'Products', 'href' => '/products'],
        ['name' => 'Portfolio', 'href' => '/portfolio'],
        ['name' => 'About', 'href' => '/about'],
        ['name' => 'Contact', 'href' => '/contact'],
    ];

    /**
     * Links shown in the account dropdown for role === 'client'.
     * Adjust the route names / icon names to match your app.
     */
    public array $clientMenu = [
        ['label' => 'Dashboard', 'href' => '/client/dashboard', 'icon' => 'dashboard'],
        ['label' => 'My Bookings', 'href' => '/client/bookings', 'icon' => 'calendar'],
        ['label' => 'My Orders', 'href' => '/client/orders', 'icon' => 'package'],
        ['label' => 'Profile', 'href' => '/client/profile', 'icon' => 'user'],
    ];

    public function mount(): void
    {
        $this->buildSearch = request()->query('buildSearch')
            ?? request()->query('portfolioSearch')
            ?? '';

        $this->refreshCart();
    }

    /**
     * Recompute the cart badge count. Listens for a browser-dispatched
     * 'cart-updated' event so the badge stays in sync without a full page
     * reload — the Livewire equivalent of the React `apollo:cart-updated`
     * window event listener.
     *
     * Swap the session-based read below for your real Cart service/model.
     */
    #[On('cart-updated')]
    public function refreshCart(): void
    {
        $cart = session('cart', []);

        $this->cartItemsCount = collect($cart)->sum(fn ($line) => $line['quantity'] ?? 1);
    }

    public function toggleMobileMenu(): void
    {
        $this->mobileOpen = ! $this->mobileOpen;
        $this->dropdownOpen = false;

        // Let JS toggle body scroll-lock without needing Alpine.
        $this->dispatch('mobile-menu-toggled', open: $this->mobileOpen);
    }

    public function closeMobileMenu(): void
    {
        $this->mobileOpen = false;
        $this->dispatch('mobile-menu-toggled', open: false);
    }

    public function toggleDropdown(): void
    {
        $this->dropdownOpen = ! $this->dropdownOpen;
    }

    public function closeDropdown(): void
    {
        $this->dropdownOpen = false;
    }

    /**
     * Handles the search form submit (desktop + mobile).
     * Equivalent to `handleBuildSearchSubmit` in the React component.
     */
    public function search()
    {
        $query = trim($this->buildSearch);
        $this->mobileOpen = false;

        return redirect()->route('home', $query !== '' ? ['buildSearch' => $query] : []);
    }

    /**
     * Equivalent to `handleLogout` in the React component.
     */
    public function logout()
    {
        Auth::guard('web')->logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Builds an avatar URL for the given user: their stored avatar_url,
     * falling back to a Dicebear identicon keyed off id/name/email —
     * mirrors `getDicebearAvatarDataUri` from the React version.
     */
    public function avatarUrl(?User $user): string
    {
        if ($user?->avatar_url) {
            return $user->avatar_url;
        }

        $seed = urlencode((string) ($user?->id ?? $user?->email ?? $user?->name ?? 'guest'));

        return "https://api.dicebear.com/9.x/initials/svg?seed={$seed}";
    }

    public function render(): Factory|\Illuminate\Contracts\View\View|View
    {
        return view('components.guest.header', [
            'user' => Auth::user(),
        ]);
    }
}
