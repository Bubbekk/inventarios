<?php

namespace App\DTOs\Procesos;

final readonly class GuardarProcesoDTO
{
    public function __construct(
        public string $nombre,
        public string $fechaInicio,
        public int $userId,
    ) {}

    /**
     * @param  array{
     *     nombre: string,
     *     fecha_inicio: string,
     *     user_id: int|string
     * }  $datos
     */
    public static function desdeArreglo(array $datos): self
    {
        return new self(
            nombre: trim($datos['nombre']),
            fechaInicio: trim($datos['fecha_inicio']),
            userId: (int) $datos['user_id'],
        );
    }
}
