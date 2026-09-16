<?php

namespace App\DTOs\Usuarios;

final readonly class GuardarUsuarioDTO
{
    /**
     * @param  string|null  $clave  Contraseña en texto plano. Null al editar sin cambiarla.
     */
    public function __construct(
        public string $nombre,
        public string $correo,
        public string $rol,
        public bool $activo = true,
        public ?string $clave = null,
    ) {}

    /**
     * @param  array{
     *     nombre: string,
     *     correo: string,
     *     rol: string,
     *     activo?: bool,
     *     clave?: string|null
     * }  $datos
     */
    public static function desdeArreglo(array $datos): self
    {
        return new self(
            nombre: trim($datos['nombre']),
            correo: mb_strtolower(trim($datos['correo'])),
            rol: $datos['rol'],
            activo: $datos['activo'] ?? true,
            clave: ($datos['clave'] ?? null) === '' ? null : ($datos['clave'] ?? null),
        );
    }
}
