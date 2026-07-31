<?php

namespace App\Livewire\Guest\Components;

use Livewire\Component;

class HomeFaqSection extends Component
{
    public $openId = null;

    public array $items = [
        [
            'id' => 1,
            'question' => 'How long does a headlight retrofit take?',
            'answer' => 'A typical retrofit takes about 4 to 6 hours depending on the complexity of your vehicle and the components chosen. We require an appointment to guarantee same-day turnaround.',
        ],
        [
            'id' => 2,
            'question' => 'Will installing an Android Headunit void my car warranty?',
            'answer' => 'Our installations use plug-and-play harnesses tailored to your specific vehicle model, meaning no wires are cut or spliced. This preserves your factory wiring and typically does not void the warranty.',
        ],
        [
            'id' => 3,
            'question' => 'Do you offer warranty on your lighting upgrades?',
            'answer' => 'Yes, all our retrofit packages and LED upgrades come with a standard 1-year warranty covering both parts and labor for peace of mind.',
        ],
        [
            'id' => 4,
            'question' => 'Do I need to leave my car overnight?',
            'answer' => 'Most services, including headunits and basic retrofits, are completed within the same day. However, full build packages or complex custom fabrication might require leaving the car overnight.',
        ],
        [
            'id' => 5,
            'question' => 'Can you restore heavily yellowed or oxidized headlights?',
            'answer' => 'Absolutely. We offer multi-stage headlight restoration that removes oxidation, refines the plastic, and seals it with a UV protective clear coat to prevent it from fading again.',
        ],
        [
            'id' => 6,
            'question' => 'What is the difference between HID and LED?',
            'answer' => 'LEDs offer instant brightness, longer lifespan, and lower power consumption. HIDs generally provide a slightly further throw in projector housings, though high-end LEDs have largely closed this gap.',
        ],
    ];

    public function toggle($id)
    {
        $this->openId = $this->openId === $id ? null : $id;
    }

    public function render()
    {
        $preview = array_slice($this->items, 0, 5);

        return view('livewire.guest.components.home-faq-section', [
            'preview' => $preview,
            'totalFaqs' => count($this->items),
        ]);
    }
}
