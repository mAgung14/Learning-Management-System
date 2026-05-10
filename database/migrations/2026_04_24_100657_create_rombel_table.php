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
        Schema::create('rombel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelas_id')
                ->constrained('kelas')
                ->cascadeOnDelete();

            $table->foreignId('jurusan_id')
                ->constrained('jurusan')
                ->cascadeOnDelete();


            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rombel');
    }

};
