<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('unit_kerja_id');
            $table->dropColumn(['role', 'is_active']);
        });
    }
};
