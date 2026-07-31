<?php

namespace App\Livewire;

use Illuminate\Contracts\View\Factory;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('1625 Autolab')]
class HomePage extends Component
{
    #[Layout('components.layouts.guest')]
    public function render(): Factory|\Illuminate\Contracts\View\View|View
    {
        return view('livewire.home-page');
    }
}
