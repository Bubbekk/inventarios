<?php

namespace App\DTOs\Ubicaciones;

final readonly class UbicacionDTO
{
    public function __construct(
        public int $id,
        public string $nombre,
        public string $tipo,
        public ?string $direccion = null,
        public int $itemsAsociados = 0,
        public ?string $creadoEn = null,
    ) {}

    /**
     * Construye el DTO a partir de una fila devuelta por el Repository.
     */
    public static function desdeFila(object $fila): self
    {
        return new self(
            id: (int) $fila->id,
            nombre: $fila->nombre,
            tipo: $fila->tipo,
            direccion: $fila->direccion ?? null,
            itemsAsociados: (int) ($fila->items_asociados ?? 0),
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
}
