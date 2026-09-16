<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('/', 'pages::inicio')->name('inicio');

    Route::livewire('/tipos', 'pages::tipos.indice')
        ->middleware('permission:tipos.ver')
        ->name('tipos.indice');

    Route::livewire('/procesos', 'pages::procesos.indice')
        ->middleware('permission:procesos.ver')
        ->name('procesos.indice');

    Route::livewire('/items', 'pages::items.indice')
        ->middleware('permission:items.ver')
        ->name('items.indice');

    Route::livewire('/items/{item}', 'pages::items.ficha')
        ->middleware('permission:items.ver')
        ->name('items.ficha');

    Route::livewire('/funcionarios', 'pages::funcionarios.indice')
        ->middleware('permission:funcionarios.ver')
        ->name('funcionarios.indice');

    Route::livewire('/ubicaciones', 'pages::ubicaciones.indice')
        ->middleware('permission:ubicaciones.ver')
        ->name('ubicaciones.indice');

    Route::livewire('/usuarios', 'pages::usuarios.indice')
        ->middleware('permission:usuarios.ver')
        ->name('usuarios.indice');
});

require __DIR__.'/settings.php';
