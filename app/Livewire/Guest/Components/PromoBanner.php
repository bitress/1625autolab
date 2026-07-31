<?php

namespace App\Livewire\Guest\Components;

use Livewire\Component;

class PromoBanner extends Component
{
    public ?array $offer = [
        'badgeText' => 'Limited Time',
        'title' => 'Special Promotion',
        'subtitle' => 'Don\'t miss out on this deal',
        'description' => 'Get the best services for your vehicle at discounted rates.',
        'ctaUrl' => '#contact',
        'ctaText' => 'Claim Your Offer',
    ];

    public function render()
    {
        return view('livewire.guest.components.promo-banner');
    }
}
