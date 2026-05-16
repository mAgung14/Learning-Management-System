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
        // Tambah kolom extracted_text ke file_material untuk menyimpan hasil extract PDF
        if (!Schema::hasColumn('file_material', 'extracted_text')) {
            Schema::table('file_material', function (Blueprint $table) {
                $table->longText('extracted_text')->nullable()->after('nama_file');
            });
        }

        // Tabel bank_soal: menyimpan soal hasil generate AI
        if (!Schema::hasTable('bank_soal')) {
            Schema::create('bank_soal', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tugas_id')->constrained('tugas')->cascadeOnDelete();
                $table->foreignId('materi_id')->constrained('materi')->cascadeOnDelete();
                $table->foreignId('guru_id')->constrained('guru')->cascadeOnDelete();
                $table->text('pertanyaan');
                $table->text('jawaban')->nullable();
                $table->enum('tipe', ['essay'])->default('essay');
                $table->enum('tingkat_kesulitan', ['mudah', 'sedang', 'sulit'])->default('sedang');
                $table->enum('status', ['draft', 'published'])->default('draft');
                $table->unsignedInteger('urutan')->default(0);
                $table->timestamps();
            });
        }

        // Tabel ai_generate_logs: log setiap proses generate AI
        if (!Schema::hasTable('ai_generate_logs')) {
            Schema::create('ai_generate_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tugas_id')->constrained('tugas')->cascadeOnDelete();
                $table->foreignId('materi_id')->constrained('materi')->cascadeOnDelete();
                $table->foreignId('guru_id')->constrained('guru')->cascadeOnDelete();
                $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
                $table->unsignedInteger('jumlah_soal_diminta')->default(5);
                $table->unsignedInteger('jumlah_soal_generated')->default(0);
                $table->text('error_message')->nullable();
                $table->longText('raw_response')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_generate_logs');
        Schema::dropIfExists('bank_soal');

        Schema::table('file_material', function (Blueprint $table) {
            $table->dropColumn('extracted_text');
        });
    }
};
