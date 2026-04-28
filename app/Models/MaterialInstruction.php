<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialInstruction extends Model
{
    protected $fillable = ['material_name', 'instructions'];

    /**
     * Convenience: fetch all instructions keyed by material_name.
     */
    public static function map(): array
    {
        return self::pluck('instructions', 'material_name')->toArray();
    }
}
