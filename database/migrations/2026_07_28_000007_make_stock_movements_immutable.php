<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS stock_movements_prevent_update');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER stock_movements_prevent_update
            BEFORE UPDATE ON stock_movements
            FOR EACH ROW
            BEGIN
                IF COALESCE(@rams_allow_stock_movement_mutation, 0) <> 1 THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'stock_movements ledger is immutable; UPDATE is forbidden';
                END IF;
            END
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS stock_movements_prevent_delete');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER stock_movements_prevent_delete
            BEFORE DELETE ON stock_movements
            FOR EACH ROW
            BEGIN
                IF COALESCE(@rams_allow_stock_movement_mutation, 0) <> 1 THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'stock_movements ledger is immutable; DELETE is forbidden';
                END IF;
            END
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS stock_movements_prevent_update');
        DB::unprepared('DROP TRIGGER IF EXISTS stock_movements_prevent_delete');
    }
};
