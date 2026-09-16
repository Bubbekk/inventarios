<div class="flex flex-col gap-6" role="status" aria-live="polite">
    <span class="sr-only">{{ __('Cargando contenido.') }}</span>

    <div class="flex flex-col gap-2">
        <flux:skeleton class="h-7 w-56" />
        <flux:skeleton.line />
    </div>

    <flux:separator variant="subtle" />

    <div class="grid gap-4 md:grid-cols-4">
        <flux:skeleton class="h-10" />
        <flux:skeleton class="h-10" />
        <flux:skeleton class="h-10" />
        <flux:skeleton class="h-10" />
    </div>

    <div class="flex flex-col gap-3">
        <flux:skeleton class="h-10" />
        <flux:skeleton.line />
        <flux:skeleton.line />
        <flux:skeleton.line />
        <flux:skeleton.line />
        <flux:skeleton.line />
    </div>
</div>
