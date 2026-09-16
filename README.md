# Sistema de Inventario DSP

Aplicación web para la gestión y el control del inventario de bienes de la Dirección de Seguridad Pública (DSP). Administra el registro maestro de bienes, los procesos periódicos de verificación (toma de inventario en terreno), la conciliación de las diferencias detectadas y la emisión de reportes para la Dirección.

El inventario no se importa desde planillas: se construye íntegramente desde el sistema, digitando el levantamiento real.

## Alcance funcional

- Catálogos maestros: tipos de bien, ubicaciones y funcionarios responsables.
- Registro de ítems con número de inventario DAF, código antiguo, serie, marca, modelo, estado de conservación, situación, vencimiento y jerarquía padre-hijo para kits y componentes.
- Procesos de verificación con ciclo de vida (apertura, avance, cierre y reapertura auditada), con un solo proceso abierto a la vez.
- Registro de verificaciones en terreno, con método físico o declarado, detección de ítems en ubicación distinta a la registrada, advertencia por código observado no coincidente y registro de bienes sin registro previo.
- Conciliación de resultados sobre el registro maestro, incluida la resolución de etiquetas cruzadas.
- Reportes por proceso, ubicación y funcionario, comparación entre procesos, reporte de vencimientos, acta de inventario por funcionario y exportación a Excel y PDF.
- Auditoría de cambios con valores anteriores y nuevos.
- Control de acceso por roles (administrador, verificador, consulta) y permisos por módulo.

## Modelo de datos

Tablas principales: `tipos`, `ubicaciones`, `funcionarios`, `items`, `procesos_verificacion`, `verificaciones`, más las tablas de `users`, roles y permisos, y el registro de actividad.

Reglas relevantes:

- `items.numero_inventario` es `varchar` y se trata siempre como texto. Está prohibido castearlo a numérico: `10601018.10` y `10601018.1` son bienes distintos.
- Índice único compuesto `(proceso_verificacion_id, item_id)` en `verificaciones`: una verificación por ítem por proceso.
- Eliminación lógica de ítems mediante `deleted_at`, filtrada explícitamente en cada Repository.

## Stack

| Componente | Versión |
|---|---|
| PHP | 8.3 o superior |
| Laravel | 13 |
| Livewire | 4 |
| Flux UI Pro | 2.19 |
| Tailwind CSS | 4 |
| Vite | 8 (vite-plus) |
| Laravel Fortify | Autenticación |
| MySQL | Base de datos |

Paquetes adicionales:

- `spatie/laravel-permission` — roles y permisos.
- `spatie/laravel-activitylog` — registro de actividad.
- `livewire/blaze` — optimización de renderizado de componentes Blade.
- `laravel/chisel`, `laravel/pao`, `laravel/tinker` — utilidades de desarrollo.
- `@laravel/passkeys` — autenticación sin contraseña.

Herramientas de calidad:

- `pestphp/pest` 5 — pruebas.
- `laravel/pint` — formateo de código.
- `larastan/larastan` — análisis estático.
- `laravel/boost` — guidelines y documentación asistida.

## Arquitectura

Flujo obligatorio de capas:

```
Componente Livewire → Service → Repository → Base de datos
        ↑                ↓
        └──── DTO ───────┘
```

- Los componentes Livewire solo invocan Services. No acceden a Repositories ni a la base de datos.
- Los Services contienen la lógica de negocio, las validaciones de dominio y el manejo de transacciones.
- Los Repositories contienen exclusivamente acceso a datos mediante Query Builder. Devuelven `array`, `?object`, escalares o `LengthAwarePaginator`; nunca `Collection`.
- Los datos viajan entre capas mediante DTOs `final readonly` con constructor promovido y tipado estricto.

### Restricción de Eloquent

El uso de Eloquent está prohibido en todo el proyecto. Las únicas excepciones son `app/Models/User.php` y `app/Actions/Fortify/`, por requerimiento de Fortify.

Consecuencias: no se usan `LogsActivity` ni `SoftDeletes`; la auditoría se emite explícitamente desde los Services con `activity()` y el filtrado de `deleted_at` es manual. Los datos de prueba se generan con constructores propios en `tests/` que insertan mediante Query Builder, no con factories.

### Idioma del código

Clases, métodos, variables, rutas y tablas en español. Se respetan las convenciones de terceros que dicten otra nomenclatura.

## Interfaz

- Toda vista se construye íntegramente con componentes de Flux UI Pro.
- Componentes Livewire en formato single-file (SFC); multi-file solo cuando se requiere JS o CSS propio.
- Rutas declaradas con `Route::livewire()` y URL en español.
- Tablas con la familia `flux:table`, ordenamiento cableado y paginación mediante `LengthAwarePaginator`.
- Fechas en formato `d-m-Y` en toda vista.
- Cromática estructural en escala `zinc`, con color de texto estandarizado.

Nota: las vistas publicadas de Flux UI Pro (`resources/views/flux`) no se versionan, por tratarse de un paquete comercial. Se obtienen al instalar las dependencias con credenciales válidas.

## Estructura

```
app/
├── Actions/Fortify/
├── DTOs/{Dominio}/
├── Models/               solo User.php
├── Providers/
├── Repositories/{Dominio}/
└── Services/{Dominio}/
resources/views/
├── components/
├── flux/                 no versionado
├── layouts/
├── pages/
└── partials/
tests/
├── Feature/
└── Unit/
```

## Autenticación

- Laravel Fortify con autenticación en dos factores habilitada.
- Sin registro público.
- Middleware `verified` en todas las rutas protegidas.
- Rate limiting de login y 2FA definido en `FortifyServiceProvider`.

## Instalación

Requiere PHP 8.3+, Composer, Node.js y MySQL. El acceso al repositorio Composer de Flux UI Pro requiere licencia y credenciales configuradas en `auth.json`.

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configurar la conexión a la base de datos en `.env` y ejecutar:

```bash
php artisan migrate --seed
npm install
npm run build
```

## Desarrollo

```bash
composer run dev
```

Levanta el servidor, el listener de colas y Vite en paralelo.

## Calidad

```bash
php artisan test        # pruebas Pest
composer run lint       # formateo con Pint
composer run types:check # análisis estático con PHPStan
composer run test       # cadena completa: lint, tipos y pruebas
```

Toda funcionalidad se cierra con sus pruebas en verde. Los Services se cubren con pruebas de Service, las rutas, autorizaciones y componentes Livewire con pruebas de Feature, y los DTOs con pruebas Unit. Los nombres de las pruebas se escriben en español.
