<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 50)->nullable()->after('name');
        });

        $used = [];
        $pusatAssigned = false;

        DB::table('users')->orderBy('id')->get(['id', 'email', 'role'])->each(
            function (object $user) use (&$used, &$pusatAssigned): void {
                $source = $user->role === 'pusat' && ! $pusatAssigned
                    ? 'admin.pusat'
                    : Str::before((string) $user->email, '@');

                if ($user->role === 'pusat' && ! $pusatAssigned) {
                    $pusatAssigned = true;
                }

                $base = Str::of($source)
                    ->lower()
                    ->replaceMatches('/[^a-z0-9._-]+/', '.')
                    ->trim('.-_')
                    ->limit(40, '')
                    ->toString();

                if (strlen($base) < 3) {
                    $base = "user.{$user->id}";
                }

                $username = in_array($base, $used, true) ? "{$base}.{$user->id}" : $base;
                $used[] = $username;

                DB::table('users')->where('id', $user->id)->update(['username' => $username]);
            },
        );

        Schema::table('users', function (Blueprint $table): void {
            $table->unique('username');
            $table->string('username', 50)->nullable(false)->change();
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('users')->whereNull('email')->orderBy('id')->get(['id', 'username'])->each(
            fn (object $user) => DB::table('users')->where('id', $user->id)->update([
                'email' => "{$user->username}@example.invalid",
            ]),
        );

        Schema::table('users', function (Blueprint $table): void {
            $table->string('email')->nullable(false)->change();
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
