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
        Schema::create('file_material', function (Blueprint $table) {
        $table->id();
        $table->foreignId('materi_id')->constrained('materi')->cascadeOnDelete();
        $table->enum('tipe', ['FILE', 'VIDEO', 'IMAGE', 'PDF', 'YOUTUBE']);
        $table->string('url');
        $table->string('nama_file');
        $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_material');
    }
};
