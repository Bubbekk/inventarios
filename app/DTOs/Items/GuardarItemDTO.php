<?php

namespace App\DTOs\Items;

final readonly class GuardarItemDTO
{
    /**
     * @param  string|null  $situacion  Nulo deja que el Service la derive del número de inventario.
     */
    public function __construct(
        public int $tipoId,
        public string $estadoConservacion,
        public ?string $numeroInventario = null,
        public ?string $codigoAntiguo = null,
        public ?int $ubicacionId = null,
        public ?int $funcionarioId = null,
        public ?int $itemPadreId = null,
        public ?string $marca = null,
        public ?string $modelo = null,
        public ?string $numeroSerie = null,
        public ?string $descripcion = null,
        public ?string $fechaVencimiento = null,
        public ?string $situacion = null,
    ) {}

    /**
     * @param  array{
     *     tipo_id: int|string,
     *     estado_conservacion: string,
     *     numero_inventario?: string|null,
     *     codigo_antiguo?: string|null,
     *     ubicacion_id?: int|string|null,
     *     funcionario_id?: int|string|null,
     *     item_padre_id?: int|string|null,
     *     marca?: string|null,
     *     modelo?: string|null,
     *     numero_serie?: string|null,
     *     descripcion?: string|null,
     *     fecha_vencimiento?: string|null,
     *     situacion?: string|null
     * }  $datos
     */
    public static function desdeArreglo(array $datos): self
    {
        return new self(
            tipoId: (int) $datos['tipo_id'],
            estadoConservacion: $datos['estado_conservacion'],
            numeroInventario: self::texto($datos['numero_inventario'] ?? null),
            codigoAntiguo: self::texto($datos['codigo_antiguo'] ?? null),
            ubicacionId: self::entero($datos['ubicacion_id'] ?? null),
            funcionarioId: self::entero($datos['funcionario_id'] ?? null),
            itemPadreId: self::entero($datos['item_padre_id'] ?? null),
            marca: self::texto($datos['marca'] ?? null),
            modelo: self::texto($datos['modelo'] ?? null),
            numeroSerie: self::texto($datos['numero_serie'] ?? null),
            descripcion: self::texto($datos['descripcion'] ?? null),
            fechaVencimiento: self::texto($datos['fecha_vencimiento'] ?? null),
            situacion: self::texto($datos['situacion'] ?? null),
        );
    }

    /**
     * El número de inventario se trata siempre como texto: 10601018.10 y
     * 10601018.1 son bienes distintos y no admiten conversión numérica.
     */
    private static function texto(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $limpio = trim((string) $valor);

        return $limpio === '' ? null : $limpio;
    }

    private static function entero(mixed $valor): ?int
    {
        if ($valor === null || $valor === '' || $valor === 0 || $valor === '0') {
            return null;
        }

        return (int) $valor;
    }
}
