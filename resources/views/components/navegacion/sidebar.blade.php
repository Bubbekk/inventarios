@php
    $navegacion = app(App\Services\Navegacion\NavegacionService::class);
    $secciones = $navegacion->seccionesVisibles();
@endphp

<flux:sidebar.nav>
    @foreach ($secciones as $seccion)
        @if ($seccion->modulos === [])
            <flux:sidebar.item
                :icon="$seccion->icono"
                :href="route($seccion->ruta)"
                :current="request()->routeIs($seccion->ruta)"
                wire:navigate
            >
                {{ __($seccion->titulo) }}
            </flux:sidebar.item>
        @else
            <flux:sidebar.group expandable :heading="__($seccion->titulo)" class="grid">
                @foreach ($seccion->modulos as $modulo)
                    <flux:sidebar.item
                        :icon="$modulo->icono"
                        :href="route($modulo->ruta)"
                        :current="request()->routeIs($modulo->ruta)"
                        wire:navigate
                    >
                        {{ __($modulo->titulo) }}
                    </flux:sidebar.item>
                @endforeach
            </flux:sidebar.group>
        @endif
    @endforeach
</flux:sidebar.nav>
