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
        Schema::create('verificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proceso_verificacion_id')->constrained('procesos_verificacion')->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('items')->restrictOnDelete();
            $table->foreignId('ubicacion_id')->nullable()->constrained('ubicaciones')->nullOnDelete();
            $table->foreignId('funcionario_id')->nullable()->constrained('funcionarios')->nullOnDelete();
            $table->enum('resultado', ['encontrado', 'no_encontrado', 'sin_registro']);
            $table->enum('metodo', ['fisica', 'declarada']);
            $table->enum('estado_conservacion', ['bueno', 'regular', 'malo'])->nullable();
            $table->string('codigo_observado')->nullable();
            $table->text('descripcion_observada')->nullable();
            $table->text('observacion')->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('verificado_at');
            $table->timestamps();

            $table->unique(['proceso_verificacion_id', 'item_id'], 'verificaciones_proceso_item_unique');
            $table->index('resultado');
            $table->index('metodo');
            $table->index('codigo_observado');
            $table->index('verificado_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verificaciones');
    }
};
