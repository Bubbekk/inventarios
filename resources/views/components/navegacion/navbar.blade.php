@php
    $navegacion = app(App\Services\Navegacion\NavegacionService::class);
    $secciones = $navegacion->seccionesVisibles();
    $actual = $navegacion->seccionActual();
@endphp

<flux:navbar class="-mb-px max-lg:hidden">
    @foreach ($secciones as $seccion)
        <flux:navbar.item
            :icon="$seccion->icono"
            :href="route($seccion->ruta)"
            :current="$actual?->clave === $seccion->clave"
            wire:navigate
        >
            {{ __($seccion->titulo) }}
        </flux:navbar.item>
    @endforeach
</flux:navbar>
