<?php

use App\DTOs\Items\GuardarItemDTO;
use App\Services\Funcionarios\FuncionarioService;
use App\Services\Items\ItemService;
use App\Services\Tipos\TipoService;
use App\Services\Ubicaciones\UbicacionService;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Ítems')] #[Lazy] class extends Component
{
    use WithPagination;

    #[Url(as: 'buscar', keep: false)]
    public string $busqueda = '';

    #[Url(as: 'tipo', keep: false)]
    public string $filtroTipo = '';

    #[Url(as: 'ubicacion', keep: false)]
    public string $filtroUbicacion = '';

    #[Url(as: 'funcionario', keep: false)]
    public string $filtroFuncionario = '';

    #[Url(as: 'estado', keep: false)]
    public string $filtroEstado = '';

    #[Url(as: 'situacion', keep: false)]
    public string $filtroSituacion = '';

    #[Url(as: 'vencimiento', keep: false)]
    public string $filtroVencimiento = '';

    #[Url(as: 'eliminados', keep: false)]
    public bool $verEliminados = false;

    public string $ordenarPor = 'numero_inventario';

    public string $direccion = 'asc';

    public ?int $itemEnEdicion = null;

    public string $numeroInventario = '';

    public string $codigoAntiguo = '';

    public string $tipoId = '';

    public string $ubicacionId = '';

    public string $funcionarioId = '';

    public string $itemPadreId = '';

    public string $marca = '';

    public string $modelo = '';

    public string $numeroSerie = '';

    public string $descripcion = '';

    public string $fechaVencimiento = '';

    public string $estadoConservacion = 'bueno';

    public string $situacion = '';

    public ?int $itemPorEliminar = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('items.ver'), 403);
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
        $filtros = ['busqueda', 'filtroTipo', 'filtroUbicacion', 'filtroFuncionario', 'filtroEstado', 'filtroSituacion', 'filtroVencimiento', 'verEliminados'];

        if (in_array($propiedad, $filtros, true)) {
            $this->resetPage();
        }
    }

    public function limpiarFiltros(): void
    {
        $this->reset('busqueda', 'filtroTipo', 'filtroUbicacion', 'filtroFuncionario', 'filtroEstado', 'filtroSituacion', 'filtroVencimiento');
        $this->resetPage();
    }

    public function crear(): void
    {
        abort_unless(auth()->user()->can('items.crear'), 403);

        $this->limpiarFormulario();
        $this->modal('item')->show();
    }

    public function editar(int $id, ItemService $servicio): void
    {
        abort_unless(auth()->user()->can('items.editar'), 403);

        $item = $servicio->obtener($id);

        if ($item === null) {
            Flux::toast(variant: 'danger', text: __('El ítem no existe.'));

            return;
        }

        $this->itemEnEdicion = $item->id;
        $this->numeroInventario = $item->numeroInventario ?? '';
        $this->codigoAntiguo = $item->codigoAntiguo ?? '';
        $this->tipoId = (string) $item->tipoId;
        $this->ubicacionId = (string) ($item->ubicacionId ?? '');
        $this->funcionarioId = (string) ($item->funcionarioId ?? '');
        $this->itemPadreId = (string) ($item->itemPadreId ?? '');
        $this->marca = $item->marca ?? '';
        $this->modelo = $item->modelo ?? '';
        $this->numeroSerie = $item->numeroSerie ?? '';
        $this->descripcion = $item->descripcion ?? '';
        $this->fechaVencimiento = $item->fechaVencimiento ?? '';
        $this->estadoConservacion = $item->estadoConservacion;
        $this->situacion = $item->situacion;
        $this->resetValidation();

        $this->modal('item')->show();
    }

    public function guardar(ItemService $servicio): void
    {
        abort_unless(
            auth()->user()->can($this->itemEnEdicion === null ? 'items.crear' : 'items.editar'),
            403,
        );

        $datos = $this->validate([
            'tipoId' => ['required', 'integer'],
            'estadoConservacion' => ['required', 'string', 'in:'.implode(',', array_keys(ItemService::ESTADOS))],
            'situacion' => ['nullable', 'string', 'in:'.implode(',', array_keys(ItemService::SITUACIONES))],
            'numeroInventario' => ['nullable', 'string', 'max:255'],
            'codigoAntiguo' => ['nullable', 'string', 'max:255'],
            'marca' => ['nullable', 'string', 'max:255'],
            'modelo' => ['nullable', 'string', 'max:255'],
            'numeroSerie' => ['nullable', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'fechaVencimiento' => ['nullable', 'date'],
        ]);

        try {
            $dto = GuardarItemDTO::desdeArreglo([
                'tipo_id' => $datos['tipoId'],
                'estado_conservacion' => $datos['estadoConservacion'],
                'situacion' => $datos['situacion'] ?? null,
                'numero_inventario' => $datos['numeroInventario'] ?? null,
                'codigo_antiguo' => $datos['codigoAntiguo'] ?? null,
                'ubicacion_id' => $this->ubicacionId,
                'funcionario_id' => $this->funcionarioId,
                'item_padre_id' => $this->itemPadreId,
                'marca' => $datos['marca'] ?? null,
                'modelo' => $datos['modelo'] ?? null,
                'numero_serie' => $datos['numeroSerie'] ?? null,
                'descripcion' => $datos['descripcion'] ?? null,
                'fecha_vencimiento' => $datos['fechaVencimiento'] ?? null,
            ]);

            if ($this->itemEnEdicion === null) {
                $servicio->crear($dto);
            } else {
                $servicio->actualizar($this->itemEnEdicion, $dto);
            }
        } catch (\DomainException $error) {
            $this->addError($this->campoDelError($error->getMessage()), $error->getMessage());

            return;
        }

        $this->modal('item')->close();
        $this->limpiarFormulario();

        Flux::toast(variant: 'success', text: __('Ítem guardado.'));
    }

    public function confirmarEliminacion(int $id): void
    {
        abort_unless(auth()->user()->can('items.eliminar'), 403);

        $this->itemPorEliminar = $id;
        $this->modal('eliminar-item')->show();
    }

    public function eliminar(ItemService $servicio): void
    {
        abort_unless(auth()->user()->can('items.eliminar'), 403);

        if ($this->itemPorEliminar === null) {
            return;
        }

        try {
            $servicio->eliminar($this->itemPorEliminar);
        } catch (\DomainException $error) {
            $this->modal('eliminar-item')->close();
            $this->itemPorEliminar = null;

            Flux::toast(variant: 'danger', text: $error->getMessage());

            return;
        }

        $this->modal('eliminar-item')->close();
        $this->itemPorEliminar = null;

        Flux::toast(variant: 'success', text: __('Ítem eliminado.'));
    }

    public function restaurar(int $id, ItemService $servicio): void
    {
        abort_unless(auth()->user()->can('items.restaurar'), 403);

        try {
            $servicio->restaurar($id);
        } catch (\DomainException $error) {
            Flux::toast(variant: 'danger', text: $error->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Ítem restaurado.'));
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        $servicio = app(ItemService::class);

        $filtros = array_filter([
            'busqueda' => $this->busqueda !== '' ? $this->busqueda : null,
            'tipo_id' => $this->filtroTipo !== '' ? (int) $this->filtroTipo : null,
            'ubicacion_id' => $this->filtroUbicacion !== '' ? (int) $this->filtroUbicacion : null,
            'funcionario_id' => $this->filtroFuncionario !== '' ? (int) $this->filtroFuncionario : null,
            'estado_conservacion' => $this->filtroEstado !== '' ? $this->filtroEstado : null,
            'situacion' => $this->filtroSituacion !== '' ? $this->filtroSituacion : null,
            'vencimiento' => $this->filtroVencimiento !== '' ? $this->filtroVencimiento : null,
        ], static fn ($valor): bool => $valor !== null);

        return $this->verEliminados
            ? $servicio->paginarEliminados($filtros, ordenarPor: $this->ordenarPor, direccion: $this->direccion)
            : $servicio->paginar($filtros, ordenarPor: $this->ordenarPor, direccion: $this->direccion);
    }

    /**
     * @return array<int, App\DTOs\Tipos\TipoDTO>
     */
    #[Computed]
    public function tipos(): array
    {
        return app(TipoService::class)->listar();
    }

    /**
     * @return array<int, App\DTOs\Ubicaciones\UbicacionDTO>
     */
    #[Computed]
    public function ubicaciones(): array
    {
        return app(UbicacionService::class)->listar();
    }

    /**
     * @return array<int, App\DTOs\Funcionarios\FuncionarioDTO>
     */
    #[Computed]
    public function funcionarios(): array
    {
        return app(FuncionarioService::class)->listarActivos();
    }

    /**
     * @return array<int, object>
     */
    #[Computed]
    public function candidatosAPadre(): array
    {
        return app(ItemService::class)->candidatosAPadre($this->itemEnEdicion);
    }

    /**
     * @return array<int, App\DTOs\Items\ItemDTO>
     */
    #[Computed]
    public function alertasVencimiento(): array
    {
        return app(ItemService::class)->proximosAVencer();
    }

    #[Computed]
    public function identificadorPorEliminar(): string
    {
        if ($this->itemPorEliminar === null) {
            return '';
        }

        return app(ItemService::class)->obtener($this->itemPorEliminar)?->identificador() ?? '';
    }

    /**
     * Asocia el mensaje de dominio al campo del formulario que lo origina.
     */
    private function campoDelError(string $mensaje): string
    {
        return match (true) {
            str_contains($mensaje, 'número de inventario') => 'numeroInventario',
            str_contains($mensaje, 'número de serie') => 'numeroSerie',
            str_contains($mensaje, 'fecha de vencimiento') => 'fechaVencimiento',
            str_contains($mensaje, 'componente') => 'itemPadreId',
            str_contains($mensaje, 'padre') => 'itemPadreId',
            default => 'tipoId',
        };
    }

    private function limpiarFormulario(): void
    {
        $this->reset(
            'itemEnEdicion', 'numeroInventario', 'codigoAntiguo', 'tipoId', 'ubicacionId',
            'funcionarioId', 'itemPadreId', 'marca', 'modelo', 'numeroSerie',
            'descripcion', 'fechaVencimiento', 'estadoConservacion', 'situacion',
        );
        $this->resetValidation();
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-mantenedor.encabezado :titulo="__('Ítems')" :descripcion="__('Registro maestro de los bienes de la Dirección.')">

        @can('items.crear')
            <flux:button variant="primary" icon="plus" wire:click="crear">
                {{ __('Nuevo ítem') }}
            </flux:button>
        @endcan
    </x-mantenedor.encabezado>

    @if ($this->alertasVencimiento !== [] && ! $verEliminados)
        <flux:callout icon="exclamation-triangle" color="amber">
            <flux:callout.heading>
                {{ trans_choice('{1}Hay 1 ítem vencido o próximo a vencer|[2,*]Hay :count ítems vencidos o próximos a vencer', count($this->alertasVencimiento), ['count' => count($this->alertasVencimiento)]) }}
            </flux:callout.heading>
            <flux:callout.text>
                @foreach (array_slice($this->alertasVencimiento, 0, 5) as $alerta)
                    {{ $alerta->identificador() }} · {{ $alerta->tipoNombre }} ·
                    {{ \Illuminate\Support\Carbon::parse($alerta->fechaVencimiento)->format('d-m-Y') }}@if (! $loop->last) · @endif
                @endforeach
            </flux:callout.text>
        </flux:callout>
    @endif

    <flux:separator variant="subtle" />

    <x-mantenedor.filtros :columnas="4">
        <flux:input
            wire:model.live.debounce.400ms="busqueda"
            :label="__('Buscar')"
            :placeholder="__('Número, código antiguo, serie, marca o modelo')"
            icon="magnifying-glass"
            clearable
        />

        <flux:select wire:model.live="filtroTipo" :label="__('Tipo')" :placeholder="__('Todos los tipos')">
            <flux:select.option value="">{{ __('Todos los tipos') }}</flux:select.option>
            @foreach ($this->tipos as $tipo)
                <flux:select.option :value="(string) $tipo->id">{{ $tipo->nombre }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="filtroUbicacion" :label="__('Ubicación')" :placeholder="__('Todas las ubicaciones')">
            <flux:select.option value="">{{ __('Todas las ubicaciones') }}</flux:select.option>
            @foreach ($this->ubicaciones as $ubicacion)
                <flux:select.option :value="(string) $ubicacion->id">{{ $ubicacion->nombre }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="filtroFuncionario" :label="__('Responsable')" :placeholder="__('Todos los responsables')">
            <flux:select.option value="">{{ __('Todos los responsables') }}</flux:select.option>
            @foreach ($this->funcionarios as $funcionario)
                <flux:select.option :value="(string) $funcionario->id">{{ $funcionario->nombreCompleto() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="filtroEstado" :label="__('Estado')" :placeholder="__('Todos los estados')">
            <flux:select.option value="">{{ __('Todos los estados') }}</flux:select.option>
            @foreach (App\Services\Items\ItemService::ESTADOS as $clave => $etiqueta)
                <flux:select.option :value="$clave">{{ __($etiqueta) }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="filtroSituacion" :label="__('Situación')" :placeholder="__('Todas las situaciones')">
            <flux:select.option value="">{{ __('Todas las situaciones') }}</flux:select.option>
            @foreach (App\Services\Items\ItemService::SITUACIONES as $clave => $etiqueta)
                <flux:select.option :value="$clave">{{ __($etiqueta) }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="filtroVencimiento" :label="__('Vencimiento')" :placeholder="__('Todos')">
            <flux:select.option value="">{{ __('Todos') }}</flux:select.option>
            <flux:select.option value="vencidos">{{ __('Vencidos') }}</flux:select.option>
            <flux:select.option value="por_vencer">{{ __('Por vencer en 30 días') }}</flux:select.option>
        </flux:select>

    </x-mantenedor.filtros>

    @can('items.restaurar')
        <flux:switch
            wire:model.live="verEliminados"
            :label="__('Ver ítems eliminados')"
            :description="__('La eliminación es lógica: el bien conserva su historial y puede restaurarse.')"
        />
    @endcan

    <flux:table :paginate="$this->items">
        <flux:table.columns>
            <flux:table.column
                sortable
                :sorted="$ordenarPor === 'numero_inventario'"
                :direction="$direccion"
                wire:click="ordenar('numero_inventario')"
            >{{ __('N.º inventario') }}</flux:table.column>

            <flux:table.column>{{ __('Tipo') }}</flux:table.column>

            <flux:table.column
                sortable
                :sorted="$ordenarPor === 'marca'"
                :direction="$direccion"
                wire:click="ordenar('marca')"
            >{{ __('Marca y modelo') }}</flux:table.column>

            <flux:table.column>{{ __('Ubicación') }}</flux:table.column>

            <flux:table.column>{{ __('Responsable') }}</flux:table.column>

            <flux:table.column
                sortable
                :sorted="$ordenarPor === 'estado_conservacion'"
                :direction="$direccion"
                wire:click="ordenar('estado_conservacion')"
            >{{ __('Estado') }}</flux:table.column>

            <flux:table.column
                sortable
                :sorted="$ordenarPor === 'situacion'"
                :direction="$direccion"
                wire:click="ordenar('situacion')"
            >{{ __('Situación') }}</flux:table.column>

            <flux:table.column align="end">{{ __('Acciones') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->items as $fila)
                <flux:table.row :key="$fila->id">
                    <flux:table.cell variant="strong">
                        {{ $fila->numero_inventario ?? $fila->codigo_antiguo ?? '—' }}
                        @if ($fila->item_padre_numero)
                            <flux:badge size="sm" color="sky" class="ms-1">{{ __('Componente') }}</flux:badge>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell>{{ $fila->tipo_nombre }}</flux:table.cell>

                    <flux:table.cell>
                        {{ trim(($fila->marca ?? '').' '.($fila->modelo ?? '')) ?: '—' }}
                    </flux:table.cell>

                    <flux:table.cell>{{ $fila->ubicacion_nombre ?? '—' }}</flux:table.cell>

                    <flux:table.cell>{{ $fila->funcionario_nombre ?: '—' }}</flux:table.cell>

                    <flux:table.cell>
                        <flux:badge size="sm" :color="match ($fila->estado_conservacion) {
                            'bueno' => 'green',
                            'regular' => 'amber',
                            'malo' => 'red',
                            default => null,
                        }">
                            {{ __(App\Services\Items\ItemService::ESTADOS[$fila->estado_conservacion] ?? $fila->estado_conservacion) }}
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:badge size="sm" :color="match ($fila->situacion) {
                            'registrado_daf' => 'green',
                            'sin_registro_daf' => 'sky',
                            'solicitud_baja' => 'amber',
                            'retirado' => 'red',
                            default => null,
                        }">
                            {{ __(App\Services\Items\ItemService::SITUACIONES[$fila->situacion] ?? $fila->situacion) }}
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell align="end">
                        <x-mantenedor.acciones>
                            @if ($verEliminados)
                                @can('items.restaurar')
                                    <flux:button
                                        size="sm"
                                        variant="primary"
                                        icon="arrow-uturn-left"
                                        :tooltip="__('Restaurar ítem')"
                                        wire:click="restaurar({{ $fila->id }})"
                                    />
                                @endcan
                            @else
                                <flux:button
                                    size="sm"
                                    variant="primary"
                                    icon="eye"
                                    :tooltip="__('Ver ficha')"
                                    :href="route('items.ficha', $fila->id)"
                                    wire:navigate
                                />

                                @can('items.editar')
                                    <flux:button
                                        size="sm"
                                        variant="primary"
                                        icon="pencil-square"
                                        :tooltip="__('Editar ítem')"
                                        wire:click="editar({{ $fila->id }})"
                                    />
                                @endcan

                                @can('items.eliminar')
                                    <flux:button
                                        size="sm"
                                        variant="danger"
                                        icon="trash"
                                        :tooltip="__('Eliminar ítem')"
                                        wire:click="confirmarEliminacion({{ $fila->id }})"
                                    />
                                @endcan
                            @endif
                        </x-mantenedor.acciones>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="8">
                        <flux:text variant="subtle">{{ __('No hay ítems que coincidan con los filtros.') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @canany(['items.crear', 'items.editar'])
        <flux:modal name="item" class="md:w-[48rem]">
            <form wire:submit="guardar" class="flex flex-col gap-6">
                <div>
                    <flux:heading size="lg">
                        {{ $itemEnEdicion === null ? __('Nuevo ítem') : __('Editar ítem') }}
                    </flux:heading>
                    <flux:subheading>
                        {{ __('El tipo determina si el número de serie y la fecha de vencimiento son obligatorios.') }}
                    </flux:subheading>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input
                        wire:model="numeroInventario"
                        :label="__('N.º de inventario DAF')"
                        :placeholder="__('10601003.192. Dejar en blanco si no tiene')"
                        autocomplete="off"
                    />

                    <flux:input
                        wire:model="codigoAntiguo"
                        :label="__('Código antiguo')"
                        :placeholder="__('Código de la etiqueta anterior. Opcional')"
                        autocomplete="off"
                    />

                    <flux:select wire:model="tipoId" :label="__('Tipo')" :placeholder="__('Seleccione la clase de bien')">
                        @foreach ($this->tipos as $tipo)
                            <flux:select.option :value="(string) $tipo->id">{{ $tipo->nombre }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="estadoConservacion" :label="__('Estado de conservación')" :placeholder="__('Seleccione el estado observado')">
                        @foreach (App\Services\Items\ItemService::ESTADOS as $clave => $etiqueta)
                            <flux:select.option :value="$clave">{{ __($etiqueta) }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="ubicacionId" :label="__('Ubicación')" :placeholder="__('Lugar donde se encuentra. Opcional')">
                        <flux:select.option value="">{{ __('Sin ubicación') }}</flux:select.option>
                        @foreach ($this->ubicaciones as $ubicacion)
                            <flux:select.option :value="(string) $ubicacion->id">{{ $ubicacion->nombre }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="funcionarioId" :label="__('Responsable')" :placeholder="__('Funcionario a cargo. Opcional')">
                        <flux:select.option value="">{{ __('Sin responsable') }}</flux:select.option>
                        @foreach ($this->funcionarios as $funcionario)
                            <flux:select.option :value="(string) $funcionario->id">{{ $funcionario->nombreCompleto() }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input
                        wire:model="marca"
                        :label="__('Marca')"
                        :placeholder="__('Fabricante del bien. Opcional')"
                        autocomplete="off"
                    />

                    <flux:input
                        wire:model="modelo"
                        :label="__('Modelo')"
                        :placeholder="__('Modelo del fabricante. Opcional')"
                        autocomplete="off"
                    />

                    <flux:input
                        wire:model="numeroSerie"
                        :label="__('Número de serie')"
                        :placeholder="__('Obligatorio si el tipo lo exige')"
                        autocomplete="off"
                    />

                    <flux:date-picker
                        wire:model="fechaVencimiento"
                        type="input"
                        clearable
                        :label="__('Fecha de vencimiento')"
                        :placeholder="__('Obligatoria si el tipo controla vencimiento')"
                    />

                    <flux:select wire:model="situacion" :label="__('Situación')" :placeholder="__('Se deriva del número DAF si se deja en blanco')">
                        <flux:select.option value="">{{ __('Derivar del número de inventario') }}</flux:select.option>
                        @foreach (App\Services\Items\ItemService::SITUACIONES as $clave => $etiqueta)
                            <flux:select.option :value="$clave">{{ __($etiqueta) }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="itemPadreId" :label="__('Forma parte de')" :placeholder="__('Kit o equipo del que es componente. Opcional')">
                        <flux:select.option value="">{{ __('No es componente de otro ítem') }}</flux:select.option>
                        @foreach ($this->candidatosAPadre as $candidato)
                            <flux:select.option :value="(string) $candidato->id">
                                {{ $candidato->numero_inventario ?? $candidato->codigo_antiguo ?? '#'.$candidato->id }} · {{ $candidato->tipo_nombre }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <flux:textarea
                    wire:model="descripcion"
                    :label="__('Descripción')"
                    :placeholder="__('Color, dimensiones, observaciones del bien. Opcional')"
                    rows="3"
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

    @can('items.eliminar')
        <x-mantenedor.confirmar-eliminacion
            modal="eliminar-item"
            :titulo="__('Eliminar ítem')"
            :mensaje="__('Se eliminará :identificador. La eliminación es lógica y puede restaurarse.', ['identificador' => $this->identificadorPorEliminar])"
        />
    @endcan
</div>
