<?php

namespace App\DTOs\Tipos;

final readonly class TipoDTO
{
    public function __construct(
        public int $id,
        public string $nombre,
        public bool $requiereSerie,
        public bool $controlaVencimiento,
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
            requiereSerie: (bool) $fila->requiere_serie,
            controlaVencimiento: (bool) $fila->controla_vencimiento,
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
