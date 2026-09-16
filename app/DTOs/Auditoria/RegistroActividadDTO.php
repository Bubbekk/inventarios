<?php

namespace App\DTOs\Auditoria;

final readonly class RegistroActividadDTO
{
    /**
     * @param  array<string, mixed>  $valoresAnteriores
     * @param  array<string, mixed>  $valoresNuevos
     * @param  array<string, mixed>  $propiedades
     */
    public function __construct(
        public string $modulo,
        public string $evento,
        public string $descripcion,
        public ?int $registroId = null,
        public ?int $causanteId = null,
        public array $valoresAnteriores = [],
        public array $valoresNuevos = [],
        public array $propiedades = [],
    ) {}

    /**
     * @param  array{
     *     modulo: string,
     *     evento: string,
     *     descripcion: string,
     *     registro_id?: int|null,
     *     causante_id?: int|null,
     *     valores_anteriores?: array<string, mixed>,
     *     valores_nuevos?: array<string, mixed>,
     *     propiedades?: array<string, mixed>
     * }  $datos
     */
    public static function desdeArreglo(array $datos): self
    {
        return new self(
            modulo: $datos['modulo'],
            evento: $datos['evento'],
            descripcion: $datos['descripcion'],
            registroId: $datos['registro_id'] ?? null,
            causanteId: $datos['causante_id'] ?? null,
            valoresAnteriores: $datos['valores_anteriores'] ?? [],
            valoresNuevos: $datos['valores_nuevos'] ?? [],
            propiedades: $datos['propiedades'] ?? [],
        );
    }
}
