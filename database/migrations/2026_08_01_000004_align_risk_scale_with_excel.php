<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('risk_matrices')->where('likelihood', '>', 4)->update(['likelihood' => 4]);
        DB::table('risk_matrices')->where('consequence', '>', 4)->update(['consequence' => 4]);
        DB::table('risk_registers')->where('likelihood', '>', 4)->update(['likelihood' => 4]);
        DB::table('risk_registers')->where('consequence', '>', 4)->update(['consequence' => 4]);

        DB::statement('ALTER TABLE risk_matrices DROP CHECK chk_risk_matrices_likelihood');
        DB::statement('ALTER TABLE risk_matrices DROP CHECK chk_risk_matrices_consequence');
        DB::statement('ALTER TABLE risk_registers DROP CHECK chk_risk_registers_likelihood');
        DB::statement('ALTER TABLE risk_registers DROP CHECK chk_risk_registers_consequence');

        DB::statement('ALTER TABLE risk_matrices ADD CONSTRAINT chk_risk_matrices_likelihood CHECK (likelihood BETWEEN 1 AND 4)');
        DB::statement('ALTER TABLE risk_matrices ADD CONSTRAINT chk_risk_matrices_consequence CHECK (consequence BETWEEN 1 AND 4)');
        DB::statement('ALTER TABLE risk_registers ADD CONSTRAINT chk_risk_registers_likelihood CHECK (likelihood IS NULL OR likelihood BETWEEN 1 AND 4)');
        DB::statement('ALTER TABLE risk_registers ADD CONSTRAINT chk_risk_registers_consequence CHECK (consequence IS NULL OR consequence BETWEEN 1 AND 4)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE risk_matrices DROP CHECK chk_risk_matrices_likelihood');
        DB::statement('ALTER TABLE risk_matrices DROP CHECK chk_risk_matrices_consequence');
        DB::statement('ALTER TABLE risk_registers DROP CHECK chk_risk_registers_likelihood');
        DB::statement('ALTER TABLE risk_registers DROP CHECK chk_risk_registers_consequence');

        DB::statement('ALTER TABLE risk_matrices ADD CONSTRAINT chk_risk_matrices_likelihood CHECK (likelihood BETWEEN 1 AND 5)');
        DB::statement('ALTER TABLE risk_matrices ADD CONSTRAINT chk_risk_matrices_consequence CHECK (consequence BETWEEN 1 AND 5)');
        DB::statement('ALTER TABLE risk_registers ADD CONSTRAINT chk_risk_registers_likelihood CHECK (likelihood IS NULL OR likelihood BETWEEN 1 AND 5)');
        DB::statement('ALTER TABLE risk_registers ADD CONSTRAINT chk_risk_registers_consequence CHECK (consequence IS NULL OR consequence BETWEEN 1 AND 5)');
    }
};
