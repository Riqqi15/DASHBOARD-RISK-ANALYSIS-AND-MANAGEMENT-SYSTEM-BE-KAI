<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OPERATING_DATES = [
        'DAOP-1'    => '2020-01-01',
        'DAOP-4'    => '2017-01-01',
        'DAOP-8'    => '2017-01-01',
        'DIVRE-III' => '2017-01-01',
        'DIVRE-IV'  => '2017-01-01',
    ];

    public function up(): void
    {
        foreach (self::OPERATING_DATES as $code => $date) {
            DB::table('unit_kerjas')
                ->where('code', $code)
                ->update(['operating_start_date' => $date]);
        }
    }

    public function down(): void
    {
        DB::table('unit_kerjas')
            ->whereIn('code', array_keys(self::OPERATING_DATES))
            ->update(['operating_start_date' => null]);
    }
};
