<?php

use App\DTOs\Tipos\GuardarTipoDTO;
use App\Services\Tipos\TipoService;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Tipos')] #[Lazy] class extends Component
{
    use WithPagination;

    #[Url(as: 'buscar', keep: false)]
    public string $busqueda = '';

    #[Url(as: 'serie', keep: false)]
    public string $filtroSerie = '';

    #[Url(as: 'vencimiento', keep: false)]
    public string $filtroVencimiento = '';

    public string $ordenarPor = 'nombre';

    public string $direccion = 'asc';

    public ?int $tipoEnEdicion = null;

    public string $nombre = '';

    public bool $requiereSerie = false;

    public bool $controlaVencimiento = false;

    public ?int $tipoPorEliminar = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('tipos.ver'), 403);
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
        if (in_array($propiedad, ['busqueda', 'filtroSerie', 'filtroVencimiento'], true)) {
            $this->resetPage();
        }
    }

    public function limpiarFiltros(): void
    {
        $this->reset('busqueda', 'filtroSerie', 'filtroVencimiento');
        $this->resetPage();
    }

    public function crear(): void
    {
        abort_unless(auth()->user()->can('tipos.crear'), 403);

        $this->limpiarFormulario();
        $this->modal('tipo')->show();
    }

    public function editar(int $id, TipoService $servicio): void
    {
        abort_unless(auth()->user()->can('tipos.editar'), 403);

        $tipo = $servicio->obtener($id);

        if ($tipo === null) {
            Flux::toast(variant: 'danger', text: __('El tipo no existe.'));

            return;
        }

        $this->tipoEnEdicion = $tipo->id;
        $this->nombre = $tipo->nombre;
        $this->requiereSerie = $tipo->requiereSerie;
        $this->controlaVencimiento = $tipo->controlaVencimiento;
        $this->resetValidation();

        $this->modal('tipo')->show();
    }

    public function guardar(TipoService $servicio): void
    {
        abort_unless(
            auth()->user()->can($this->tipoEnEdicion === null ? 'tipos.crear' : 'tipos.editar'),
            403,
        );

        $datos = $this->validate([
            'nombre' => ['required', 'string', 'max:255'],
        ]);

        try {
            $dto = GuardarTipoDTO::desdeArreglo([
                'nombre' => $datos['nombre'],
                'requiere_serie' => $this->requiereSerie,
                'controla_vencimiento' => $this->controlaVencimiento,
            ]);

            if ($this->tipoEnEdicion === null) {
                $servicio->crear($dto);
            } else {
                $servicio->actualizar($this->tipoEnEdicion, $dto);
            }
        } catch (\DomainException $error) {
            $this->addError('nombre', $error->getMessage());

            return;
        }

        $this->modal('tipo')->close();
        $this->limpiarFormulario();

        Flux::toast(variant: 'success', text: __('Tipo guardado.'));
    }

    public function confirmarEliminacion(int $id): void
    {
        abort_unless(auth()->user()->can('tipos.eliminar'), 403);

        $this->tipoPorEliminar = $id;
        $this->modal('eliminar-tipo')->show();
    }

    public function eliminar(TipoService $servicio): void
    {
        abort_unless(auth()->user()->can('tipos.eliminar'), 403);

        if ($this->tipoPorEliminar === null) {
            return;
        }

        try {
            $servicio->eliminar($this->tipoPorEliminar);
        } catch (\DomainException $error) {
            $this->modal('eliminar-tipo')->close();
            $this->tipoPorEliminar = null;

            Flux::toast(variant: 'danger', text: $error->getMessage());

            return;
        }

        $this->modal('eliminar-tipo')->close();
        $this->tipoPorEliminar = null;

        Flux::toast(variant: 'success', text: __('Tipo eliminado.'));
    }

    #[Computed]
    public function tipos(): LengthAwarePaginator
    {
        return app(TipoService::class)->paginar(
            filtros: array_filter([
                'busqueda' => $this->busqueda !== '' ? $this->busqueda : null,
                'requiere_serie' => $this->filtroSerie === '' ? null : $this->filtroSerie === 'si',
                'controla_vencimiento' => $this->filtroVencimiento === '' ? null : $this->filtroVencimiento === 'si',
            ], static fn ($valor): bool => $valor !== null),
            ordenarPor: $this->ordenarPor,
            direccion: $this->direccion,
        );
    }

    #[Computed]
    public function nombrePorEliminar(): string
    {
        if ($this->tipoPorEliminar === null) {
            return '';
        }

        return app(TipoService::class)->obtener($this->tipoPorEliminar)?->nombre ?? '';
    }

    private function limpiarFormulario(): void
    {
        $this->reset('tipoEnEdicion', 'nombre', 'requiereSerie', 'controlaVencimiento');
        $this->resetValidation();
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-mantenedor.encabezado :titulo="__('Tipos')" :descripcion="__('Clases de bien del inventario y sus reglas de registro.')">

        @can('tipos.crear')
            <flux:button variant="primary" icon="plus" wire:click="crear">
                {{ __('Nuevo tipo') }}
            </flux:button>
        @endcan
    </x-mantenedor.encabezado>

    <flux:separator variant="subtle" />

    <x-mantenedor.filtros :columnas="4">
        <flux:input
            wire:model.live.debounce.400ms="busqueda"
            :label="__('Buscar')"
            :placeholder="__('Nombre del tipo de bien')"
            icon="magnifying-glass"
            clearable
        />

        <flux:select wire:model.live="filtroSerie" :label="__('Requiere serie')" :placeholder="__('Todos')">
            <flux:select.option value="">{{ __('Todos') }}</flux:select.option>
            <flux:select.option value="si">{{ __('Sí') }}</flux:select.option>
            <flux:select.option value="no">{{ __('No') }}</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="filtroVencimiento" :label="__('Controla vencimiento')" :placeholder="__('Todos')">
            <flux:select.option value="">{{ __('Todos') }}</flux:select.option>
            <flux:select.option value="si">{{ __('Sí') }}</flux:select.option>
            <flux:select.option value="no">{{ __('No') }}</flux:select.option>
        </flux:select>

    </x-mantenedor.filtros>

    <flux:table :paginate="$this->tipos">
        <flux:table.columns>
            <flux:table.column
                sortable
                :sorted="$ordenarPor === 'nombre'"
                :direction="$direccion"
                wire:click="ordenar('nombre')"
            >{{ __('Nombre') }}</flux:table.column>

            <flux:table.column
                sortable
                :sorted="$ordenarPor === 'requiere_serie'"
                :direction="$direccion"
                wire:click="ordenar('requiere_serie')"
            >{{ __('Requiere serie') }}</flux:table.column>

            <flux:table.column
                sortable
                :sorted="$ordenarPor === 'controla_vencimiento'"
                :direction="$direccion"
                wire:click="ordenar('controla_vencimiento')"
            >{{ __('Controla vencimiento') }}</flux:table.column>

            <flux:table.column align="end">{{ __('Ítems') }}</flux:table.column>

            <flux:table.column
                sortable
                :sorted="$ordenarPor === 'created_at'"
                :direction="$direccion"
                wire:click="ordenar('created_at')"
            >{{ __('Creado') }}</flux:table.column>

            <flux:table.column align="end">{{ __('Acciones') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->tipos as $fila)
                <flux:table.row :key="$fila->id">
                    <flux:table.cell variant="strong">{{ $fila->nombre }}</flux:table.cell>

                    <flux:table.cell>
                        <flux:badge size="sm" :color="$fila->requiere_serie ? 'green' : null">
                            {{ $fila->requiere_serie ? __('Sí') : __('No') }}
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:badge size="sm" :color="$fila->controla_vencimiento ? 'amber' : null">
                            {{ $fila->controla_vencimiento ? __('Sí') : __('No') }}
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell align="end">{{ $fila->items_asociados }}</flux:table.cell>

                    <flux:table.cell>
                        {{ \Illuminate\Support\Carbon::parse($fila->created_at)->format('d-m-Y') }}
                    </flux:table.cell>

                    <flux:table.cell align="end">
                        <x-mantenedor.acciones>
                            @can('tipos.editar')
                                <flux:button
                                    size="sm"
                                    variant="primary"
                                    icon="pencil-square"
                                    :tooltip="__('Editar tipo')"
                                    wire:click="editar({{ $fila->id }})"
                                />
                            @endcan

                            @can('tipos.eliminar')
                                <flux:button
                                    size="sm"
                                    variant="danger"
                                    icon="trash"
                                    :tooltip="__('Eliminar tipo')"
                                    wire:click="confirmarEliminacion({{ $fila->id }})"
                                />
                            @endcan
                        </x-mantenedor.acciones>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">
                        <flux:text variant="subtle">{{ __('No hay tipos que coincidan con los filtros.') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @canany(["tipos.crear", "tipos.editar"])
    <flux:modal name="tipo" class="md:w-[32rem]">
        <form wire:submit="guardar" class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">
                    {{ $tipoEnEdicion === null ? __('Nuevo tipo') : __('Editar tipo') }}
                </flux:heading>
                <flux:subheading>
                    {{ __('Los indicadores definen qué datos exige el registro de un ítem de esta clase.') }}
                </flux:subheading>
            </div>

            <flux:input
                wire:model="nombre"
                :label="__('Nombre')"
                :placeholder="__('Clase de bien, por ejemplo Computador o Extintor')"
                autocomplete="off"
            />

            <flux:switch
                wire:model="requiereSerie"
                :label="__('Requiere número de serie')"
                :description="__('El número de serie pasa a ser obligatorio al registrar un ítem de este tipo.')"
            />

            <flux:switch
                wire:model="controlaVencimiento"
                :label="__('Controla vencimiento')"
                :description="__('La fecha de vencimiento pasa a ser obligatoria y el ítem aparece en las alertas.')"
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

    @can("tipos.eliminar")
    <x-mantenedor.confirmar-eliminacion
        modal="eliminar-tipo"
        :titulo="__('Eliminar tipo')"
        :mensaje="__('Se eliminará :nombre. Esta acción no se puede deshacer.', ['nombre' => $this->nombrePorEliminar])"
    />
    @endcan
</div>
