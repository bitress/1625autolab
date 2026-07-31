<?php

namespace App\Livewire\Guest\Components;

use Livewire\Component;

class ServicesGrid extends Component
{
    public array $services = [
        [
            'icon' => 'Lightbulb',
            'title' => 'Headlight Retrofit',
            'description' => 'Upgrade your stock headlights to projector or bi-led setups for dramatically improved night visibility and a sharper, more premium look.',
            'startingPrice' => '₱13,750',
            'duration' => '4-6 Hours',
            'slug' => 'headlight-retrofit',
        ],
        [
            'icon' => 'MonitorPlay',
            'title' => 'Android Headunit',
            'description' => 'Replace your factory head unit with a modern Android display featuring wireless CarPlay, Android Auto, and full OEM integration.',
            'startingPrice' => '₱8,500',
            'duration' => '2–3 Hours',
            'slug' => 'android-headunit',
        ],
        [
            'icon' => 'Wrench',
            'title' => 'General Retrofitting',
            'description' => 'Custom interior and exterior retrofits tailored to your vehicle — DRL wiring, ambient lighting, dash cam installation, and more.',
            'startingPrice' => '₱3,500',
            'duration' => '1–4 Hours',
            'slug' => 'general-retrofitting',
        ],
        [
            'icon' => 'Zap',
            'title' => 'HID / LED Upgrade',
            'description' => 'Direct-fit HID and LED bulb conversions for improved lumens output without full projector housings — fast, clean, and effective.',
            'startingPrice' => '₱4,200',
            'duration' => '1–2 Hours',
            'slug' => 'hid-led-upgrade',
        ],
        [
            'icon' => 'ShieldAlert',
            'title' => 'Calibration & Alignment',
            'description' => 'Factory-spec aiming and ADAS calibration after headlight retrofit to ensure beam pattern compliance and safety system accuracy.',
            'startingPrice' => '₱2,000',
            'duration' => '30–60 Min',
            'slug' => 'calibration-alignment',
        ],
        [
            'icon' => 'CarFront',
            'title' => 'Full Build Package',
            'description' => 'Complete head-to-tail transformation: retrofit, headunit, ambient lighting, and calibration bundled into one seamless shop experience.',
            'startingPrice' => '₱22,000',
            'duration' => 'Full Day',
            'slug' => 'full-build-package',
        ],
    ];

    public function render()
    {
        return view('livewire.guest.components.services-grid');
    }
}
