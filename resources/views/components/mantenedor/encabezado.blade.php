@props([
    'titulo',
    'descripcion' => null,
])

<div class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <flux:heading size="xl" level="1">{{ $titulo }}</flux:heading>

        @if ($descripcion)
            <flux:subheading>{{ $descripcion }}</flux:subheading>
        @endif
    </div>

    {{ $slot }}
</div>
