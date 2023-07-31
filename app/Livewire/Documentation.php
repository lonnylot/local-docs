<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Str;
use Livewire\Attributes\Reactive;

class Documentation extends Component
{
    #[Reactive]
    public string $version = '10.x';

    #[Reactive]
    public string $doc = 'installation';

    public function mount($version, $doc)
    {
        $this->version = Str::of($version)->afterLast('/');
        $this->doc = Str::of($doc)->afterLast('/');
    }

    public function render()
    {
        $path = storage_path('docs/' . $this->version . '/' . $this->doc . '.md');

        abort_unless(file_exists($path), 404);

        return view('livewire.documentation')
            ->with(
                'content',
                Str::of(file_get_contents($path))
                    ->replace('{{version}}', $this->version)
                    ->markdown()
            );
    }
}
