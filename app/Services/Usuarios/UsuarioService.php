<?php

namespace App\Services\Usuarios;

use App\DTOs\Auditoria\RegistroActividadDTO;
use App\DTOs\Usuarios\GuardarUsuarioDTO;
use App\DTOs\Usuarios\UsuarioDTO;
use App\Repositories\Usuarios\UsuarioRepository;
use App\Services\Auditoria\RegistroActividadService;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class UsuarioService
{
    /**
     * Módulo con el que se identifica la tabla en la bitácora de actividad.
     */
    private const MODULO = 'users';

    private const ROL_ADMINISTRADOR = 'administrador';

    public function __construct(
        private UsuarioRepository $repositorio,
        private RegistroActividadService $bitacora,
    ) {}

    /**
     * @param  array{busqueda?: string|null, rol?: string|null, activo?: bool|null}  $filtros
     */
    public function paginar(array $filtros = [], int $porPagina = 15, string $ordenarPor = 'name', string $direccion = 'asc'): LengthAwarePaginator
    {
        return $this->repositorio->paginar($filtros, $porPagina, $ordenarPor, $direccion);
    }

    public function obtener(int $id): ?UsuarioDTO
    {
        $fila = $this->repositorio->buscarPorId($id);

        return $fila === null ? null : UsuarioDTO::desdeFila($fila);
    }

    /**
     * @return array<int, string>
     */
    public function rolesDisponibles(): array
    {
        return array_map(static fn (object $rol): string => $rol->name, $this->repositorio->roles());
    }

    /**
     * Crea una cuenta con su rol. La contraseña la asigna quien administra.
     *
     * @throws DomainException
     */
    public function crear(GuardarUsuarioDTO $datos): int
    {
        if ($datos->clave === null) {
            throw new DomainException('La contraseña es obligatoria al crear una cuenta.');
        }

        if ($this->repositorio->existeCorreo($datos->correo)) {
            throw new DomainException('Ya existe una cuenta con ese correo.');
        }

        $rol = $this->rolObligatorio($datos->rol);

        $id = DB::transaction(function () use ($datos, $rol): int {
            $ahora = now();

            $id = $this->repositorio->insertar([
                'name' => $datos->nombre,
                'email' => $datos->correo,
                'password' => Hash::make($datos->clave),
                'activo' => $datos->activo,
                'email_verified_at' => $ahora,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);

            $this->repositorio->sincronizarRol($id, (int) $rol->id);

            return $id;
        });

        $this->olvidarCache();

        $this->bitacora->registrar(new RegistroActividadDTO(
            modulo: self::MODULO,
            evento: 'created',
            descripcion: 'Cuenta creada',
            registroId: $id,
            valoresNuevos: [
                'name' => $datos->nombre,
                'email' => $datos->correo,
                'rol' => $datos->rol,
                'activo' => $datos->activo,
            ],
        ));

        return $id;
    }

    /**
     * Actualiza nombre, correo y rol. La contraseña solo cambia si se informa.
     *
     * @throws DomainException
     */
    public function actualizar(int $id, GuardarUsuarioDTO $datos): void
    {
        $actual = $this->repositorio->buscarPorId($id);

        if ($actual === null) {
            throw new DomainException('La cuenta no existe.');
        }

        if ($this->repositorio->existeCorreo($datos->correo, $id)) {
            throw new DomainException('Ya existe una cuenta con ese correo.');
        }

        $rol = $this->rolObligatorio($datos->rol);

        $this->validarQueQuedeUnAdministrador($actual, $datos->rol, $datos->activo, $id);

        DB::transaction(function () use ($id, $datos, $rol): void {
            $atributos = [
                'name' => $datos->nombre,
                'email' => $datos->correo,
                'activo' => $datos->activo,
                'updated_at' => now(),
            ];

            if ($datos->clave !== null) {
                $atributos['password'] = Hash::make($datos->clave);
            }

            $this->repositorio->actualizar($id, $atributos);
            $this->repositorio->sincronizarRol($id, (int) $rol->id);
        });

        $this->olvidarCache();

        $this->bitacora->registrarActualizacion(
            modulo: self::MODULO,
            registroId: $id,
            anteriores: [
                'name' => $actual->name,
                'email' => $actual->email,
                'rol' => $actual->rol,
                'activo' => (bool) $actual->activo,
            ],
            nuevos: [
                'name' => $datos->nombre,
                'email' => $datos->correo,
                'rol' => $datos->rol,
                'activo' => $datos->activo,
            ],
            descripcion: 'Cuenta actualizada',
        );
    }

    /**
     * Activa o desactiva una cuenta. No se eliminan cuentas, para conservar el historial.
     *
     * @throws DomainException
     */
    public function cambiarEstado(int $id, bool $activo): void
    {
        $actual = $this->repositorio->buscarPorId($id);

        if ($actual === null) {
            throw new DomainException('La cuenta no existe.');
        }

        if ((bool) $actual->activo === $activo) {
            return;
        }

        if (! $activo) {
            $this->validarQueNoSeaLaPropiaCuenta($id);
            $this->validarQueQuedeUnAdministrador($actual, $actual->rol, false, $id);
        }

        $this->repositorio->cambiarEstado($id, $activo);

        $this->bitacora->registrar(new RegistroActividadDTO(
            modulo: self::MODULO,
            evento: $activo ? 'activated' : 'deactivated',
            descripcion: $activo ? 'Cuenta activada' : 'Cuenta desactivada',
            registroId: $id,
            valoresAnteriores: ['activo' => (bool) $actual->activo],
            valoresNuevos: ['activo' => $activo],
        ));
    }

    /**
     * @throws DomainException
     */
    private function rolObligatorio(string $nombre): object
    {
        $rol = $this->repositorio->rolPorNombre($nombre);

        if ($rol === null) {
            throw new DomainException('El rol indicado no existe.');
        }

        return $rol;
    }

    /**
     * @throws DomainException
     */
    private function validarQueNoSeaLaPropiaCuenta(int $id): void
    {
        if (Auth::id() === $id) {
            throw new DomainException('No puede desactivar su propia cuenta.');
        }
    }

    /**
     * Impide dejar el sistema sin ninguna cuenta de administrador activa.
     *
     * @throws DomainException
     */
    private function validarQueQuedeUnAdministrador(object $actual, ?string $rolNuevo, bool $activoNuevo, int $id): void
    {
        $dejaDeSerAdministradorActivo = $actual->rol === self::ROL_ADMINISTRADOR
            && (bool) $actual->activo
            && ($rolNuevo !== self::ROL_ADMINISTRADOR || ! $activoNuevo);

        if (! $dejaDeSerAdministradorActivo) {
            return;
        }

        if ($this->repositorio->contarActivosConRol(self::ROL_ADMINISTRADOR, $id) === 0) {
            throw new DomainException('El sistema debe conservar al menos una cuenta de administrador activa.');
        }
    }

    /**
     * El rol se escribe con Query Builder, por lo que la caché de Spatie se invalida a mano.
     */
    private function olvidarCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
