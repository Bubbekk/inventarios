<?php

namespace App\DTOs\Tipos;

final readonly class GuardarTipoDTO
{
    public function __construct(
        public string $nombre,
        public bool $requiereSerie = false,
        public bool $controlaVencimiento = false,
    ) {}

    /**
     * @param  array{
     *     nombre: string,
     *     requiere_serie?: bool,
     *     controla_vencimiento?: bool
     * }  $datos
     */
    public static function desdeArreglo(array $datos): self
    {
        return new self(
            nombre: trim($datos['nombre']),
            requiereSerie: $datos['requiere_serie'] ?? false,
            controlaVencimiento: $datos['controla_vencimiento'] ?? false,
        );
    }
}
