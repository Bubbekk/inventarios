<x-layouts::app.header :title="$title ?? null">
    <flux:main container>
        <div class="flex flex-col gap-6 md:flex-row md:gap-10">
            <div class="md:w-[220px]">
                <x-navegacion.navlist />
            </div>

            <flux:separator class="md:hidden" variant="subtle" />

            <div class="flex-1">
                {{ $slot }}
            </div>
        </div>
    </flux:main>
</x-layouts::app.header>
