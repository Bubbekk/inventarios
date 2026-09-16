<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TiposSeeder extends Seeder
{
    /**
     * Catálogo inicial tomado de la columna "Artículo" de INVENTARIO DSP.xlsx.
     *
     * Los nombres se cargan tal como aparecen en la planilla, salvo la
     * unificación de mayúsculas: "Antena" y "antena" eran el mismo tipo con
     * distinta caja y colisionaban con el índice único.
     *
     * Formato: nombre => [requiere_serie, controla_vencimiento].
     *
     * @var array<string, array{0: bool, 1: bool}>
     */
    private const TIPOS = [
        'Antena' => [true, false],
        'Base Radial' => [true, false],
        'Batería Radio Comunicaciones' => [true, false],
        'Biblioteca' => [false, false],
        'Caja Fuerte' => [false, false],
        'Calefactor' => [false, false],
        'Camara De Seguridad' => [true, false],
        'Camara Gopro' => [true, false],
        'Camioneta' => [true, false],
        'Chalecos Anticortes' => [false, true],
        'Chalecos Antibala' => [false, true],
        'Cilindro De Gas' => [false, true],
        'Comando' => [true, false],
        'Comando Joystick' => [true, false],
        'Computador' => [true, false],
        'Computador All In One' => [true, false],
        'Cpu' => [true, false],
        'Diario Mural' => [false, false],
        'Disco Duro' => [true, false],
        'Dvr' => [true, false],
        'Escritorio' => [false, false],
        'Estacion De Trabajo' => [false, false],
        'Estufa A Gas' => [false, false],
        'Extintor' => [false, true],
        'Gabinete' => [false, false],
        'Grabadora' => [true, false],
        'Hervidor' => [false, false],
        'Hervidor Termo Halter' => [false, false],
        'Horno Electrico' => [false, false],
        'Impresora' => [true, false],
        'Kardex' => [false, false],
        'Kit Radio Comunicaciones Portatil' => [true, false],
        'Locker' => [false, false],
        'Mesa' => [false, false],
        'Mesa Cocina' => [false, false],
        'Mesa Plegable' => [false, false],
        'Mesa Reunión' => [false, false],
        'Microondas' => [false, false],
        'Monitor Led' => [true, false],
        'Monófono Radio' => [true, false],
        'Mouse' => [false, false],
        'Mueble Oficina' => [false, false],
        'Multicargador Radios (6 Slot)' => [true, false],
        'Notebook' => [true, false],
        'Proyector Led' => [true, false],
        'Radio Comunicaciones' => [true, false],
        'Radio Comunicaciones Portatil' => [true, false],
        'Radio Comunicaciones Radio Vhf' => [true, false],
        'Refrigerador' => [false, false],
        'Repisa' => [false, false],
        'Silla' => [false, false],
        'Silla Visita' => [false, false],
        'Sirena' => [true, false],
        'Smarthphone' => [true, false],
        'Switch' => [true, false],
        'Teclado' => [false, false],
        'Telon' => [false, false],
        'Termohervidor' => [false, false],
        'Tv Led' => [true, false],
        'Ventilador' => [false, false],
    ];

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $ahora = now();

        foreach (self::TIPOS as $nombre => [$requiereSerie, $controlaVencimiento]) {
            if (DB::table('tipos')->where('nombre', $nombre)->exists()) {
                continue;
            }

            DB::table('tipos')->insert([
                'nombre' => $nombre,
                'requiere_serie' => $requiereSerie,
                'controla_vencimiento' => $controlaVencimiento,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    /**
     * Cantidad de tipos del catálogo inicial.
     */
    public static function total(): int
    {
        return count(self::TIPOS);
    }
}
