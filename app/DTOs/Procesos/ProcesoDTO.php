<?php

namespace App\DTOs\Procesos;

final readonly class ProcesoDTO
{
    public function __construct(
        public int $id,
        public string $nombre,
        public string $fechaInicio,
        public string $estado,
        public int $userId,
        public ?string $fechaCierre = null,
        public ?string $responsable = null,
        public int $verificados = 0,
        public int $totalItems = 0,
        public int $sinRegistro = 0,
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
            fechaInicio: $fila->fecha_inicio,
            estado: $fila->estado,
            userId: (int) $fila->user_id,
            fechaCierre: $fila->fecha_cierre ?? null,
            responsable: $fila->responsable ?? null,
            verificados: (int) ($fila->verificados ?? 0),
            totalItems: (int) ($fila->total_items ?? 0),
            sinRegistro: (int) ($fila->sin_registro ?? 0),
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

    public function estaAbierto(): bool
    {
        return $this->estado === 'abierto';
    }

    /**
     * Porcentaje de avance sobre el total de ítems vigentes.
     */
    public function porcentajeAvance(): int
    {
        if ($this->totalItems === 0) {
            return 0;
        }

        return (int) round($this->verificados * 100 / $this->totalItems);
    }

    public function pendientes(): int
    {
        return max(0, $this->totalItems - $this->verificados);
    }
}
