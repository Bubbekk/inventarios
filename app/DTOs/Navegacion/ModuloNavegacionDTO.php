<?php

namespace App\DTOs\Navegacion;

final readonly class ModuloNavegacionDTO
{
    public function __construct(
        public string $titulo,
        public string $ruta,
        public string $icono,
        public ?string $permiso = null,
    ) {}

    /**
     * @param  array{titulo: string, ruta: string, icono: string, permiso?: string|null}  $datos
     */
    public static function desdeArreglo(array $datos): self
    {
        return new self(
            titulo: $datos['titulo'],
            ruta: $datos['ruta'],
            icono: $datos['icono'],
            permiso: $datos['permiso'] ?? null,
        );
    }
}
