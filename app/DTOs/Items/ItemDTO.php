<?php

namespace App\DTOs\Items;

final readonly class ItemDTO
{
    public function __construct(
        public int $id,
        public int $tipoId,
        public string $estadoConservacion,
        public string $situacion,
        public ?string $numeroInventario = null,
        public ?string $codigoAntiguo = null,
        public ?int $ubicacionId = null,
        public ?int $funcionarioId = null,
        public ?int $itemPadreId = null,
        public ?string $marca = null,
        public ?string $modelo = null,
        public ?string $numeroSerie = null,
        public ?string $descripcion = null,
        public ?string $fechaVencimiento = null,
        public ?string $tipoNombre = null,
        public ?string $ubicacionNombre = null,
        public ?string $funcionarioNombre = null,
        public ?string $itemPadreNumero = null,
        public bool $eliminado = false,
        public ?string $creadoEn = null,
    ) {}

    /**
     * Construye el DTO a partir de una fila devuelta por el Repository.
     */
    public static function desdeFila(object $fila): self
    {
        return new self(
            id: (int) $fila->id,
            tipoId: (int) $fila->tipo_id,
            estadoConservacion: $fila->estado_conservacion,
            situacion: $fila->situacion,
            numeroInventario: $fila->numero_inventario ?? null,
            codigoAntiguo: $fila->codigo_antiguo ?? null,
            ubicacionId: isset($fila->ubicacion_id) ? (int) $fila->ubicacion_id : null,
            funcionarioId: isset($fila->funcionario_id) ? (int) $fila->funcionario_id : null,
            itemPadreId: isset($fila->item_padre_id) ? (int) $fila->item_padre_id : null,
            marca: $fila->marca ?? null,
            modelo: $fila->modelo ?? null,
            numeroSerie: $fila->numero_serie ?? null,
            descripcion: $fila->descripcion ?? null,
            fechaVencimiento: $fila->fecha_vencimiento ?? null,
            tipoNombre: $fila->tipo_nombre ?? null,
            ubicacionNombre: $fila->ubicacion_nombre ?? null,
            funcionarioNombre: $fila->funcionario_nombre ?? null,
            itemPadreNumero: $fila->item_padre_numero ?? null,
            eliminado: ($fila->deleted_at ?? null) !== null,
            creadoEn: $fila->created_at ?? null,
        );
    }

    /**
     * @param  array<int, object>  $filas
     * @return array<int, self>
     */
    public static function desdeFilas(array $filas): array
    {
        return array_map(static fn (object $fila): self => self::desdeFila($fila), $filas);
    }

    /**
     * Identificador visible del bien: el número DAF si lo tiene, si no el código antiguo.
     */
    public function identificador(): string
    {
        return $this->numeroInventario ?? $this->codigoAntiguo ?? '—';
    }
}
