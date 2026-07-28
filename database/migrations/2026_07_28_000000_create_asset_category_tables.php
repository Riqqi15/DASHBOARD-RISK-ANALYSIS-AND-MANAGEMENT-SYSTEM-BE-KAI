<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('normalized_name')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('asset_systems', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_group_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('normalized_name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['asset_group_id', 'normalized_name']);
        });

        Schema::create('asset_subsystems', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_system_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('normalized_name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['asset_system_id', 'normalized_name']);
        });

        Schema::create('asset_category_source_aliases', function (Blueprint $table): void {
            $table->id();
            $table->string('category_type', 16);
            $table->unsignedBigInteger('category_id');
            $table->string('source_path');
            $table->string('normalized_source_path');
            $table->string('workbook_name');
            $table->string('sheet_name');
            $table->dateTime('first_imported_at');
            $table->dateTime('last_imported_at');
            $table->timestamps();

            $table->unique(
                ['category_type', 'normalized_source_path'],
                'asset_category_alias_source_unique',
            );
            $table->index(['category_type', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_category_source_aliases');
        Schema::dropIfExists('asset_subsystems');
        Schema::dropIfExists('asset_systems');
        Schema::dropIfExists('asset_groups');
    }
};
