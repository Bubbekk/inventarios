<?php

use App\DTOs\Ubicaciones\GuardarUbicacionDTO;
use App\Services\Ubicaciones\UbicacionService;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Ubicaciones')] #[Lazy] class extends Component
{
    use WithPagination;

    #[Url(as: 'buscar', keep: false)]
    public string $busqueda = '';

    #[Url(as: 'tipo', keep: false)]
    public string $filtroTipo = '';

    public string $ordenarPor = 'nombre';

    public string $direccion = 'asc';

    public ?int $ubicacionEnEdicion = null;

    public string $nombre = '';

    public string $tipo = '';

    public string $direccionUbicacion = '';

    public ?int $ubicacionPorEliminar = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('ubicaciones.ver'), 403);
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
        if (in_array($propiedad, ['busqueda', 'filtroTipo'], true)) {
            $this->resetPage();
        }
    }

    public function limpiarFiltros(): void
    {
        $this->reset('busqueda', 'filtroTipo');
        $this->resetPage();
    }

    public function crear(): void
    {
        abort_unless(auth()->user()->can('ubicaciones.crear'), 403);

        $this->limpiarFormulario();
        $this->modal('ubicacion')->show();
    }

    public function editar(int $id, UbicacionService $servicio): void
    {
        abort_unless(auth()->user()->can('ubicaciones.editar'), 403);

        $ubicacion = $servicio->obtener($id);

        if ($ubicacion === null) {
            Flux::toast(variant: 'danger', text: __('La ubicación no existe.'));

            return;
        }

        $this->ubicacionEnEdicion = $ubicacion->id;
        $this->nombre = $ubicacion->nombre;
        $this->tipo = $ubicacion->tipo;
        $this->direccionUbicacion = $ubicacion->direccion ?? '';
        $this->resetValidation();

        $this->modal('ubicacion')->show();
    }

    public function guardar(UbicacionService $servicio): void
    {
        abort_unless(
            auth()->user()->can($this->ubicacionEnEdicion === null ? 'ubicaciones.crear' : 'ubicaciones.editar'),
            403,
        );

        $datos = $this->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'string', 'in:'.implode(',', array_keys(UbicacionService::TIPOS))],
            'direccionUbicacion' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $dto = GuardarUbicacionDTO::desdeArreglo([
                'nombre' => $datos['nombre'],
                'tipo' => $datos['tipo'],
                'direccion' => $datos['direccionUbicacion'] ?? null,
            ]);

            if ($this->ubicacionEnEdicion === null) {
                $servicio->crear($dto);
            } else {
                $servicio->actualizar($this->ubicacionEnEdicion, $dto);
            }
        } catch (\DomainException $error) {
            $this->addError('nombre', $error->getMessage());

            return;
        }

        $this->modal('ubicacion')->close();
        $this->limpiarFormulario();

        Flux::toast(variant: 'success', text: __('Ubicación guardada.'));
    }

    public function confirmarEliminacion(int $id): void
    {
        abort_unless(auth()->user()->can('ubicaciones.eliminar'), 403);

        $this->ubicacionPorEliminar = $id;
        $this->modal('eliminar-ubicacion')->show();
    }

    public function eliminar(UbicacionService $servicio): void
    {
        abort_unless(auth()->user()->can('ubicaciones.eliminar'), 403);

        if ($this->ubicacionPorEliminar === null) {
            return;
        }

        try {
            $servicio->eliminar($this->ubicacionPorEliminar);
        } catch (\DomainException $error) {
            $this->modal('eliminar-ubicacion')->close();
            $this->ubicacionPorEliminar = null;

            Flux::toast(variant: 'danger', text: $error->getMessage());

            return;
        }

        $this->modal('eliminar-ubicacion')->close();
        $this->ubicacionPorEliminar = null;

        Flux::toast(variant: 'success', text: __('Ubicación eliminada.'));
    }

    #[Computed]
    public function ubicaciones(): LengthAwarePaginator
    {
        return app(UbicacionService::class)->paginar(
            filtros: array_filter([
                'busqueda' => $this->busqueda !== '' ? $this->busqueda : null,
                'tipo' => $this->filtroTipo !== '' ? $this->filtroTipo : null,
            ], static fn ($valor): bool => $valor !== null),
            ordenarPor: $this->ordenarPor,
            direccion: $this->direccion,
        );
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function tipos(): array
    {
        return UbicacionService::TIPOS;
    }

    #[Computed]
    public function nombrePorEliminar(): string
    {
        if ($this->ubicacionPorEliminar === null) {
            return '';
        }

        return app(UbicacionService::class)->obtener($this->ubicacionPorEliminar)?->nombre ?? '';
    }

    private function limpiarFormulario(): void
    {
        $this->reset('ubicacionEnEdicion', 'nombre', 'tipo', 'direccionUbicacion');
        $this->resetValidation();
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-mantenedor.encabezado :titulo="__('Ubicaciones')" :descripcion="__('Dependencias, bodegas, vehículos y vía pública donde se encuentran los bienes.')">

        @can('ubicaciones.crear')
            <flux:button variant="primary" icon="plus" wire:click="crear">
                {{ __('Nueva ubicación') }}
            </flux:button>
        @endcan
    </x-mantenedor.encabezado>

    <flux:separator variant="subtle" />

    <x-mantenedor.filtros :columnas="3">
        <flux:input
            wire:model.live.debounce.400ms="busqueda"
            :label="__('Buscar')"
            :placeholder="__('Nombre o dirección de la ubicación')"
            icon="magnifying-glass"
            clearable
        />

        <flux:select wire:model.live="filtroTipo" :label="__('Tipo')" :placeholder="__('Todos los tipos')">
            <flux:select.option value="">{{ __('Todos los tipos') }}</flux:select.option>
            @foreach ($this->tipos as $clave => $etiqueta)
                <flux:select.option :value="$clave">{{ __($etiqueta) }}</flux:select.option>
            @endforeach
        </flux:select>

    </x-mantenedor.filtros>

    <flux:table :paginate="$this->ubicaciones">
        <flux:table.columns>
            <flux:table.column
                sortable
                :sorted="$ordenarPor === 'nombre'"
                :direction="$direccion"
                wire:click="ordenar('nombre')"
            >{{ __('Nombre') }}</flux:table.column>

            <flux:table.column
                sortable
                :sorted="$ordenarPor === 'tipo'"
                :direction="$direccion"
                wire:click="ordenar('tipo')"
            >{{ __('Tipo') }}</flux:table.column>

            <flux:table.column>{{ __('Dirección') }}</flux:table.column>

            <flux:table.column align="end">{{ __('Ítems') }}</flux:table.column>

            <flux:table.column
                sortable
                :sorted="$ordenarPor === 'created_at'"
                :direction="$direccion"
                wire:click="ordenar('created_at')"
            >{{ __('Creada') }}</flux:table.column>

            <flux:table.column align="end">{{ __('Acciones') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->ubicaciones as $fila)
                <flux:table.row :key="$fila->id">
                    <flux:table.cell variant="strong">{{ $fila->nombre }}</flux:table.cell>

                    <flux:table.cell>
                        <flux:badge size="sm" :color="match ($fila->tipo) {
                            'oficina' => 'blue',
                            'bodega' => 'amber',
                            'vehiculo' => 'violet',
                            'via_publica' => 'teal',
                            default => null,
                        }">
                            {{ __(App\Services\Ubicaciones\UbicacionService::etiquetaTipo($fila->tipo)) }}
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell>
                        @if ($fila->direccion)
                            {{ $fila->direccion }}
                        @else
                            <flux:text variant="subtle">{{ __('Sin dirección') }}</flux:text>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell align="end">{{ $fila->items_asociados }}</flux:table.cell>

                    <flux:table.cell>
                        {{ \Illuminate\Support\Carbon::parse($fila->created_at)->format('d-m-Y') }}
                    </flux:table.cell>

                    <flux:table.cell align="end">
                        <x-mantenedor.acciones>
                            @can('ubicaciones.editar')
                                <flux:button
                                    size="sm"
                                    variant="primary"
                                    icon="pencil-square"
                                    :tooltip="__('Editar ubicación')"
                                    wire:click="editar({{ $fila->id }})"
                                />
                            @endcan

                            @can('ubicaciones.eliminar')
                                <flux:button
                                    size="sm"
                                    variant="danger"
                                    icon="trash"
                                    :tooltip="__('Eliminar ubicación')"
                                    wire:click="confirmarEliminacion({{ $fila->id }})"
                                />
                            @endcan
                        </x-mantenedor.acciones>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">
                        <flux:text variant="subtle">{{ __('No hay ubicaciones que coincidan con los filtros.') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @canany(['ubicaciones.crear', 'ubicaciones.editar'])
        <flux:modal name="ubicacion" class="md:w-[32rem]">
            <form wire:submit="guardar" class="flex flex-col gap-6">
                <div>
                    <flux:heading size="lg">
                        {{ $ubicacionEnEdicion === null ? __('Nueva ubicación') : __('Editar ubicación') }}
                    </flux:heading>
                    <flux:subheading>
                        {{ __('El tipo determina la naturaleza del lugar donde se resguarda el bien.') }}
                    </flux:subheading>
                </div>

                <flux:input
                    wire:model="nombre"
                    :label="__('Nombre')"
                    :placeholder="__('Dependencia o lugar, por ejemplo Central o Bodega norte')"
                    autocomplete="off"
                />

                <flux:select wire:model="tipo" :label="__('Tipo')" :placeholder="__('Seleccione el tipo de ubicación')">
                    @foreach ($this->tipos as $clave => $etiqueta)
                        <flux:select.option :value="$clave">{{ __($etiqueta) }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input
                    wire:model="direccionUbicacion"
                    :label="__('Dirección')"
                    :placeholder="__('Calle y número, o referencia del lugar. Opcional')"
                    autocomplete="off"
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

    @can('ubicaciones.eliminar')
        <x-mantenedor.confirmar-eliminacion
            modal="eliminar-ubicacion"
            :titulo="__('Eliminar ubicación')"
            :mensaje="__('Se eliminará :nombre. Esta acción no se puede deshacer.', ['nombre' => $this->nombrePorEliminar])"
        />
    @endcan
</div>
