<?php

namespace App\Models\Sponsorship;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use SoftDeletes;

    protected $table = 'sponsorship_companies';

    protected $fillable = [
        'name',
        'website',
        'instagram',
        'logo',
        'industry',
        'country',
        'notes',
        'created_by_user_id',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function leads()
    {
        return $this->hasMany(Lead::class, 'company_id');
    }
}
