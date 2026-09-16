# Protocolo de Trabajo — Guideline Base

## 1. Comunicación

Las reglas R1 a R6 rigen exclusivamente la forma de comunicación. No eximen del cumplimiento de ninguna obligación técnica establecida en las secciones siguientes.

### R1 — Respuesta mínima
Responder únicamente lo preguntado.
- Prohibido agregar contexto no solicitado.
- Prohibido anticipar pasos futuros.
- Prohibido ofrecer alternativas no pedidas.
- Prohibido incluir código, archivos o elementos no requeridos explícitamente.

### R2 — Un paso a la vez
- Ejecutar un solo paso por respuesta.
- Detenerse al completarlo.
- No continuar sin confirmación explícita y textual del usuario ("continúa", "sí", "ok").
- El silencio no constituye confirmación.

### R3 — Prohibición de iniciativa
- No sugerir próximos pasos.
- No completar lo que "probablemente" se quiere.
- No asumir intenciones.
- Cada acción requiere orden explícita.

### R4 — Formato fijo
- Sin emojis.
- Sin negritas decorativas.
- Sin introducciones ni cierres corteses.
- Tono formal, técnico y directo.
- Longitud mínima necesaria.
- Español de Chile; prohibido el voseo y formas rioplatenses.

### R5 — Reinicio obligatorio
Al recibir un documento, archivo o bloque de código:
1. Suspender el procesamiento.
2. Releer este protocolo completo.
3. Restablecer todos los parámetros.
4. Recién entonces proceder.

### R6 — Verificación previa
Antes de cada respuesta, confirmar:
- ¿Fue solicitado explícitamente?
- ¿Es un solo paso?
- ¿Requiere confirmación antes de continuar?
- ¿Se recibieron archivos? Si es así, aplicar R5.

Si alguna verificación falla, no se responde esa parte.

---

## 2. Ubicación del protocolo

- El protocolo reside en `.ai/guidelines/protocolo-base.md`.
- Está prohibido redactar o modificar reglas directamente en `CLAUDE.md`, porque Laravel Boost regenera ese archivo y descarta cualquier cambio manual.
- Las reglas se incorporan a `CLAUDE.md` exclusivamente al ejecutar `php artisan boost:update`, que integra el contenido de `.ai/guidelines/`.
- Toda modificación del protocolo se hace en su archivo fuente y luego se ejecuta `boost:update` para propagarla.

---

## 3. Stack

| Componente | Versión / uso |
|---|---|
| PHP | 8.3 o superior |
| Laravel | 13 |
| Livewire | 4 |
| Flux UI Pro | 2.15.0 |
| Tailwind CSS | 4 |
| Laravel Fortify | Autenticación |
| Pest | Testing |
| Laravel Boost | Guidelines, MCP y `search-docs` |

- Está prohibido incorporar paquetes de interfaz distintos de Flux UI Pro.
- Está prohibido agregar dependencias sin autorización explícita.
- Ante dudas de versión o API, se consulta `search-docs` de Laravel Boost antes de escribir código.

---

## 4. Arquitectura

### 4.1 Flujo obligatorio

```
Componente Livewire → Service → Repository → Base de datos
        ↑                ↓
        └──── DTO ───────┘
```

- Los componentes Livewire solo invocan Services. Está prohibido acceder a Repositories o a la base de datos desde un componente.
- Los Services contienen la lógica de negocio, validaciones de dominio y manejo de transacciones (`DB::transaction`).
- Los Repositories contienen exclusivamente acceso a datos mediante Query Builder.
- Los datos viajan entre capas mediante DTOs; está prohibido pasar arreglos sueltos o el request completo.

### 4.2 Eloquent

- Está prohibido el uso de Eloquent.
- Única excepción: `app/Models/User.php` y `app/Actions/Fortify/`, por requerimiento de Fortify.

### 4.3 DTOs

- Clases `final readonly` con constructor promovido.
- Tipado estricto en todas las propiedades.
- Constructores estáticos con nombre en español (`desdeArreglo()`, `desdeFilas()`).
- Sin lógica de negocio.

### 4.4 Idioma del código

- Clases, métodos, variables, rutas y tablas en español.
- Se respetan las convenciones de terceros que dicten otra nomenclatura (por ejemplo, Spatie, Fortify, métodos del framework).

### 4.5 Migraciones

- Está prohibido crear migraciones que modifiquen tablas ya creadas por otra migración del proyecto. Toda columna, índice o restricción nueva se agrega directamente en la migración que crea la tabla.
- Tras modificar una migración existente se ejecuta `php artisan migrate:fresh --seed`.
- Única excepción: `2025_08_14_170933_add_two_factor_columns_to_users_table.php`, publicada por Fortify, que no se edita.

---

## 5. Restricciones de UI

### 5.1 Componentes

- Solo se usan componentes reales de Flux UI Pro verificados en `resources/views/flux`. Está prohibido inventar etiquetas o props.
- Todo componente Livewire debe construirse 100% con Flux UI Pro. Si no cumple, se rehace.
- Esta sección prevalece sobre cualquier ejemplo de la skill `flux-ui-pro`.

### 5.2 Botones

- Prohibidas las variantes `ghost` y `filled`.
- Las acciones de eliminar y cancelar usan obligatoriamente la variante `danger`.

### 5.3 Formularios

- Todo input lleva un `placeholder` explicativo del dato esperado.

### 5.4 Fechas

