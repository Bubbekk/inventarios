<?php

namespace App\Services\Funcionarios;

use App\DTOs\Auditoria\RegistroActividadDTO;
use App\DTOs\Funcionarios\FuncionarioDTO;
use App\DTOs\Funcionarios\GuardarFuncionarioDTO;
use App\Repositories\Funcionarios\FuncionarioRepository;
use App\Services\Auditoria\RegistroActividadService;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class FuncionarioService
{
    /**
     * Módulo con el que se identifica la tabla en la bitácora de actividad.
     */
    private const MODULO = 'funcionarios';

    public function __construct(
        private FuncionarioRepository $repositorio,
        private RegistroActividadService $bitacora,
    ) {}

    /**
     * @param  array{busqueda?: string|null, activo?: bool|null, con_rut?: bool|null}  $filtros
     */
    public function paginar(array $filtros = [], int $porPagina = 15, string $ordenarPor = 'apellidos', string $direccion = 'asc'): LengthAwarePaginator
    {
        return $this->repositorio->paginar($filtros, $porPagina, $ordenarPor, $direccion);
    }

    public function obtener(int $id): ?FuncionarioDTO
    {
        $fila = $this->repositorio->buscarPorId($id);

        return $fila === null ? null : FuncionarioDTO::desdeFila($fila);
    }

    /**
     * Responsables activos, para los selectores de otros módulos.
     *
     * @return array<int, FuncionarioDTO>
     */
    public function listarActivos(): array
    {
        return array_map(
            static fn (object $fila): FuncionarioDTO => FuncionarioDTO::desdeFila($fila),
            $this->repositorio->activos(),
        );
    }

    /**
     * @throws DomainException
     */
    public function crear(GuardarFuncionarioDTO $datos): int
    {
        $rut = $this->rutValidado($datos->rut);

        $this->validarNombre($datos->nombres, $datos->apellidos);

        $id = DB::transaction(function () use ($datos, $rut): int {
            $ahora = now();

            return $this->repositorio->insertar([
                'rut' => $rut,
                'nombres' => $datos->nombres,
                'apellidos' => $datos->apellidos,
                'cargo' => $datos->cargo,
                'activo' => $datos->activo,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        });

        $this->bitacora->registrar(new RegistroActividadDTO(
            modulo: self::MODULO,
            evento: 'created',
            descripcion: 'Funcionario creado',
            registroId: $id,
            valoresNuevos: [
                'rut' => $rut,
                'nombres' => $datos->nombres,
                'apellidos' => $datos->apellidos,
                'cargo' => $datos->cargo,
                'activo' => $datos->activo,
            ],
        ));

        return $id;
    }

    /**
     * @throws DomainException
     */
    public function actualizar(int $id, GuardarFuncionarioDTO $datos): void
    {
        $actual = $this->repositorio->buscarPorId($id);

        if ($actual === null) {
            throw new DomainException('El funcionario no existe.');
        }

        $rut = $this->rutValidado($datos->rut, $id);

        $this->validarNombre($datos->nombres, $datos->apellidos, $id);

        DB::transaction(function () use ($id, $datos, $rut): void {
            $this->repositorio->actualizar($id, [
                'rut' => $rut,
                'nombres' => $datos->nombres,
                'apellidos' => $datos->apellidos,
                'cargo' => $datos->cargo,
                'activo' => $datos->activo,
                'updated_at' => now(),
            ]);
        });

        $this->bitacora->registrarActualizacion(
            modulo: self::MODULO,
            registroId: $id,
            anteriores: [
                'rut' => $actual->rut,
                'nombres' => $actual->nombres,
                'apellidos' => $actual->apellidos,
                'cargo' => $actual->cargo,
                'activo' => (bool) $actual->activo,
            ],
            nuevos: [
                'rut' => $rut,
                'nombres' => $datos->nombres,
                'apellidos' => $datos->apellidos,
                'cargo' => $datos->cargo,
                'activo' => $datos->activo,
            ],
            descripcion: 'Funcionario actualizado',
        );
    }

    /**
     * Activa o desactiva al funcionario. No se elimina, para conservar el historial.
     *
     * @throws DomainException
     */
    public function cambiarEstado(int $id, bool $activo): void
    {
        $actual = $this->repositorio->buscarPorId($id);

        if ($actual === null) {
            throw new DomainException('El funcionario no existe.');
        }

        if ((bool) $actual->activo === $activo) {
            return;
        }

        $this->repositorio->cambiarEstado($id, $activo);

        $this->bitacora->registrar(new RegistroActividadDTO(
            modulo: self::MODULO,
            evento: $activo ? 'activated' : 'deactivated',
            descripcion: $activo ? 'Funcionario activado' : 'Funcionario desactivado',
            registroId: $id,
            valoresAnteriores: ['activo' => (bool) $actual->activo],
            valoresNuevos: ['activo' => $activo],
        ));
    }

    /**
     * Normaliza un RUT a la forma 12345678-9, sin puntos y con el dígito
     * verificador en mayúscula. Devuelve null si la entrada viene vacía.
     */
    public static function normalizarRut(?string $rut): ?string
    {
        if ($rut === null) {
            return null;
        }

        $limpio = strtoupper(preg_replace('/[^0-9kK]/', '', $rut) ?? '');

        if (strlen($limpio) < 2) {
            return $limpio === '' ? null : $limpio;
        }

        return substr($limpio, 0, -1).'-'.substr($limpio, -1);
    }

    /**
     * Verifica el dígito verificador por el algoritmo módulo 11.
     */
    public static function rutEsValido(string $rut): bool
    {
        $normalizado = self::normalizarRut($rut);

        if ($normalizado === null || ! preg_match('/^(\d{7,8})-([0-9K])$/', $normalizado, $partes)) {
            return false;
        }

        $cuerpo = $partes[1];
        $verificador = $partes[2];

        $suma = 0;
        $multiplicador = 2;

        for ($posicion = strlen($cuerpo) - 1; $posicion >= 0; $posicion--) {
            $suma += ((int) $cuerpo[$posicion]) * $multiplicador;
            $multiplicador = $multiplicador === 7 ? 2 : $multiplicador + 1;
        }

        $resto = 11 - ($suma % 11);

        $esperado = match ($resto) {
            11 => '0',
            10 => 'K',
            default => (string) $resto,
        };

        return $esperado === $verificador;
    }

    /**
     * Valida y normaliza el RUT cuando se informa. El nulo es válido: la DSP
     * registra responsables que no son personas naturales, como los cargos de turno.
     *
     * @throws DomainException
     */
    private function rutValidado(?string $rut, ?int $exceptoId = null): ?string
    {
        if ($rut === null || trim($rut) === '') {
            return null;
        }

        if (! self::rutEsValido($rut)) {
            throw new DomainException('El RUT informado no es válido.');
        }

        $normalizado = self::normalizarRut($rut);

        if ($this->repositorio->existeRut($normalizado, $exceptoId)) {
            throw new DomainException('Ya existe un funcionario con ese RUT.');
        }

        return $normalizado;
    }

    /**
     * @throws DomainException
     */
    private function validarNombre(string $nombres, string $apellidos, ?int $exceptoId = null): void
    {
        if ($nombres === '' || $apellidos === '') {
            throw new DomainException('Los nombres y apellidos son obligatorios.');
        }

        if ($this->repositorio->existeNombre($nombres, $apellidos, $exceptoId)) {
            throw new DomainException('Ya existe un funcionario con ese nombre y apellidos.');
        }
    }
}
