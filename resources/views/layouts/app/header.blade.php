<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-zinc-800">
        <flux:header container class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <x-app-logo class="max-lg:hidden" href="{{ route('inicio') }}" wire:navigate />

            <x-navegacion.navbar />

            <flux:spacer />

            <flux:navbar class="me-4">
                <flux:navbar.item
                    class="max-lg:hidden"
                    icon="cog-6-tooth"
                    :href="route('profile.edit')"
                    :label="__('Ajustes')"
                    :current="request()->routeIs('profile.edit', 'security.edit', 'appearance.edit')"
                    wire:navigate
                />
            </flux:navbar>

            <x-desktop-user-menu />
        </flux:header>

        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 lg:hidden dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('inicio') }}" wire:navigate />

                <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
            </flux:sidebar.header>

            <x-navegacion.sidebar />

            <flux:sidebar.spacer />

            <flux:sidebar.nav>
                <flux:sidebar.group expandable :heading="__('Ajustes')" class="grid">
                    <flux:sidebar.item :href="route('profile.edit')" :current="request()->routeIs('profile.edit')" wire:navigate>{{ __('Perfil') }}</flux:sidebar.item>
                    <flux:sidebar.item :href="route('security.edit')" :current="request()->routeIs('security.edit')" wire:navigate>{{ __('Seguridad') }}</flux:sidebar.item>
                    <flux:sidebar.item :href="route('appearance.edit')" :current="request()->routeIs('appearance.edit')" wire:navigate>{{ __('Apariencia') }}</flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>
        </flux:sidebar>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
