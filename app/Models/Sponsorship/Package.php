<?php

namespace App\Models\Sponsorship;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    protected $table = 'sponsorship_packages';

    protected $fillable = [
        'name',
        'price',
        'assistants_count',
        'description',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'assistants_count' => 'integer',
        'is_active' => 'boolean',
    ];

    public function benefits()
    {
        return $this->belongsToMany(
            PackageBenefit::class,
            'sponsorship_package_benefit',
            'package_id',
            'benefit_id'
        )->withTimestamps();
    }
}
