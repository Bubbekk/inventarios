<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-zinc-800">
        <flux:header container class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <x-app-logo class="max-lg:hidden" href="{{ route('dashboard') }}" wire:navigate />

            <flux:navbar class="-mb-px max-lg:hidden">
                <flux:navbar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    Panel
                </flux:navbar.item>

                <flux:separator vertical variant="subtle" class="my-2" />

                <flux:dropdown class="max-lg:hidden">
                    <flux:navbar.item icon:trailing="chevron-down">Ajustes</flux:navbar.item>

                    <flux:navmenu>
                        <flux:navmenu.item :href="route('profile.edit')" wire:navigate>Perfil</flux:navmenu.item>
                        <flux:navmenu.item :href="route('security.edit')" wire:navigate>Seguridad</flux:navmenu.item>
                        <flux:navmenu.item :href="route('appearance.edit')" wire:navigate>Apariencia</flux:navmenu.item>
                    </flux:navmenu>
                </flux:dropdown>
            </flux:navbar>

            <flux:spacer />

            <flux:navbar class="me-4">
                <flux:navbar.item class="max-lg:hidden" icon="cog-6-tooth" :href="route('profile.edit')" label="Ajustes" wire:navigate />
            </flux:navbar>

            <x-desktop-user-menu />
        </flux:header>

        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 lg:hidden dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />

                <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    Panel
                </flux:sidebar.item>

                <flux:sidebar.group expandable heading="Ajustes" class="grid">
                    <flux:sidebar.item :href="route('profile.edit')" :current="request()->routeIs('profile.edit')" wire:navigate>Perfil</flux:sidebar.item>
                    <flux:sidebar.item :href="route('security.edit')" :current="request()->routeIs('security.edit')" wire:navigate>Seguridad</flux:sidebar.item>
                    <flux:sidebar.item :href="route('appearance.edit')" :current="request()->routeIs('appearance.edit')" wire:navigate>Apariencia</flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:sidebar.spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="cog-6-tooth" :href="route('profile.edit')" wire:navigate>Ajustes</flux:sidebar.item>
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
