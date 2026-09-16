<?php

namespace App\DTOs\Usuarios;

final readonly class UsuarioDTO
{
    public function __construct(
        public int $id,
        public string $nombre,
        public string $correo,
        public bool $activo,
        public ?string $rol = null,
        public ?string $creadoEn = null,
        public bool $correoVerificado = false,
        public bool $segundoFactorConfirmado = false,
    ) {}

    /**
     * Construye el DTO a partir de una fila devuelta por el Repository.
     */
    public static function desdeFila(object $fila): self
    {
        return new self(
            id: (int) $fila->id,
            nombre: $fila->name,
            correo: $fila->email,
            activo: (bool) $fila->activo,
            rol: $fila->rol ?? null,
            creadoEn: $fila->created_at ?? null,
            correoVerificado: ($fila->email_verified_at ?? null) !== null,
            segundoFactorConfirmado: ($fila->two_factor_confirmed_at ?? null) !== null,
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
