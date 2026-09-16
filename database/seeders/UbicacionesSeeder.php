<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UbicacionesSeeder extends Seeder
{
    /**
     * Catálogo inicial tomado de la columna "Dependencia" de INVENTARIO DSP.xlsx.
     *
     * Queda fuera "Fuera de la dependencia", que en la planilla marca un estado
     * del bien y no un lugar físico, y se agrega "Vía pública" para las cámaras
     * instaladas en la ciudad, que la planilla anota en una columna aparte.
     *
     * Formato: nombre => tipo.
     *
     * @var array<string, string>
     */
    private const UBICACIONES = [
        'Bodega' => 'bodega',
        'Central' => 'oficina',
        'Cocina' => 'oficina',
        'Dep1' => 'oficina',
        'Dirección' => 'oficina',
        'Entrada' => 'oficina',
        'Lazos' => 'oficina',
        'MCSV' => 'oficina',
        'Vehiculo' => 'vehiculo',
        'Vía pública' => 'via_publica',
    ];

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $ahora = now();

        foreach (self::UBICACIONES as $nombre => $tipo) {
            if (DB::table('ubicaciones')->where('nombre', $nombre)->exists()) {
                continue;
            }

            DB::table('ubicaciones')->insert([
                'nombre' => $nombre,
                'tipo' => $tipo,
                'direccion' => null,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    /**
     * Cantidad de ubicaciones del catálogo inicial.
     */
    public static function total(): int
    {
        return count(self::UBICACIONES);
    }
}
