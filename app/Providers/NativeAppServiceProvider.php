<?php

namespace App\Providers;

use Native\Laravel\Facades\Window;
use Native\Laravel\Menu\Menu;

class NativeAppServiceProvider
{
    /**
     * Executed once the native application has been booted.
     * Use this method to open windows, register global shortcuts, etc.
     */
    public function boot(): void
    {
        Menu::new()
            ->appMenu()
            ->submenu('About', Menu::new()
                ->link('https://twitter.com/lonnylot', 'Twitter')
                ->link('', 'Github')
            )
            ->register();

        Window::open()
            ->width(1024)
            ->height(800);
    }
}
