@php
    $seccion = app(App\Services\Navegacion\NavegacionService::class)->seccionActual();
@endphp

@if ($seccion && $seccion->modulos !== [])
    <flux:navlist>
        <flux:navlist.group :heading="__($seccion->titulo)">
            @foreach ($seccion->modulos as $modulo)
                <flux:navlist.item
                    :icon="$modulo->icono"
                    :href="route($modulo->ruta)"
                    :current="request()->routeIs($modulo->ruta)"
                    wire:navigate
                >
                    {{ __($modulo->titulo) }}
                </flux:navlist.item>
            @endforeach
        </flux:navlist.group>
    </flux:navlist>
@endif
