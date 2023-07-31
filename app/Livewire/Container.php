<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Illuminate\Support\Str;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use ZipArchive;

class Container extends Component
{
    public string $selectedVersion = '10.x';
    public string $doc = 'installation';

    public array $versions = [
        '10.x',
        '9.x',
        '8.x',
        '7.x',
    ];

    private $downloadLinks = [
        '10.x' => 'https://github.com/laravel/docs/archive/refs/heads/10.x.zip',
        '9.x' => 'https://github.com/laravel/docs/archive/refs/heads/9.x.zip',
        '8.x' => 'https://github.com/laravel/docs/archive/refs/heads/8.x.zip',
        '7.x' => 'https://github.com/laravel/docs/archive/refs/heads/7.x.zip',
    ];

    public function mount($version, $doc)
    {
        $this->changeVersion($version);
        $this->changeDoc($doc);
    }

    public function changeVersion(string $version)
    {
        $this->selectedVersion = Str::of($version)->afterLast('/');
    }

    public function changeDoc(string $doc)
    {
        $this->doc = Str::of($doc)->afterLast('/');
    }

    public function update()
    {
        // Loop through all download links, download the zip files, unzip, and store
        // the contents in the storage/docs directory.
        foreach ($this->downloadLinks as $version => $link) {
            $zip = file_get_contents($link);
            $zipPath = storage_path('docs/' . $version . '.zip');

            try {
                file_put_contents($zipPath, $zip);

                $zip = new ZipArchive;
                $res = $zip->open($zipPath);
                if ($res === true) {
                    $zip->extractTo(storage_path('docs'));
                    $zip->close();
                }

                Storage::disk('docs')->deleteDirectory($version);
                Storage::disk('docs')->move('docs-' . $version, $version);
            } finally {
                unlink($zipPath);
            }
        }
    }

    public function render()
    {
        return view('livewire.container')
            ->with(
                'nav', 
                $this->markdown(storage_path('docs/10.x/documentation.md'))
            );
    }

    private function markdown(string $path): string
    {
        $string = Str::of(file_get_contents($path))
                    ->replace('/docs/{{version}}/', '')
                    ->replaceMatches('#\[(.*?)\]\((.*?)\)#', function ($match) {
                        return '[' . $match[1] . '](' . $match[2] . '){wire:click.prevent="changeDoc(\'' . $match[2] . '\')" wire:key="' . $match[2] . '"}';
                    });
        $converter = new GithubFlavoredMarkdownConverter([]);
        $converter->getEnvironment()->addExtension(new AttributesExtension());

        return (string) $converter->convert($string);
    }
}
