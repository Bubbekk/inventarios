<?php

namespace App\DTOs\Funcionarios;

final readonly class GuardarFuncionarioDTO
{
    /**
     * @param  string|null  $rut  Sin puntos y con guion. Nulo cuando el responsable
     *                            no es una persona natural, como un cargo de turno.
     */
    public function __construct(
        public string $nombres,
        public string $apellidos,
        public bool $activo = true,
        public ?string $rut = null,
        public ?string $cargo = null,
    ) {}

    /**
     * @param  array{
     *     nombres: string,
     *     apellidos: string,
     *     activo?: bool,
     *     rut?: string|null,
     *     cargo?: string|null
     * }  $datos
     */
    public static function desdeArreglo(array $datos): self
    {
        $rut = trim((string) ($datos['rut'] ?? ''));
        $cargo = trim((string) ($datos['cargo'] ?? ''));

        return new self(
            nombres: trim($datos['nombres']),
            apellidos: trim($datos['apellidos']),
            activo: $datos['activo'] ?? true,
            rut: $rut === '' ? null : $rut,
            cargo: $cargo === '' ? null : $cargo,
        );
    }
}
