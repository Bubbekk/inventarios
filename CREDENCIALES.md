# Credenciales de acceso

Cuentas del entorno local de desarrollo. No corresponden a producción.

## Administrador

| Dato | Valor |
|---|---|
| id | 3 |
| Nombre | Javier Gonzalez |
| Correo | jaaviergoonzalez@gmail.com |
| Contraseña | admin |
| Rol | administrador |
| Estado | activa, con correo verificado |

El id cambia cada vez que se ejecuta `migrate:fresh`, porque la cuenta se recrea después de los seeders.

## Comando que la crea

En PowerShell las comillas dobles deben escaparse con `\"`, porque PowerShell las elimina al pasar los argumentos a un ejecutable nativo y tinker recibe la instrucción incompleta.

### PowerShell

```
php artisan tinker --execute 'use App\Models\User; $usuario = User::create([\"name\" => \"Javier Gonzalez\", \"email\" => \"jaaviergoonzalez@gmail.com\", \"password\" => \"admin\"]); $usuario->forceFill([\"email_verified_at\" => now(), \"activo\" => true])->save(); $usuario->assignRole(\"administrador\");'
```

### Git Bash, CMD o Linux

```
php artisan tinker --execute 'use App\Models\User; $usuario = User::create(["name" => "Javier Gonzalez", "email" => "jaaviergoonzalez@gmail.com", "password" => "admin"]); $usuario->forceFill(["email_verified_at" => now(), "activo" => true])->save(); $usuario->assignRole("administrador");'
```

### Verificación

```
php artisan tinker --execute 'use App\Models\User; use Illuminate\Support\Facades\Hash; $u = User::where(\"email\",\"jaaviergoonzalez@gmail.com\")->first(); echo $u->id.\"|\".$u->name.\"|activo=\".(int)$u->activo.\"|rol=\".$u->getRoleNames()->implode(\",\").\"|clave_ok=\".(int)Hash::check(\"admin\",$u->password);'
```

## Cuenta del seeder

`AdministradorSeeder` crea además `admin@dsp.cl` con la contraseña `password`, según los valores por omisión de `config/inventario.php`. En producción deben definirse `ADMIN_NOMBRE`, `ADMIN_CORREO` y `ADMIN_CLAVE` en el `.env` del servidor.

## Advertencias

- La contraseña `admin` tiene cinco caracteres y no cumple las reglas de `Password::default()` que aplica el mantenedor de usuarios. Se aceptó porque tinker escribe directamente sobre el modelo, sin pasar por esa validación.
- El token de parada `--%` de PowerShell no sirve en este caso: tinker responde `InvalidArgumentException Unexpected end of input`.
- Este archivo contiene contraseñas en texto plano. `.gitignore` no lo excluye, por lo que se versiona junto con el proyecto.
