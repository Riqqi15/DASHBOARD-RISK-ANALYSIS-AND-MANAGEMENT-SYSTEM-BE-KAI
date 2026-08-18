<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'category_type',
    'category_id',
    'unit_kerja_id',
    'source_path',
    'normalized_source_path',
    'workbook_name',
    'sheet_name',
    'first_imported_at',
    'last_imported_at',
])]
class AssetCategorySourceAlias extends Model
{
    protected function casts(): array
    {
        return [
            'first_imported_at' => 'datetime',
            'last_imported_at' => 'datetime',
        ];
    }
}
