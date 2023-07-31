<?php

use App\Livewire\Container;
use App\Livewire\Documentation;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', Container::class)
    ->name('home')
    ->defaults('version', '10.x')
    ->defaults('doc', 'installation');

Route::get('/docs/{version}/{doc}', Documentation::class)
    ->name('documentation')
    ->defaults('version', '10.x')
    ->defaults('doc', 'installation');
