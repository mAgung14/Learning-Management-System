<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Menambahkan YOUTUBE ke dalam tipe ENUM
        DB::statement("ALTER TABLE file_material MODIFY COLUMN tipe ENUM('FILE', 'VIDEO', 'IMAGE', 'PDF', 'YOUTUBE') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE file_material MODIFY COLUMN tipe ENUM('FILE', 'VIDEO', 'IMAGE', 'PDF') NOT NULL");
    }
};
