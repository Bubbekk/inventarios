<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Inicio')] class extends Component
{
    /**
     * Accesos directos a los módulos sobre los que la cuenta tiene permiso.
     *
     * El panel con indicadores se construye en la Fase 9 y reemplaza esta página.
     *
     * @return array<int, array{titulo: string, descripcion: string, icono: string, ruta: string}>
     */
    #[Computed]
    public function accesos(): array
    {
        $usuario = Auth::user();

        $modulos = [
            [
                'permiso' => 'usuarios.ver',
                'titulo' => __('Usuarios'),
                'descripcion' => __('Cuentas de acceso y asignación de roles.'),
                'icono' => 'users',
                'ruta' => 'usuarios.indice',
            ],
        ];

        return array_values(array_map(
            static fn (array $modulo): array => [
                'titulo' => $modulo['titulo'],
                'descripcion' => $modulo['descripcion'],
                'icono' => $modulo['icono'],
                'ruta' => $modulo['ruta'],
            ],
            array_filter(
                $modulos,
                static fn (array $modulo): bool => $usuario->can($modulo['permiso']) && Route::has($modulo['ruta']),
            ),
        ));
    }

    #[Computed]
    public function rol(): string
    {
        return Auth::user()->getRoleNames()->map(fn (string $rol): string => ucfirst($rol))->implode(', ');
    }
}; ?>

<div class="flex flex-col gap-8">
    <flux:card class="flex flex-col gap-6 sm:flex-row sm:items-center">
        <flux:avatar
            size="xl"
            circle
            color="violet"
            :name="auth()->user()->name"
            :initials="auth()->user()->initials()"
        />

        <div class="flex flex-col gap-1">
            <flux:heading size="xl" level="1">
                {{ __('Hola, :nombre', ['nombre' => auth()->user()->name]) }}
            </flux:heading>

            <flux:text variant="subtle">{{ auth()->user()->email }}</flux:text>

            <div class="mt-2 flex flex-wrap items-center gap-2">
                @if ($this->rol !== '')
                    <flux:badge size="sm" color="violet" icon="shield-check">{{ $this->rol }}</flux:badge>
                @endif

                <flux:badge size="sm" color="green" icon="check-circle">{{ __('Cuenta activa') }}</flux:badge>
            </div>
        </div>

        <flux:spacer />

        <flux:button
            variant="primary"
            icon="cog-6-tooth"
            :href="route('profile.edit')"
            wire:navigate
        >
            {{ __('Mi cuenta') }}
        </flux:button>
    </flux:card>

    <div class="flex flex-col gap-4">
        <div>
            <flux:heading size="lg">{{ __('Módulos disponibles') }}</flux:heading>
            <flux:subheading>{{ __('Se listan solo los módulos habilitados para su rol.') }}</flux:subheading>
        </div>

        <flux:separator variant="subtle" />

        @if ($this->accesos !== [])
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($this->accesos as $acceso)
                    <flux:card
                        variant="soft"
                        class="group flex flex-col gap-4 transition hover:border-zinc-300 dark:hover:border-white/20"
                    >
                        <div class="flex items-start gap-3">
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-zinc-100 dark:bg-white/10">
                                <flux:icon :icon="$acceso['icono']" variant="outline" class="size-5 text-zinc-500 dark:text-zinc-300" />
                            </div>

                            <div class="flex flex-col">
                                <flux:heading>{{ $acceso['titulo'] }}</flux:heading>
                                <flux:text size="sm" variant="subtle">{{ $acceso['descripcion'] }}</flux:text>
                            </div>
                        </div>

                        <flux:spacer />

                        <flux:button
                            variant="primary"
                            size="sm"
                            icon:trailing="arrow-right"
                            class="w-full"
                            :href="route($acceso['ruta'])"
                            wire:navigate
                        >
                            {{ __('Abrir') }}
                        </flux:button>
                    </flux:card>
                @endforeach
            </div>
        @else
            <flux:card variant="soft" class="flex flex-col items-center gap-3 py-10 text-center">
                <flux:icon icon="lock-closed" variant="outline" class="size-8 text-zinc-400" />
                <flux:heading>{{ __('Sin módulos habilitados') }}</flux:heading>
                <flux:text variant="subtle">
                    {{ __('Su rol todavía no tiene módulos asignados. Comuníquese con el administrador.') }}
                </flux:text>
            </flux:card>
        @endif
    </div>
</div>
