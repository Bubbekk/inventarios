<?php

use App\DTOs\Usuarios\GuardarUsuarioDTO;
use App\Services\Usuarios\UsuarioService;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Usuarios')] #[Lazy] class extends Component
{
    use WithPagination;

    #[Url(as: 'buscar', keep: false)]
    public string $busqueda = '';

    #[Url(as: 'rol', keep: false)]
    public string $filtroRol = '';

    #[Url(as: 'estado', keep: false)]
    public string $filtroEstado = '';

    public string $ordenarPor = 'name';

    public string $direccion = 'asc';

    public ?int $usuarioEnEdicion = null;

    public string $nombre = '';

    public string $correo = '';

    public string $rol = '';

    public bool $activo = true;

    public string $clave = '';

    public string $clave_confirmation = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('usuarios.ver'), 403);
    }

    /**
     * Ordena el listado. Un segundo clic sobre la misma columna invierte el sentido.
     */
    public function ordenar(string $columna): void
    {
        if ($this->ordenarPor === $columna) {
            $this->direccion = $this->direccion === 'asc' ? 'desc' : 'asc';
        } else {
            $this->ordenarPor = $columna;
            $this->direccion = 'asc';
        }

        $this->resetPage();
    }

    public function updated(string $propiedad): void
    {
        if (in_array($propiedad, ['busqueda', 'filtroRol', 'filtroEstado'], true)) {
            $this->resetPage();
        }
    }

    public function limpiarFiltros(): void
    {
        $this->reset('busqueda', 'filtroRol', 'filtroEstado');
        $this->resetPage();
    }

    public function crear(): void
    {
        abort_unless(auth()->user()->can('usuarios.crear'), 403);

        $this->limpiarFormulario();
        $this->modal('usuario')->show();
    }

    public function editar(int $id): void
    {
        abort_unless(auth()->user()->can('usuarios.editar'), 403);

        $usuario = app(UsuarioService::class)->obtener($id);

        if ($usuario === null) {
            Flux::toast(variant: 'danger', text: __('La cuenta no existe.'));

            return;
        }

        $this->usuarioEnEdicion = $usuario->id;
        $this->nombre = $usuario->nombre;
        $this->correo = $usuario->correo;
        $this->rol = $usuario->rol ?? '';
        $this->activo = $usuario->activo;
        $this->clave = '';
        $this->clave_confirmation = '';
        $this->resetValidation();

        $this->modal('usuario')->show();
    }

    public function guardar(UsuarioService $servicio): void
    {
        abort_unless(
            auth()->user()->can($this->usuarioEnEdicion === null ? 'usuarios.crear' : 'usuarios.editar'),
            403,
        );

        $datos = $this->validate($this->reglas());

        try {
            $dto = GuardarUsuarioDTO::desdeArreglo([
                'nombre' => $datos['nombre'],
                'correo' => $datos['correo'],
                'rol' => $datos['rol'],
                'activo' => $this->activo,
                'clave' => $datos['clave'] ?? null,
            ]);

            if ($this->usuarioEnEdicion === null) {
                $servicio->crear($dto);
            } else {
                $servicio->actualizar($this->usuarioEnEdicion, $dto);
            }
        } catch (\DomainException $error) {
            $this->addError('correo', $error->getMessage());

            return;
        }

        $this->modal('usuario')->close();
        $this->limpiarFormulario();

        Flux::toast(variant: 'success', text: __('Cuenta guardada.'));
    }

    public function cambiarEstado(int $id, bool $activo, UsuarioService $servicio): void
    {
        abort_unless(auth()->user()->can('usuarios.eliminar'), 403);

        try {
            $servicio->cambiarEstado($id, $activo);
        } catch (\DomainException $error) {
            Flux::toast(variant: 'danger', text: $error->getMessage());

            return;
        }

        Flux::toast(
            variant: 'success',
            text: $activo ? __('Cuenta activada.') : __('Cuenta desactivada.'),
        );
    }

    #[Computed]
    public function usuarios(): LengthAwarePaginator
    {
        return app(UsuarioService::class)->paginar(
            filtros: array_filter([
                'busqueda' => $this->busqueda !== '' ? $this->busqueda : null,
                'rol' => $this->filtroRol !== '' ? $this->filtroRol : null,
                'activo' => $this->filtroEstado === '' ? null : $this->filtroEstado === 'activos',
            ], static fn ($valor): bool => $valor !== null),
            ordenarPor: $this->ordenarPor,
            direccion: $this->direccion,
        );
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function roles(): array
    {
        return app(UsuarioService::class)->rolesDisponibles();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function reglas(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'correo' => ['required', 'string', 'email', 'max:255'],
            'rol' => ['required', 'string', 'in:'.implode(',', $this->roles())],
            'clave' => $this->usuarioEnEdicion === null
                ? ['required', 'string', Password::default(), 'confirmed']
                : ['nullable', 'string', Password::default(), 'confirmed'],
        ];
    }

    private function limpiarFormulario(): void
    {
        $this->reset('usuarioEnEdicion', 'nombre', 'correo', 'rol', 'activo', 'clave', 'clave_confirmation');
        $this->resetValidation();
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-mantenedor.encabezado :titulo="__('Usuarios')" :descripcion="__('Cuentas de acceso al sistema y su rol.')">

        @can('usuarios.crear')
            <flux:button variant="primary" icon="plus" wire:click="crear">
                {{ __('Nueva cuenta') }}
            </flux:button>
        @endcan
    </x-mantenedor.encabezado>

    <flux:separator variant="subtle" />

    <x-mantenedor.filtros :columnas="4">
        <flux:input
            wire:model.live.debounce.400ms="busqueda"
            :label="__('Buscar')"
            :placeholder="__('Nombre o correo de la cuenta')"
            icon="magnifying-glass"
            clearable
        />

        <flux:select wire:model.live="filtroRol" :label="__('Rol')" :placeholder="__('Todos los roles')">
            <flux:select.option value="">{{ __('Todos los roles') }}</flux:select.option>
            @foreach ($this->roles as $nombreRol)
                <flux:select.option :value="$nombreRol">{{ ucfirst($nombreRol) }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="filtroEstado" :label="__('Estado')" :placeholder="__('Todos los estados')">
            <flux:select.option value="">{{ __('Todos los estados') }}</flux:select.option>
            <flux:select.option value="activos">{{ __('Activas') }}</flux:select.option>
            <flux:select.option value="inactivos">{{ __('Desactivadas') }}</flux:select.option>
        </flux:select>

    </x-mantenedor.filtros>

    <flux:table :paginate="$this->usuarios">
        <flux:table.columns>
            <flux:table.column
                sortable
                :sorted="$ordenarPor === 'name'"
                :direction="$direccion"
                wire:click="ordenar('name')"
            >{{ __('Nombre') }}</flux:table.column>

            <flux:table.column
                sortable
                :sorted="$ordenarPor === 'email'"
                :direction="$direccion"
                wire:click="ordenar('email')"
            >{{ __('Correo') }}</flux:table.column>

            <flux:table.column>{{ __('Rol') }}</flux:table.column>

            <flux:table.column
                sortable
                :sorted="$ordenarPor === 'activo'"
                :direction="$direccion"
                wire:click="ordenar('activo')"
            >{{ __('Estado') }}</flux:table.column>

            <flux:table.column
                sortable
                :sorted="$ordenarPor === 'created_at'"
                :direction="$direccion"
                wire:click="ordenar('created_at')"
            >{{ __('Creada') }}</flux:table.column>

            <flux:table.column align="end">{{ __('Acciones') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->usuarios as $fila)
                <flux:table.row :key="$fila->id">
                    <flux:table.cell variant="strong">{{ $fila->name }}</flux:table.cell>
                    <flux:table.cell>{{ $fila->email }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($fila->rol)
                            <flux:badge size="sm" :color="$fila->rol === 'administrador' ? 'violet' : ($fila->rol === 'verificador' ? 'blue' : 'sky')">
                                {{ ucfirst($fila->rol) }}
                            </flux:badge>
                        @else
                            <flux:text variant="subtle">{{ __('Sin rol') }}</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$fila->activo ? 'green' : 'red'">
                            {{ $fila->activo ? __('Activa') : __('Desactivada') }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ \Illuminate\Support\Carbon::parse($fila->created_at)->format('d-m-Y') }}
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <x-mantenedor.acciones>
                            @can('usuarios.editar')
                                <flux:button
                                    size="sm"
                                    variant="primary"
                                    icon="pencil-square"
                                    :tooltip="__('Editar cuenta')"
                                    wire:click="editar({{ $fila->id }})"
                                />
                            @endcan

                            @can('usuarios.eliminar')
                                @if ($fila->activo)
                                    <flux:button
                                        size="sm"
                                        variant="danger"
                                        icon="user-minus"
                                        :tooltip="__('Desactivar cuenta')"
                                        wire:click="cambiarEstado({{ $fila->id }}, false)"
                                        wire:confirm="{{ __('¿Desactivar esta cuenta? Conservará su historial.') }}"
                                    />
                                @else
                                    <flux:button
                                        size="sm"
                                        variant="primary"
                                        icon="user-plus"
                                        :tooltip="__('Activar cuenta')"
                                        wire:click="cambiarEstado({{ $fila->id }}, true)"
                                    />
                                @endif
                            @endcan
                        </x-mantenedor.acciones>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">
                        <flux:text variant="subtle">{{ __('No hay cuentas que coincidan con los filtros.') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="usuario" class="md:w-[32rem]" @close="$refresh">
        <form wire:submit="guardar" class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">
                    {{ $usuarioEnEdicion === null ? __('Nueva cuenta') : __('Editar cuenta') }}
                </flux:heading>
                <flux:subheading>
                    {{ __('El acceso al sistema lo otorga el rol asignado.') }}
                </flux:subheading>
            </div>

            <flux:input
                wire:model="nombre"
                :label="__('Nombre')"
                :placeholder="__('Nombre y apellidos del titular de la cuenta')"
                autocomplete="off"
            />

            <flux:input
                wire:model="correo"
                type="email"
                :label="__('Correo')"
                :placeholder="__('correo@dsp.cl')"
                autocomplete="off"
            />

            <flux:select wire:model="rol" :label="__('Rol')" :placeholder="__('Seleccione el rol de la cuenta')">
                @foreach ($this->roles as $nombreRol)
                    <flux:select.option :value="$nombreRol">{{ ucfirst($nombreRol) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input
                wire:model="clave"
                type="password"
                :label="$usuarioEnEdicion === null ? __('Contraseña') : __('Nueva contraseña')"
                :placeholder="$usuarioEnEdicion === null ? __('Contraseña inicial de la cuenta') : __('Dejar en blanco para conservar la actual')"
                autocomplete="new-password"
                viewable
            />

            <flux:input
                wire:model="clave_confirmation"
                type="password"
                :label="__('Confirmar contraseña')"
                :placeholder="__('Repita la contraseña ingresada')"
                autocomplete="new-password"
                viewable
            />

            <flux:switch
                wire:model="activo"
                :label="__('Cuenta activa')"
                :description="__('Una cuenta desactivada no puede ingresar al sistema y conserva su historial.')"
            />

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="danger">{{ __('Cancelar') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Guardar') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
