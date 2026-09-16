@props([
    'columnas' => 4,
    'limpiar' => 'limpiarFiltros',
])

@php
    // Las clases de Tailwind deben ser literales para que el compilador las conserve.
    $grilla = match ((int) $columnas) {
        2 => 'md:grid-cols-2',
        3 => 'md:grid-cols-3',
        4 => 'md:grid-cols-4',
        default => 'md:grid-cols-4',
    };
@endphp

<div class="grid gap-4 {{ $grilla }}">
    {{ $slot }}

    <div class="flex items-end">
        <flux:button variant="danger" icon="x-mark" wire:click="{{ $limpiar }}" class="w-full">
            {{ __('Limpiar filtros') }}
        </flux:button>
    </div>
</div>
