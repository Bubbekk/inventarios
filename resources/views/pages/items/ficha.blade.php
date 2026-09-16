<?php

use App\DTOs\Items\ItemDTO;
use App\Services\Auditoria\RegistroActividadService;
use App\Services\Items\ItemService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Ficha del ítem')] class extends Component
{
    public int $item;

    public function mount(int $item): void
    {
        abort_unless(auth()->user()->can('items.ver'), 403);

        $this->item = $item;

        abort_if($this->ficha === null, 404);
    }

    #[Computed]
    public function ficha(): ?ItemDTO
    {
        return app(ItemService::class)->obtener($this->item, incluirEliminados: true);
    }

    /**
     * @return array<int, ItemDTO>
     */
    #[Computed]
    public function componentes(): array
    {
        return app(ItemService::class)->componentesDe($this->item);
    }

    /**
     * @return array<int, object>
     */
    #[Computed]
    public function verificaciones(): array
    {
        return app(ItemService::class)->verificacionesDe($this->item);
    }

    /**
     * @return array<int, object>
     */
    #[Computed]
    public function cambios(): array
    {
        return app(RegistroActividadService::class)->historialDe('items', $this->item);
    }
}; ?>

<div class="flex flex-col gap-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex flex-col gap-1">
            <flux:heading size="xl" level="1">{{ $this->ficha->identificador() }}</flux:heading>
            <flux:subheading>{{ $this->ficha->tipoNombre }}</flux:subheading>

            <div class="mt-2 flex flex-wrap items-center gap-2">
                <flux:badge size="sm" :color="match ($this->ficha->estadoConservacion) {
                    'bueno' => 'green',
                    'regular' => 'amber',
                    'malo' => 'red',
                    default => null,
                }">
                    {{ __(App\Services\Items\ItemService::ESTADOS[$this->ficha->estadoConservacion] ?? $this->ficha->estadoConservacion) }}
                </flux:badge>

                <flux:badge size="sm" :color="match ($this->ficha->situacion) {
                    'registrado_daf' => 'green',
                    'sin_registro_daf' => 'sky',
                    'solicitud_baja' => 'amber',
                    'retirado' => 'red',
                    default => null,
                }">
                    {{ __(App\Services\Items\ItemService::SITUACIONES[$this->ficha->situacion] ?? $this->ficha->situacion) }}
                </flux:badge>

                @if ($this->ficha->eliminado)
                    <flux:badge size="sm" color="red" icon="trash">{{ __('Eliminado') }}</flux:badge>
                @endif
            </div>
        </div>

        <flux:button variant="primary" icon="arrow-left" :href="route('items.indice')" wire:navigate>
            {{ __('Volver al listado') }}
        </flux:button>
    </div>

    <flux:separator variant="subtle" />

    <flux:card class="flex flex-col gap-4">
        <flux:heading size="lg">{{ __('Datos del bien') }}</flux:heading>

        <div class="grid gap-x-8 gap-y-4 md:grid-cols-3">
            @foreach ([
                __('N.º de inventario DAF') => $this->ficha->numeroInventario,
                __('Código antiguo') => $this->ficha->codigoAntiguo,
                __('Tipo') => $this->ficha->tipoNombre,
                __('Marca') => $this->ficha->marca,
                __('Modelo') => $this->ficha->modelo,
                __('Número de serie') => $this->ficha->numeroSerie,
                __('Ubicación') => $this->ficha->ubicacionNombre,
                __('Responsable') => $this->ficha->funcionarioNombre,
                __('Forma parte de') => $this->ficha->itemPadreNumero,
                __('Fecha de vencimiento') => $this->ficha->fechaVencimiento
                    ? \Illuminate\Support\Carbon::parse($this->ficha->fechaVencimiento)->format('d-m-Y')
                    : null,
                __('Fecha de registro') => $this->ficha->creadoEn
                    ? \Illuminate\Support\Carbon::parse($this->ficha->creadoEn)->format('d-m-Y')
                    : null,
            ] as $etiqueta => $valor)
                <div class="flex flex-col gap-1">
                    <flux:text size="sm" variant="subtle">{{ $etiqueta }}</flux:text>
                    <flux:text>{{ $valor ?: '—' }}</flux:text>
                </div>
            @endforeach
        </div>

        @if ($this->ficha->descripcion)
            <flux:separator variant="subtle" />

            <div class="flex flex-col gap-1">
                <flux:text size="sm" variant="subtle">{{ __('Descripción') }}</flux:text>
                <flux:text>{{ $this->ficha->descripcion }}</flux:text>
            </div>
        @endif
    </flux:card>

    <div class="flex flex-col gap-4">
        <flux:heading size="lg">{{ __('Componentes') }}</flux:heading>

        @if ($this->componentes !== [])
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('N.º inventario') }}</flux:table.column>
                    <flux:table.column>{{ __('Tipo') }}</flux:table.column>
                    <flux:table.column>{{ __('Marca y modelo') }}</flux:table.column>
                    <flux:table.column>{{ __('Estado') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Acciones') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->componentes as $componente)
                        <flux:table.row :key="$componente->id">
                            <flux:table.cell variant="strong">{{ $componente->identificador() }}</flux:table.cell>
                            <flux:table.cell>{{ $componente->tipoNombre }}</flux:table.cell>
                            <flux:table.cell>{{ trim(($componente->marca ?? '').' '.($componente->modelo ?? '')) ?: '—' }}</flux:table.cell>
                            <flux:table.cell>
                                {{ __(App\Services\Items\ItemService::ESTADOS[$componente->estadoConservacion] ?? $componente->estadoConservacion) }}
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button
                                    size="sm"
                                    variant="primary"
                                    icon="eye"
                                    :tooltip="__('Ver ficha')"
                                    :href="route('items.ficha', $componente->id)"
                                    wire:navigate
                                />
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @else
            <flux:text variant="subtle">{{ __('Este bien no tiene componentes asociados.') }}</flux:text>
        @endif
    </div>

    <div class="flex flex-col gap-4">
        <flux:heading size="lg">{{ __('Historial de verificaciones') }}</flux:heading>

        @if ($this->verificaciones !== [])
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Proceso') }}</flux:table.column>
                    <flux:table.column>{{ __('Fecha') }}</flux:table.column>
                    <flux:table.column>{{ __('Resultado') }}</flux:table.column>
                    <flux:table.column>{{ __('Método') }}</flux:table.column>
                    <flux:table.column>{{ __('Ubicación observada') }}</flux:table.column>
                    <flux:table.column>{{ __('Verificó') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->verificaciones as $verificacion)
                        <flux:table.row :key="$verificacion->id">
                            <flux:table.cell variant="strong">{{ $verificacion->proceso_nombre }}</flux:table.cell>
                            <flux:table.cell>
                                {{ \Illuminate\Support\Carbon::parse($verificacion->verificado_at)->format('d-m-Y') }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="match ($verificacion->resultado) {
                                    'encontrado' => 'green',
                                    'no_encontrado' => 'red',
                                    'sin_registro' => 'sky',
                                    default => null,
                                }">
                                    {{ str_replace('_', ' ', $verificacion->resultado) }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>{{ $verificacion->metodo }}</flux:table.cell>
                            <flux:table.cell>{{ $verificacion->ubicacion_nombre ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $verificacion->verificador ?? '—' }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @else
            <flux:text variant="subtle">{{ __('Este bien todavía no ha sido verificado en ningún proceso.') }}</flux:text>
        @endif
    </div>

    <div class="flex flex-col gap-4">
        <flux:heading size="lg">{{ __('Historial de cambios') }}</flux:heading>

        @if ($this->cambios !== [])
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Fecha') }}</flux:table.column>
                    <flux:table.column>{{ __('Evento') }}</flux:table.column>
                    <flux:table.column>{{ __('Descripción') }}</flux:table.column>
                    <flux:table.column>{{ __('Cambios') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->cambios as $cambio)
                        <flux:table.row :key="$cambio->id">
                            <flux:table.cell>
                                {{ \Illuminate\Support\Carbon::parse($cambio->created_at)->format('d-m-Y H:i') }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="match ($cambio->event) {
                                    'created' => 'green',
                                    'updated' => 'amber',
                                    'deleted' => 'red',
                                    'restored' => 'sky',
                                    default => null,
                                }">{{ $cambio->event }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>{{ $cambio->description }}</flux:table.cell>
                            <flux:table.cell>
                                @php
                                    $detalle = json_decode($cambio->attribute_changes ?? '', true);
                                @endphp

                                @if (is_array($detalle) && isset($detalle['attributes']))
                                    <flux:text size="sm" variant="subtle">
                                        {{ implode(' · ', array_map(
                                            static fn ($clave, $valor): string => $clave.': '.(is_scalar($valor) ? (string) $valor : '—'),
                                            array_keys($detalle['attributes']),
                                            $detalle['attributes'],
                                        )) }}
                                    </flux:text>
                                @else
                                    <flux:text size="sm" variant="subtle">—</flux:text>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @else
            <flux:text variant="subtle">{{ __('No hay cambios registrados para este bien.') }}</flux:text>
        @endif
    </div>
</div>
