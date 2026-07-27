<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 20)->default('unit')->index()->after('email');
            $table->foreignId('unit_kerja_id')->nullable()->after('role')
                ->constrained('unit_kerjas')->restrictOnDelete();
            $table->boolean('is_active')->default(true)->index()->after('password');
        });

        DB::table('users')->whereNull('unit_kerja_id')->update(['is_active' => false]);
        DB::statement(<<<'SQL'
            ALTER TABLE users
            ADD CONSTRAINT users_role_unit_scope_check
            CHECK (
                (role = 'pusat' AND unit_kerja_id IS NULL)
                OR (role = 'unit' AND (unit_kerja_id IS NOT NULL OR is_active = 0))
            )
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CHECK users_role_unit_scope_check');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('unit_kerja_id');
            $table->dropColumn(['role', 'is_active']);
        });
    }
};
