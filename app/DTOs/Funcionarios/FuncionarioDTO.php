<?php

namespace App\DTOs\Funcionarios;

final readonly class FuncionarioDTO
{
    public function __construct(
        public int $id,
        public string $nombres,
        public string $apellidos,
        public bool $activo,
        public ?string $rut = null,
        public ?string $cargo = null,
        public int $itemsAsociados = 0,
        public ?string $creadoEn = null,
    ) {}

    /**
     * Nombre completo, en el orden usado en los listados.
     */
    public function nombreCompleto(): string
    {
        return trim($this->apellidos.', '.$this->nombres);
    }

    /**
     * Construye el DTO a partir de una fila devuelta por el Repository.
     */
    public static function desdeFila(object $fila): self
    {
        return new self(
            id: (int) $fila->id,
            nombres: $fila->nombres,
            apellidos: $fila->apellidos,
            activo: (bool) $fila->activo,
            rut: $fila->rut ?? null,
            cargo: $fila->cargo ?? null,
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
