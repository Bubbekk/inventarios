@props([
    'modal',
    'titulo',
    'mensaje',
    'accion' => 'eliminar',
    'etiqueta' => null,
])

<flux:modal :name="$modal" class="md:w-96">
    <div class="flex flex-col gap-6">
        <div>
            <flux:heading size="lg">{{ $titulo }}</flux:heading>
            <flux:subheading>{{ $mensaje }}</flux:subheading>
        </div>

        <div class="flex gap-2">
            <flux:spacer />

            <flux:modal.close>
                <flux:button variant="danger">{{ __('Cancelar') }}</flux:button>
            </flux:modal.close>

            <flux:button variant="danger" icon="trash" wire:click="{{ $accion }}">
                {{ $etiqueta ?? __('Eliminar') }}
            </flux:button>
        </div>
    </div>
</flux:modal>
