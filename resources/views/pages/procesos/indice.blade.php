<?php

use App\DTOs\Procesos\GuardarProcesoDTO;
use App\Services\Procesos\ProcesoService;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Procesos de verificación')] #[Lazy] class extends Component
{
    use WithPagination;

    #[Url(as: 'buscar', keep: false)]
    public string $busqueda = '';

    #[Url(as: 'estado', keep: false)]
    public string $filtroEstado = '';

    public string $ordenarPor = 'fecha_inicio';

    public string $direccion = 'desc';

    public ?int $procesoEnEdicion = null;

    public string $nombre = '';

    public string $fechaInicio = '';

    public ?int $procesoPorCerrar = null;

    public string $fechaCierre = '';

    public ?int $procesoPorEliminar = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('procesos.ver'), 403);

        $this->fechaInicio = now()->toDateString();
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
        if (in_array($propiedad, ['busqueda', 'filtroEstado'], true)) {
            $this->resetPage();
        }
    }

    public function limpiarFiltros(): void
    {
        $this->reset('busqueda', 'filtroEstado');
        $this->resetPage();
    }

    public function crear(): void
    {
        abort_unless(auth()->user()->can('procesos.crear'), 403);

        $this->limpiarFormulario();
        $this->modal('proceso')->show();
    }

    public function editar(int $id, ProcesoService $servicio): void
    {
        abort_unless(auth()->user()->can('procesos.crear'), 403);

        $proceso = $servicio->obtener($id);

        if ($proceso === null) {
            Flux::toast(variant: 'danger', text: __('El proceso no existe.'));

            return;
        }

        $this->procesoEnEdicion = $proceso->id;
        $this->nombre = $proceso->nombre;
        $this->fechaInicio = substr($proceso->fechaInicio, 0, 10);
        $this->resetValidation();

        $this->modal('proceso')->show();
    }

    public function guardar(ProcesoService $servicio): void
    {
        abort_unless(auth()->user()->can('procesos.crear'), 403);

        $datos = $this->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'fechaInicio' => ['required', 'date'],
        ]);

        try {
            $dto = GuardarProcesoDTO::desdeArreglo([
                'nombre' => $datos['nombre'],
                'fecha_inicio' => $datos['fechaInicio'],
                'user_id' => Auth::id(),
            ]);

            if ($this->procesoEnEdicion === null) {
                $servicio->crear($dto);
            } else {
                $servicio->actualizar($this->procesoEnEdicion, $dto);
            }
        } catch (\DomainException $error) {
            $this->addError('nombre', $error->getMessage());

            return;
        }

        $this->modal('proceso')->close();
        $this->limpiarFormulario();

        Flux::toast(variant: 'success', text: __('Proceso guardado.'));
    }

    public function confirmarCierre(int $id): void
    {
        abort_unless(auth()->user()->can('procesos.cerrar'), 403);

        $this->procesoPorCerrar = $id;
        $this->fechaCierre = now()->toDateString();
        $this->resetValidation();

        $this->modal('cerrar-proceso')->show();
    }

    public function cerrar(ProcesoService $servicio): void
    {
        abort_unless(auth()->user()->can('procesos.cerrar'), 403);

        if ($this->procesoPorCerrar === null) {
            return;
        }

        $this->validate(['fechaCierre' => ['required', 'date']]);

        try {
            $servicio->cerrar($this->procesoPorCerrar, $this->fechaCierre);
        } catch (\DomainException $error) {
            $this->addError('fechaCierre', $error->getMessage());

            return;
        }

        $this->modal('cerrar-proceso')->close();
        $this->procesoPorCerrar = null;

        Flux::toast(variant: 'success', text: __('Proceso cerrado.'));
    }

    public function reabrir(int $id, ProcesoService $servicio): void
    {
        abort_unless(auth()->user()->can('procesos.reabrir'), 403);

        try {
            $servicio->reabrir($id);
        } catch (\DomainException $error) {
            Flux::toast(variant: 'danger', text: $error->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Proceso reabierto.'));
    }

    public function confirmarEliminacion(int $id): void
    {
        abort_unless(auth()->user()->can('procesos.crear'), 403);

        $this->procesoPorEliminar = $id;
        $this->modal('eliminar-proceso')->show();
    }

    public function eliminar(ProcesoService $servicio): void
    {
        abort_unless(auth()->user()->can('procesos.crear'), 403);

        if ($this->procesoPorEliminar === null) {
            return;
        }

        try {
            $servicio->eliminar($this->procesoPorEliminar);
        } catch (\DomainException $error) {
            $this->modal('eliminar-proceso')->close();
            $this->procesoPorEliminar = null;

            Flux::toast(variant: 'danger', text: $error->getMessage());

            return;
        }

        $this->modal('eliminar-proceso')->close();
        $this->procesoPorEliminar = null;

        Flux::toast(variant: 'success', text: __('Proceso eliminado.'));
    }

    #[Computed]
    public function procesos(): LengthAwarePaginator
    {
        return app(ProcesoService::class)->paginar(
            filtros: array_filter([
                'busqueda' => $this->busqueda !== '' ? $this->busqueda : null,
                'estado' => $this->filtroEstado !== '' ? $this->filtroEstado : null,
            ], static fn ($valor): bool => $valor !== null),
            ordenarPor: $this->ordenarPor,
            direccion: $this->direccion,
        );
    }

    #[Computed]
    public function abierto(): ?App\DTOs\Procesos\ProcesoDTO
    {
        return app(ProcesoService::class)->abierto();
    }

    #[Computed]
    public function nombrePorEliminar(): string
    {
        if ($this->procesoPorEliminar === null) {
            return '';
        }

        return app(ProcesoService::class)->obtener($this->procesoPorEliminar)?->nombre ?? '';
    }

    private function limpiarFormulario(): void
    {
        $this->reset('procesoEnEdicion', 'nombre');
        $this->fechaInicio = now()->toDateString();
        $this->resetValidation();
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-mantenedor.encabezado
        :titulo="__('Procesos de verificación')"
        :descripcion="__('Tomas de inventario: apertura, avance y cierre.')"
    >
        @can('procesos.crear')
            <flux:button variant="primary" icon="plus" wire:click="crear" :disabled="$this->abierto !== null">
                {{ __('Nuevo proceso') }}
            </flux:button>
        @endcan
    </x-mantenedor.encabezado>

    @if ($this->abierto)
        <flux:card class="flex flex-col gap-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <flux:badge size="sm" color="green">{{ __('Proceso abierto') }}</flux:badge>
                    <flux:heading size="lg">{{ $this->abierto->nombre }}</flux:heading>
                    <flux:text variant="subtle">
                        {{ __('Inició el :fecha · Responsable: :responsable', [
                            'fecha' => \Illuminate\Support\Carbon::parse($this->abierto->fechaInicio)->format('d-m-Y'),
                            'responsable' => $this->abierto->responsable ?? '—',
                        ]) }}
                    </flux:text>
                </div>

                @can('procesos.cerrar')
                    <flux:button variant="danger" icon="lock-closed" wire:click="confirmarCierre({{ $this->abierto->id }})">
                        {{ __('Cerrar proceso') }}
                    </flux:button>
                @endcan
            </div>

            <div class="flex flex-col gap-2">
                <div class="flex items-center justify-between">
                    <flux:text size="sm" variant="subtle">
                        {{ __(':verificados de :total ítems verificados', [
                            'verificados' => $this->abierto->verificados,
                            'total' => $this->abierto->totalItems,
                        ]) }}
                    </flux:text>
                    <flux:text size="sm" variant="strong">{{ $this->abierto->porcentajeAvance() }}%</flux:text>
                </div>

                <flux:progress :value="$this->abierto->porcentajeAvance()" />

                <flux:text size="sm" variant="subtle">
                    {{ __(':pendientes pendientes · :sin_registro sin registro', [
                        'pendientes' => $this->abierto->pendientes(),
                        'sin_registro' => $this->abierto->sinRegistro,
                    ]) }}
                </flux:text>
            </div>
        </flux:card>
    @endif

    <flux:separator variant="subtle" />

    <x-mantenedor.filtros :columnas="3">
        <flux:input
            wire:model.live.debounce.400ms="busqueda"
            :label="__('Buscar')"
            :placeholder="__('Nombre del proceso de verificación')"
            icon="magnifying-glass"
            clearable
        />

        <flux:select wire:model.live="filtroEstado" :label="__('Estado')" :placeholder="__('Todos los estados')">
            <flux:select.option value="">{{ __('Todos los estados') }}</flux:select.option>
            @foreach (App\Services\Procesos\ProcesoService::ESTADOS as $clave => $etiqueta)
                <flux:select.option :value="$clave">{{ __($etiqueta) }}</flux:select.option>
            @endforeach
        </flux:select>
    </x-mantenedor.filtros>

    <flux:table :paginate="$this->procesos">
        <flux:table.columns>
            <flux:table.column
                sortable
                :sorted="$ordenarPor === 'nombre'"
                :direction="$direccion"
                wire:click="ordenar('nombre')"
            >{{ __('Proceso') }}</flux:table.column>

            <flux:table.column
                sortable
                :sorted="$ordenarPor === 'fecha_inicio'"
                :direction="$direccion"
                wire:click="ordenar('fecha_inicio')"
            >{{ __('Inicio') }}</flux:table.column>

            <flux:table.column
                sortable
                :sorted="$ordenarPor === 'fecha_cierre'"
                :direction="$direccion"
                wire:click="ordenar('fecha_cierre')"
            >{{ __('Cierre') }}</flux:table.column>

            <flux:table.column>{{ __('Responsable') }}</flux:table.column>

            <flux:table.column>{{ __('Avance') }}</flux:table.column>

            <flux:table.column
                sortable
                :sorted="$ordenarPor === 'estado'"
                :direction="$direccion"
                wire:click="ordenar('estado')"
            >{{ __('Estado') }}</flux:table.column>

            <flux:table.column align="end">{{ __('Acciones') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->procesos as $fila)
                @php
                    $avance = $fila->total_items > 0
                        ? (int) round($fila->verificados * 100 / $fila->total_items)
                        : 0;
                @endphp

                <flux:table.row :key="$fila->id">
                    <flux:table.cell variant="strong">{{ $fila->nombre }}</flux:table.cell>

                    <flux:table.cell>
                        {{ \Illuminate\Support\Carbon::parse($fila->fecha_inicio)->format('d-m-Y') }}
                    </flux:table.cell>

                    <flux:table.cell>
                        {{ $fila->fecha_cierre ? \Illuminate\Support\Carbon::parse($fila->fecha_cierre)->format('d-m-Y') : '—' }}
                    </flux:table.cell>

                    <flux:table.cell>{{ $fila->responsable ?? '—' }}</flux:table.cell>

                    <flux:table.cell>
                        {{ $fila->verificados }} / {{ $fila->total_items }}
                        <flux:text size="sm" variant="subtle" inline>({{ $avance }}%)</flux:text>
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:badge size="sm" :color="$fila->estado === 'abierto' ? 'green' : null">
                            {{ __(App\Services\Procesos\ProcesoService::ESTADOS[$fila->estado] ?? $fila->estado) }}
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell align="end">
                        <x-mantenedor.acciones>
                            @if ($fila->estado === 'abierto')
                                @can('procesos.crear')
                                    <flux:button
                                        size="sm"
                                        variant="primary"
                                        icon="pencil-square"
                                        :tooltip="__('Editar proceso')"
                                        wire:click="editar({{ $fila->id }})"
                                    />
                                @endcan

                                @can('procesos.cerrar')
                                    <flux:button
                                        size="sm"
                                        variant="danger"
                                        icon="lock-closed"
                                        :tooltip="__('Cerrar proceso')"
                                        wire:click="confirmarCierre({{ $fila->id }})"
                                    />
                                @endcan
                            @else
                                @can('procesos.reabrir')
                                    <flux:button
                                        size="sm"
                                        variant="primary"
                                        icon="lock-open"
                                        :tooltip="__('Reabrir proceso')"
                                        wire:click="reabrir({{ $fila->id }})"
                                        wire:confirm="{{ __('¿Reabrir este proceso? Quedará disponible para registrar verificaciones.') }}"
                                    />
                                @endcan
                            @endif

                            @can('procesos.crear')
                                @if ($fila->verificados === 0)
                                    <flux:button
                                        size="sm"
                                        variant="danger"
                                        icon="trash"
                                        :tooltip="__('Eliminar proceso')"
                                        wire:click="confirmarEliminacion({{ $fila->id }})"
                                    />
                                @endif
                            @endcan
                        </x-mantenedor.acciones>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7">
                        <flux:text variant="subtle">{{ __('No hay procesos que coincidan con los filtros.') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @can('procesos.crear')
        <flux:modal name="proceso" class="md:w-[32rem]">
            <form wire:submit="guardar" class="flex flex-col gap-6">
                <div>
                    <flux:heading size="lg">
                        {{ $procesoEnEdicion === null ? __('Nuevo proceso') : __('Editar proceso') }}
                    </flux:heading>
                    <flux:subheading>
                        {{ __('Solo puede haber un proceso abierto a la vez.') }}
                    </flux:subheading>
                </div>

                <flux:input
                    wire:model="nombre"
                    :label="__('Nombre')"
                    :placeholder="__('Denominación de la toma, por ejemplo Inventario 2026')"
                    autocomplete="off"
                />

                <flux:date-picker
                    wire:model="fechaInicio"
                    type="input"
                    :label="__('Fecha de inicio')"
                    :placeholder="__('Día en que comienza la toma de inventario')"
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

        <x-mantenedor.confirmar-eliminacion
            modal="eliminar-proceso"
            :titulo="__('Eliminar proceso')"
            :mensaje="__('Se eliminará :nombre. Solo se permite en procesos sin verificaciones.', ['nombre' => $this->nombrePorEliminar])"
        />
    @endcan

    @can('procesos.cerrar')
        <flux:modal name="cerrar-proceso" class="md:w-[28rem]">
            <form wire:submit="cerrar" class="flex flex-col gap-6">
                <div>
                    <flux:heading size="lg">{{ __('Cerrar proceso') }}</flux:heading>
                    <flux:subheading>
                        {{ __('Al cerrarlo no se podrán registrar más verificaciones. Un administrador puede reabrirlo.') }}
                    </flux:subheading>
                </div>

                <flux:date-picker
                    wire:model="fechaCierre"
                    type="input"
                    :label="__('Fecha de cierre')"
                    :placeholder="__('Día en que termina la toma de inventario')"
                />

                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="danger">{{ __('Cancelar') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="danger" icon="lock-closed">
                        {{ __('Cerrar proceso') }}
                    </flux:button>
                </div>
            </form>
        </flux:modal>
    @endcan
</div>
