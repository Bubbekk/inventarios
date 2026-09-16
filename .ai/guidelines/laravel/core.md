# Hacer las cosas a la manera de Laravel

- Usar los comandos `php artisan make:` para crear archivos nuevos (por ejemplo migraciones, controladores). Los comandos disponibles se listan con `php artisan list` y sus parámetros se consultan con `php artisan [comando] --help`.
- Para crear una clase PHP genérica, usar `php artisan make:class`.
- Pasar `--no-interaction` a todos los comandos Artisan para que funcionen sin entrada del usuario. Pasar además las `--options` correctas para asegurar el comportamiento esperado.

## Generación de URLs

- Al generar enlaces a otras páginas, preferir rutas con nombre y la función `route()`.

## Error de Vite

- Ante el error "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest", ejecutar `npm run build` o solicitar al usuario que ejecute `npm run dev` o `composer run dev`.
