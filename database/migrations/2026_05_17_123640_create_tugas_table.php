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
        Schema::create('tugas', function (Blueprint $table) {
        $table->id();
        $table->string('judul');
        $table->text('deskripsi');
        $table->dateTime('deadline');
        $table->foreignId('mapel_id')->constrained('mata_pelajaran')->cascadeOnDelete();
        $table->foreignId('guru_id')->constrained('guru')->cascadeOnDelete();
        $table->foreignId('rombel_id')->nullable()->constrained('rombel')->nullOnDelete();
        $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tugas');
    }
};
