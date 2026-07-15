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
        // Widen the enum first so existing 'helper'/'requester' rows can be
        // rewritten to 'user' without truncation, then narrow it down to
        // the final FindBack role set.
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'helper', 'requester', 'user') NOT NULL DEFAULT 'user'");
        DB::statement("UPDATE users SET role = 'user' WHERE role IN ('helper', 'requester')");
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'user') NOT NULL DEFAULT 'user'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'helper', 'requester', 'user') NOT NULL DEFAULT 'user'");
        DB::statement("UPDATE users SET role = 'requester' WHERE role = 'user'");
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'helper', 'requester') NOT NULL DEFAULT 'requester'");
    }
};
