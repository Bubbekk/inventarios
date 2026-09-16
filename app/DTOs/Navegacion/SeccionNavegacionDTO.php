<?php

namespace App\DTOs\Navegacion;

final readonly class SeccionNavegacionDTO
{
    /**
     * @param  array<int, ModuloNavegacionDTO>  $modulos
     */
    public function __construct(
        public string $clave,
        public string $titulo,
        public string $icono,
        public string $ruta,
        public array $modulos = [],
    ) {}

    /**
     * @param  array{
     *     titulo: string,
     *     icono: string,
     *     ruta?: string|null,
     *     permiso?: string|null,
     *     modulos?: array<int, array{titulo: string, ruta: string, icono: string, permiso?: string|null}>
     * }  $datos
     * @param  array<int, ModuloNavegacionDTO>  $modulos
     */
    public static function desdeArreglo(string $clave, array $datos, array $modulos): self
    {
        return new self(
            clave: $clave,
            titulo: $datos['titulo'],
            icono: $datos['icono'],
            ruta: $datos['ruta'] ?? ($modulos[0]->ruta ?? ''),
            modulos: $modulos,
        );
    }
}
