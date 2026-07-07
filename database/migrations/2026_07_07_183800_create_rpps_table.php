<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rpps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guru_id')->constrained('guru')->cascadeOnDelete();
            $table->foreignId('mapel_id')->constrained('mata_pelajaran')->cascadeOnDelete();
            $table->foreignId('rombel_id')->nullable()->constrained('rombel')->cascadeOnDelete();
            $table->string('judul')->nullable();
            $table->text('deskripsi')->nullable();
            $table->text('kompetensi_dasar')->nullable();
            $table->text('indikator')->nullable();
            $table->text('tujuan_pembelajaran')->nullable();
            $table->enum('status', ['draft', 'submitted', 'approved'])->default('draft');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rpps');
    }
};
