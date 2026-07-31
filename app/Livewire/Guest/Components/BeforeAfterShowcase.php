<?php

namespace App\Livewire\Guest\Components;

use Livewire\Component;

class BeforeAfterShowcase extends Component
{
    public string $searchQuery = '';

    public int $activeIndex = 0;

    public int $splitPercent = 50;

    public array $allCases = [
        [
            'id' => '1',
            'title' => 'Paint Correction & Ceramic Coating',
            'beforeUrl' => 'https://images.unsplash.com/photo-1552519507-da3b142c6e3d?w=800&q=80',
            'afterUrl' => 'https://images.unsplash.com/photo-1494976388531-d1058494cdd8?w=800&q=80',
            'description' => 'Restored depth and clarity to a heavily swirled clear coat.',
            'vehicleMake' => 'Porsche',
            'vehicleModel' => '911',
        ],
        [
            'id' => '2',
            'title' => 'Headlight Restoration & Retrofit',
            'beforeUrl' => 'https://images.unsplash.com/photo-1611016186353-9af58c69a533?w=800&q=80',
            'afterUrl' => 'https://images.unsplash.com/photo-1611016186353-9af58c69a533?w=800&q=80',
            'description' => 'Eliminated heavy oxidation and upgraded with bi-led projectors.',
            'vehicleMake' => 'Honda',
            'vehicleModel' => 'Civic',
        ],
    ];

    public function updatedSearchQuery()
    {
        $this->activeIndex = 0;
        $this->splitPercent = 50;
    }

    public function clearSearch()
    {
        $this->searchQuery = '';
        $this->activeIndex = 0;
        $this->splitPercent = 50;
    }

    public function selectCase($idx)
    {
        $this->activeIndex = $idx;
        $this->splitPercent = 50;
    }

    public function getFilteredCasesProperty()
    {
        $q = mb_strtolower(trim($this->searchQuery));
        if (! $q) {
            return $this->allCases;
        }

        return array_filter($this->allCases, function ($c) use ($q) {
            $make = mb_strtolower($c['vehicleMake'] ?? '');
            $model = mb_strtolower($c['vehicleModel'] ?? '');
            $title = mb_strtolower($c['title'] ?? '');

            return str_contains($make, $q) || str_contains($model, $q) || str_contains($title, $q);
        });
    }

    public function render()
    {
        $cases = array_values($this->getFilteredCasesProperty());
        $active = $cases[$this->activeIndex] ?? null;

        return view('livewire.guest.components.before-after-showcase', [
            'cases' => $cases,
            'active' => $active,
        ]);
    }
}
