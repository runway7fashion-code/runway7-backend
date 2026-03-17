<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VolunteerProfile extends Model
{
    protected $fillable = [
        'user_id', 'age', 'gender', 'tshirt_size', 'experience', 'comfortable_fast_paced',
        'full_availability', 'contribution', 'resume_link', 'instagram', 'location', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'age' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
