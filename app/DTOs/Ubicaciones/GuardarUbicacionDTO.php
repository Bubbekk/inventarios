<?php

namespace App\DTOs\Ubicaciones;

final readonly class GuardarUbicacionDTO
{
    public function __construct(
        public string $nombre,
        public string $tipo,
        public ?string $direccion = null,
    ) {}

    /**
     * @param  array{
     *     nombre: string,
     *     tipo: string,
     *     direccion?: string|null
     * }  $datos
     */
    public static function desdeArreglo(array $datos): self
    {
        $direccion = trim((string) ($datos['direccion'] ?? ''));

        return new self(
            nombre: trim($datos['nombre']),
            tipo: $datos['tipo'],
            direccion: $direccion === '' ? null : $direccion,
        );
    }
}