- Formato día-mes-año obligatorio en toda vista (`d-m-Y`).

### 5.5 Tablas

- Se usa obligatoriamente la familia `flux:table` (`flux:table.columns`, `flux:table.column`, `flux:table.rows`, `flux:table.row`, `flux:table.cell`).
- Ordenamiento cableado mediante `sortable`, `:sorted` y `:direction` en `flux:table.column`, con propiedades `$ordenarPor` y `$direccion` en el componente.
- Paginación mediante `:paginate` en `flux:table`, recibiendo un `LengthAwarePaginator` desde el Service.

### 5.6 Color

- Color de texto estandarizado en todas las vistas; prohibidos los tonos oscuros para texto.
- Única excepción: los contextos invertidos, donde `text-black` actúa bajo `dark:` sobre un fondo claro. Cambiarlo dejaría el texto ilegible.
- Cromática estructural (bordes, fondos, separadores) en escala `zinc`. Están prohibidas las escalas `gray`, `slate`, `neutral` y `stone`.

---

## 6. Componentes Livewire

- Single-file component (SFC) obligatorio. En `config/livewire.php`: `'make_command' => ['type' => 'sfc']`.
- Multi-file component (MFC) solo cuando el componente requiere JS o CSS propio. Los archivos se nombran `{componente}.js` y `{componente}.css`.
- Rutas declaradas con `Route::livewire()` y URL en español.
- Placeholder de carga diferida en `partials.placeholder`.

```php
Route::livewire('/sumarios/crear', 'pages::sumarios.crear')
    ->name('sumarios.crear');
```

---

## 7. Estructura de carpetas

```
app/
├── Actions/Fortify/
├── DTOs/
│   ├── {Dominio}/
│   └── Grafico/          ← transversal, exenta de la taxonomía por dominio
├── Models/               ← solo User.php
├── Providers/
├── Repositories/
│   └── {Dominio}/
└── Services/
    └── {Dominio}/
resources/views/
├── components/
├── flux/
├── pages/
└── partials/
    └── placeholder.blade.php
tests/
├── Feature/
└── Unit/
```

---

## 8. Gráficos Flux

### 8.1 Paleta y accesibilidad

- Paleta cerrada: solo se usan los colores definidos en el proyecto para series.
- Toda serie debe distinguirse por algo más que el color (leyenda, etiqueta o tooltip).
- Todo gráfico incluye `flux:chart.tooltip` y una descripción accesible.

### 8.2 Clases

- En el grupo `chart` se distinguen dos tipos de clase permitidos:
  - Clase de color: aplicable a elementos coloreables.
  - Clase de dimensión/layout: aplicable al contenedor.
- `flux:chart.axis` no recibe clases de color. Las etiquetas coloreables son sus hijos: `flux:chart.axis.tick`, `flux:chart.axis.grid`, `flux:chart.axis.line` y `flux:chart.axis.mark`.

### 8.3 SerieGraficoDTO — regla de tres capas

1. Repository: devuelve filas crudas (`array`).
2. Service: construye el DTO con `SerieGraficoDTO::desdeFilas()`.
3. Componente: consume el DTO y lo entrega a `flux:chart`.

`desdeFilas()` separa la columna SQL de la clave de salida:

```php
SerieGraficoDTO::desdeFilas(
    filas: $filas,
    columnaSql: 'total_casos',
    claveSalida: 'casos',
);
```

---

## 9. Repositories

Tipos de retorno permitidos:

| Caso | Tipo |
|---|---|
| Listado | `array` |
| Registro único | `?object` |
| Conteo, suma, existencia | escalar (`int`, `float`, `bool`) |
| Listado paginado | `LengthAwarePaginator` |

- Está prohibido devolver `Collection`.
- Todo método declara su tipo de retorno.

---

## 10. Autenticación

- Autenticación con Laravel Fortify.
- 2FA habilitado (`Features::twoFactorAuthentication()`), con confirmación de segundo factor y de contraseña.
- Passkeys habilitadas (`Features::passkeys()`), con confirmación de contraseña. Se administran desde `settings/security` y se respaldan en la tabla `passkeys`.
- Sin registro público: `Features::registration()` deshabilitado. Las cuentas se crean exclusivamente desde el mantenedor de usuarios.
- Middleware `verified` en todas las rutas protegidas.
- Rate limiting de login, 2FA y passkeys definido en `FortifyServiceProvider` mediante `RateLimiter::for()`.

---

## 11. Skills y documentación

- Skills instaladas: `livewire4` y `flux-ui-pro`.
- El catálogo de componentes Flux reside íntegramente en `.claude/skills/flux-ui-pro/` (SKILL.md, INDICE.md y fichas en `references/`). Este protocolo no lo duplica.
- En caso de conflicto, la sección 5 prevalece sobre los ejemplos de la skill.
- Los dominios sin skill se resuelven con `search-docs` de Laravel Boost.

---

## 12. Testing

- Todo Service tiene tests con Pest.
- Tests de Feature para rutas, autorización y componentes Livewire; tests Unit para DTOs.
- Nombres de tests en español.
- Está prohibido dar por terminada una funcionalidad sin que sus tests pasen (`php artisan test`).

---

## 13. Historial técnico

- Todo cambio relevante de arquitectura, dependencias o reglas se registra con fecha, descripción y motivo.
- Toda modificación de este protocolo se registra indicando las secciones afectadas.
- El historial técnico del proyecto reside en la carpeta `reportes/`.
