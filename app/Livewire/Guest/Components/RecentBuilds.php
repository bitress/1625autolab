<?php

namespace App\Livewire\Guest\Components;

use Livewire\Attributes\Url;
use Livewire\Component;

class RecentBuilds extends Component
{
    #[Url(as: 'buildSearch')]
    public string $buildSearch = '';

    #[Url(as: 'portfolioSearch')]
    public string $portfolioSearch = '';

    public int $activeIndex = 0;

    public bool $autoPaused = false;

    // Dummy data instead of backend fetch
    public array $posts = [
        [
            'id' => '1',
            'message' => 'Amazing headlight retrofit for a Civic. Projector upgrade.',
            'images' => ['https://images.unsplash.com/photo-1611016186353-9af58c69a533?w=800&q=80'],
            'url' => '#',
            'title' => 'Honda Civic Projector Retrofit',
        ],
        [
            'id' => '2',
            'message' => 'Full ambient lighting install for a Mustang. Custom colors.',
            'images' => ['https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?w=800&q=80'],
            'url' => '#',
            'title' => 'Mustang Ambient Lighting',
        ],
        [
            'id' => '3',
            'message' => 'Android Headunit upgrade in a Fortuner with wireless CarPlay.',
            'images' => ['https://images.unsplash.com/photo-1590483863483-305f6396fdf6?w=800&q=80'],
            'url' => '#',
            'title' => 'Fortuner Android Headunit',
        ],
    ];

    public function pauseAuto()
    {
        $this->autoPaused = true;
    }

    public function resumeAuto()
    {
        $this->autoPaused = false;
    }

    public function goNext()
    {
        $visibleCount = count($this->getVisiblePostsProperty());
        if ($visibleCount === 0) {
            return;
        }

        $this->activeIndex = ($this->activeIndex + 1) % $visibleCount;
    }

    public function autoNext()
    {
        if ($this->autoPaused) {
            return;
        }
        $this->goNext();
    }

    public function goPrev()
    {
        $visibleCount = count($this->getVisiblePostsProperty());
        if ($visibleCount === 0) {
            return;
        }

        $this->activeIndex = ($this->activeIndex - 1 + $visibleCount) % $visibleCount;
    }

    public function setActiveIndex($index)
    {
        $this->activeIndex = $index;
    }

    public function getVisiblePostsProperty()
    {
        $search = trim($this->buildSearch !== '' ? $this->buildSearch : $this->portfolioSearch);

        if ($search === '') {
            return $this->posts;
        }

        $needle = mb_strtolower($search);

        return array_filter($this->posts, function ($post) use ($needle) {
            $haystack = mb_strtolower(($post['message'] ?? '').' '.($post['title'] ?? ''));

            return str_contains($haystack, $needle);
        });
    }

    public function render()
    {
        $visiblePosts = array_values($this->getVisiblePostsProperty());

        // Prevent out of bounds if array shrinks
        if ($this->activeIndex >= count($visiblePosts)) {
            $this->activeIndex = 0;
        }

        return view('livewire.guest.components.recent-builds', [
            'visiblePosts' => $visiblePosts,
            'search' => trim($this->buildSearch !== '' ? $this->buildSearch : $this->portfolioSearch),
        ]);
    }
}
