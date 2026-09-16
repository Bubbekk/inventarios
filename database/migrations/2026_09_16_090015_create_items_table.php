<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('numero_inventario')->nullable()->unique();
            $table->string('codigo_antiguo')->nullable();
            $table->foreignId('tipo_id')->constrained('tipos')->restrictOnDelete();
            $table->foreignId('ubicacion_id')->nullable()->constrained('ubicaciones')->restrictOnDelete();
            $table->foreignId('funcionario_id')->nullable()->constrained('funcionarios')->restrictOnDelete();
            $table->foreignId('item_padre_id')->nullable()->constrained('items')->nullOnDelete();
            $table->string('marca')->nullable();
            $table->string('modelo')->nullable();
            $table->string('numero_serie')->nullable();
            $table->text('descripcion')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->enum('estado_conservacion', ['bueno', 'regular', 'malo']);
            $table->enum('situacion', ['registrado_daf', 'sin_registro_daf', 'solicitud_baja', 'retirado']);
            $table->timestamps();
            $table->softDeletes();

            $table->index('codigo_antiguo');
            $table->index('numero_serie');
            $table->index('marca');
            $table->index('modelo');
            $table->index('estado_conservacion');
            $table->index('situacion');
            $table->index('fecha_vencimiento');
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
