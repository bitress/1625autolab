<?php

namespace App\Livewire\Guest\Components;

use Livewire\Component;

class Testimonials extends Component
{
    public array $testimonials = [
        [
            'id' => 1,
            'name' => 'Michael R.',
            'role' => 'Civic Owner',
            'imageUrl' => 'https://images.unsplash.com/photo-1599566150163-29194dcaad36?w=150&q=80',
            'rating' => 5,
            'content' => 'The headlight retrofit completely transformed my night driving experience. Highly recommend 1625 Auto Lab!',
        ],
        [
            'id' => 2,
            'name' => 'Sarah J.',
            'role' => 'Fortuner Owner',
            'imageUrl' => '',
            'rating' => 5,
            'content' => 'Just got the Android Headunit installed. The wireless CarPlay works flawlessly, and it looks completely OEM.',
        ],
        [
            'id' => 3,
            'name' => 'David L.',
            'role' => 'Mustang Owner',
            'imageUrl' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&q=80',
            'rating' => 5,
            'content' => 'Amazing attention to detail with the ambient lighting setup. The interior feels like a luxury car now.',
        ],
    ];

    public function render()
    {
        return view('livewire.guest.components.testimonials');
    }
}
