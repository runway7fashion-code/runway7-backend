<?php

namespace App\Models\Sponsorship;

use Illuminate\Database\Eloquent\Model;

class PackageBenefit extends Model
{
    protected $table = 'sponsorship_package_benefits';

    protected $fillable = ['name', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function packages()
    {
        return $this->belongsToMany(
            Package::class,
            'sponsorship_package_benefit',
            'benefit_id',
            'package_id'
        )->withTimestamps();
    }
}
