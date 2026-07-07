<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rpp_pertemuans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rpp_id')->constrained('rpps')->cascadeOnDelete();
            $table->integer('pertemuan_ke');
            $table->string('topik')->nullable();
            $table->text('kegiatan_pendahuluan')->nullable();
            $table->text('kegiatan_inti')->nullable();
            $table->text('kegiatan_penutup')->nullable();
            $table->integer('alokasi_waktu')->nullable()->comment('dalam menit');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rpp_pertemuans');
    }
};
