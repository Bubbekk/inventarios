<?php

use App\DTOs\Funcionarios\GuardarFuncionarioDTO;
use App\Services\Funcionarios\FuncionarioService;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Funcionarios')] #[Lazy] class extends Component
{
    use WithPagination;

    #[Url(as: 'buscar', keep: false)]
    public string $busqueda = '';

    #[Url(as: 'estado', keep: false)]
    public string $filtroEstado = '';

    #[Url(as: 'rut', keep: false)]
    public string $filtroRut = '';

    public string $ordenarPor = 'apellidos';

    public string $direccion = 'asc';

    public ?int $funcionarioEnEdicion = null;

    public string $nombres = '';

    public string $apellidos = '';

    public string $rut = '';

    public string $cargo = '';

    public bool $activo = true;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('funcionarios.ver'), 403);
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
        if (in_array($propiedad, ['busqueda', 'filtroEstado', 'filtroRut'], true)) {
            $this->resetPage();
        }
    }

    public function limpiarFiltros(): void
    {
        $this->reset('busqueda', 'filtroEstado', 'filtroRut');
        $this->resetPage();
    }

    public function crear(): void
    {
        abort_unless(auth()->user()->can('funcionarios.crear'), 403);

        $this->limpiarFormulario();
        $this->modal('funcionario')->show();
    }

    public function editar(int $id, FuncionarioService $servicio): void
    {
        abort_unless(auth()->user()->can('funcionarios.editar'), 403);

        $funcionario = $servicio->obtener($id);

        if ($funcionario === null) {
            Flux::toast(variant: 'danger', text: __('El funcionario no existe.'));

            return;
        }

        $this->funcionarioEnEdicion = $funcionario->id;
        $this->nombres = $funcionario->nombres;
        $this->apellidos = $funcionario->apellidos;
        $this->rut = $funcionario->rut ?? '';
        $this->cargo = $funcionario->cargo ?? '';
        $this->activo = $funcionario->activo;
        $this->resetValidation();

        $this->modal('funcionario')->show();
    }

    public function guardar(FuncionarioService $servicio): void
    {
        abort_unless(
            auth()->user()->can($this->funcionarioEnEdicion === null ? 'funcionarios.crear' : 'funcionarios.editar'),
            403,
        );

        $datos = $this->validate([
            'nombres' => ['required', 'string', 'max:255'],
            'apellidos' => ['required', 'string', 'max:255'],
            'rut' => ['nullable', 'string', 'max:20'],
            'cargo' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $dto = GuardarFuncionarioDTO::desdeArreglo([
                'nombres' => $datos['nombres'],
                'apellidos' => $datos['apellidos'],
                'rut' => $datos['rut'] ?? null,
                'cargo' => $datos['cargo'] ?? null,
                'activo' => $this->activo,
            ]);

            if ($this->funcionarioEnEdicion === null) {
                $servicio->crear($dto);
            } else {
                $servicio->actualizar($this->funcionarioEnEdicion, $dto);
            }
        } catch (\DomainException $error) {
            $this->addError(
                str_contains($error->getMessage(), 'RUT') ? 'rut' : 'nombres',
                $error->getMessage(),
            );

            return;
        }

        $this->modal('funcionario')->close();
        $this->limpiarFormulario();

        Flux::toast(variant: 'success', text: __('Funcionario guardado.'));
    }

    public function cambiarEstado(int $id, bool $activo, FuncionarioService $servicio): void
    {
        abort_unless(auth()->user()->can('funcionarios.editar'), 403);

        try {
            $servicio->cambiarEstado($id, $activo);
        } catch (\DomainException $error) {
            Flux::toast(variant: 'danger', text: $error->getMessage());

            return;
        }

        Flux::toast(
            variant: 'success',
            text: $activo ? __('Funcionario activado.') : __('Funcionario desactivado.'),
        );
    }

    #[Computed]
    public function funcionarios(): LengthAwarePaginator
    {
        return app(FuncionarioService::class)->paginar(
            filtros: array_filter([
                'busqueda' => $this->busqueda !== '' ? $this->busqueda : null,
                'activo' => $this->filtroEstado === '' ? null : $this->filtroEstado === 'activos',
                'con_rut' => $this->filtroRut === '' ? null : $this->filtroRut === 'con',
            ], static fn ($valor): bool => $valor !== null),
            ordenarPor: $this->ordenarPor,
            direccion: $this->direccion,
        );
    }

    private function limpiarFormulario(): void
    {
        $this->reset('funcionarioEnEdicion', 'nombres', 'apellidos', 'rut', 'cargo', 'activo');
        $this->resetValidation();
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-mantenedor.encabezado :titulo="__('Funcionarios')" :descripcion="__('Responsables a cargo de los bienes del inventario.')">

        @can('funcionarios.crear')
            <flux:button variant="primary" icon="plus" wire:click="crear">
                {{ __('Nuevo funcionario') }}
            </flux:button>
        @endcan
    </x-mantenedor.encabezado>

    <flux:separator variant="subtle" />

    <x-mantenedor.filtros :columnas="4">
        <flux:input
            wire:model.live.debounce.400ms="busqueda"
            :label="__('Buscar')"
            :placeholder="__('Nombre, apellidos, RUT o cargo')"
            icon="magnifying-glass"
            clearable
        />

        <flux:select wire:model.live="filtroEstado" :label="__('Estado')" :placeholder="__('Todos los estados')">
            <flux:select.option value="">{{ __('Todos los estados') }}</flux:select.option>
            <flux:select.option value="activos">{{ __('Activos') }}</flux:select.option>
            <flux:select.option value="inactivos">{{ __('Desactivados') }}</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="filtroRut" :label="__('RUT')" :placeholder="__('Todos')">
            <flux:select.option value="">{{ __('Todos') }}</flux:select.option>
            <flux:select.option value="con">{{ __('Con RUT') }}</flux:select.option>
            <flux:select.option value="sin">{{ __('Sin RUT') }}</flux:select.option>
        </flux:select>

    </x-mantenedor.filtros>

    <flux:table :paginate="$this->funcionarios">
        <flux:table.columns>
            <flux:table.column
                sortable
                :sorted="$ordenarPor === 'apellidos'"
                :direction="$direccion"
                wire:click="ordenar('apellidos')"
            >{{ __('Funcionario') }}</flux:table.column>

            <flux:table.column
                sortable
                :sorted="$ordenarPor === 'rut'"
                :direction="$direccion"
                wire:click="ordenar('rut')"
            >{{ __('RUT') }}</flux:table.column>

            <flux:table.column
                sortable
                :sorted="$ordenarPor === 'cargo'"
                :direction="$direccion"
                wire:click="ordenar('cargo')"
            >{{ __('Cargo') }}</flux:table.column>

            <flux:table.column
                sortable
                :sorted="$ordenarPor === 'activo'"
                :direction="$direccion"
                wire:click="ordenar('activo')"
            >{{ __('Estado') }}</flux:table.column>

            <flux:table.column align="end">{{ __('Ítems a cargo') }}</flux:table.column>

            <flux:table.column align="end">{{ __('Acciones') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->funcionarios as $fila)
                <flux:table.row :key="$fila->id">
                    <flux:table.cell variant="strong">{{ $fila->apellidos }}, {{ $fila->nombres }}</flux:table.cell>

                    <flux:table.cell>
                        @if ($fila->rut)
                            {{ $fila->rut }}
                        @else
                            <flux:badge size="sm" color="sky">{{ __('Sin RUT') }}</flux:badge>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell>
                        @if ($fila->cargo)
                            {{ $fila->cargo }}
                        @else
                            <flux:text variant="subtle">{{ __('Sin cargo') }}</flux:text>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:badge size="sm" :color="$fila->activo ? 'green' : 'red'">
                            {{ $fila->activo ? __('Activo') : __('Desactivado') }}
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell align="end">{{ $fila->items_asociados }}</flux:table.cell>

                    <flux:table.cell align="end">
                        <x-mantenedor.acciones>
                            @can('funcionarios.editar')
                                <flux:button
                                    size="sm"
                                    variant="primary"
                                    icon="pencil-square"
                                    :tooltip="__('Editar funcionario')"
                                    wire:click="editar({{ $fila->id }})"
                                />

                                @if ($fila->activo)
                                    <flux:button
                                        size="sm"
                                        variant="danger"
                                        icon="user-minus"
                                        :tooltip="__('Desactivar funcionario')"
                                        wire:click="cambiarEstado({{ $fila->id }}, false)"
                                        wire:confirm="{{ __('¿Desactivar a este funcionario? Conservará su historial de bienes.') }}"
                                    />
                                @else
                                    <flux:button
                                        size="sm"
                                        variant="primary"
                                        icon="user-plus"
                                        :tooltip="__('Activar funcionario')"
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
                        <flux:text variant="subtle">{{ __('No hay funcionarios que coincidan con los filtros.') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @canany(['funcionarios.crear', 'funcionarios.editar'])
        <flux:modal name="funcionario" class="md:w-[32rem]">
            <form wire:submit="guardar" class="flex flex-col gap-6">
                <div>
                    <flux:heading size="lg">
                        {{ $funcionarioEnEdicion === null ? __('Nuevo funcionario') : __('Editar funcionario') }}
                    </flux:heading>
                    <flux:subheading>
                        {{ __('El RUT es opcional: los cargos de turno y los responsables de uso común se registran sin él.') }}
                    </flux:subheading>
                </div>

                <flux:input
                    wire:model="nombres"
                    :label="__('Nombres')"
                    :placeholder="__('Nombres de la persona, o denominación del cargo de turno')"
                    autocomplete="off"
                />

                <flux:input
                    wire:model="apellidos"
                    :label="__('Apellidos')"
                    :placeholder="__('Apellidos de la persona, o complemento de la denominación')"
                    autocomplete="off"
                />

                <flux:input
                    wire:model="rut"
                    :label="__('RUT')"
                    :placeholder="__('12.345.678-5. Dejar en blanco si no corresponde a una persona')"
                    autocomplete="off"
                />

                <flux:input
                    wire:model="cargo"
                    :label="__('Cargo')"
                    :placeholder="__('Función que desempeña. Opcional')"
                    autocomplete="off"
                />

                <flux:switch
                    wire:model="activo"
                    :label="__('Funcionario activo')"
                    :description="__('Un funcionario desactivado no aparece en los selectores y conserva su historial de bienes.')"
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
    @endcanany
</div>
