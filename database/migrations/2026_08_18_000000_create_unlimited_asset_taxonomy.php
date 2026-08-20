<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_category_levels', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('normalized_name')->unique();
            $table->unsignedInteger('position')->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('asset_category_nodes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_category_level_id')->constrained()->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('asset_category_nodes')->restrictOnDelete();
            $table->string('name');
            $table->string('normalized_name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->char('dashboard_color', 7)->nullable();
            $table->string('dashboard_color_source', 10)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->string('legacy_type', 16)->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['legacy_type', 'legacy_id']);
            $table->unique(
                ['asset_category_level_id', 'parent_id', 'normalized_name'],
                'asset_category_nodes_sibling_unique',
            );
            $table->index(['parent_id', 'sort_order']);
        });

        Schema::table('assets', function (Blueprint $table): void {
            $table->foreignId('asset_category_node_id')
                ->nullable()
                ->after('asset_subsystem_id')
                ->constrained('asset_category_nodes')
                ->restrictOnDelete();
            $table->index(['unit_kerja_id', 'asset_category_node_id']);
        });

        DB::statement('ALTER TABLE assets MODIFY asset_subsystem_id BIGINT UNSIGNED NULL');

        $now = now();
        foreach ([
            ['name' => 'Aset Prasarana Sintel', 'normalized_name' => 'aset prasarana sintel', 'position' => 1],
            ['name' => 'System', 'normalized_name' => 'system', 'position' => 2],
            ['name' => 'Subsystem', 'normalized_name' => 'subsystem', 'position' => 3],
        ] as $level) {
            DB::table('asset_category_levels')->updateOrInsert(
                ['position' => $level['position']],
                [...$level, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            );
        }

        $levels = DB::table('asset_category_levels')->pluck('id', 'position');
        $groupNodes = [];
        foreach (DB::table('asset_groups')->orderBy('id')->get() as $group) {
            $nodeId = $this->upsertLegacyNode('group', $group, (int) $levels[1], null, $now);
            $groupNodes[(int) $group->id] = $nodeId;
        }

        $systemNodes = [];
        foreach (DB::table('asset_systems')->orderBy('id')->get() as $system) {
            $nodeId = $this->upsertLegacyNode(
                'system',
                $system,
                (int) $levels[2],
                $groupNodes[(int) $system->asset_group_id] ?? null,
                $now,
            );
            $systemNodes[(int) $system->id] = $nodeId;
        }

        foreach (DB::table('asset_subsystems')->orderBy('id')->get() as $subsystem) {
            $this->upsertLegacyNode(
                'subsystem',
                $subsystem,
                (int) $levels[3],
                $systemNodes[(int) $subsystem->asset_system_id] ?? null,
                $now,
            );
        }

        DB::statement(<<<'SQL'
            UPDATE assets
            INNER JOIN asset_category_nodes
                ON asset_category_nodes.legacy_type = 'subsystem'
                AND asset_category_nodes.legacy_id = assets.asset_subsystem_id
            SET assets.asset_category_node_id = asset_category_nodes.id
            WHERE assets.asset_category_node_id IS NULL
            SQL);
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            $table->dropIndex(['unit_kerja_id', 'asset_category_node_id']);
            $table->dropForeign(['asset_category_node_id']);
            $table->dropColumn('asset_category_node_id');
        });

        if (DB::table('assets')->whereNull('asset_subsystem_id')->doesntExist()) {
            DB::statement('ALTER TABLE assets MODIFY asset_subsystem_id BIGINT UNSIGNED NOT NULL');
        }

        Schema::dropIfExists('asset_category_nodes');
        Schema::dropIfExists('asset_category_levels');
    }

    private function upsertLegacyNode(
        string $type,
        object $legacy,
        int $levelId,
        ?int $parentId,
        mixed $now,
    ): int {
        $attributes = [
            'asset_category_level_id' => $levelId,
            'parent_id' => $parentId,
            'name' => $legacy->name,
            'normalized_name' => $legacy->normalized_name,
            'sort_order' => $legacy->sort_order,
            'dashboard_color' => $legacy->dashboard_color ?? null,
            'dashboard_color_source' => $legacy->dashboard_color_source ?? null,
            'is_active' => $legacy->is_active,
            'deleted_at' => $legacy->deleted_at,
            'updated_at' => $now,
        ];

        DB::table('asset_category_nodes')->updateOrInsert(
            ['legacy_type' => $type, 'legacy_id' => $legacy->id],
            [...$attributes, 'created_at' => $legacy->created_at ?? $now],
        );

        return (int) DB::table('asset_category_nodes')
            ->where('legacy_type', $type)
            ->where('legacy_id', $legacy->id)
            ->value('id');
    }
};
