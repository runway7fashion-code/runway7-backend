<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModelProfile extends Model
{
    use HasFactory;

    protected $appends = ['comp_card_progress'];

    protected $fillable = [
        'user_id', 'birth_date', 'age', 'gender', 'location',
        'agency', 'is_agency', 'is_top', 'is_test_model',
        'instagram', 'participation_number',
        'height', 'bust', 'chest', 'waist', 'hips',
        'shoe_size', 'dress_size', 'body_type', 'ethnicity', 'hair',
        'photos', 'photo_1', 'photo_2', 'photo_3', 'photo_4',
        'compcard_completed', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'birth_date'         => 'date',
            'photos'             => 'array',
            'is_agency'          => 'boolean',
            'is_top'             => 'boolean',
            'is_test_model'      => 'boolean',
            'compcard_completed' => 'boolean',
        ];
    }

    public function user() { return $this->belongsTo(User::class); }

    public function isCompCardComplete(): bool
    {
        return $this->photo_1 && $this->photo_2 && $this->photo_3 && $this->photo_4;
    }

    public function getCompCardPhotosAttribute(): array
    {
        return array_values(array_filter([
            ['position' => 1, 'label' => 'Headshot',            'url' => $this->photo_1],
            ['position' => 2, 'label' => 'Full Body Front',     'url' => $this->photo_2],
            ['position' => 3, 'label' => 'Full Body Side',      'url' => $this->photo_3],
            ['position' => 4, 'label' => 'Creative/Editorial',  'url' => $this->photo_4],
        ], fn($photo) => $photo['url'] !== null));
    }

    public function getCompCardProgressAttribute(): int
    {
        $filled = collect([$this->photo_1, $this->photo_2, $this->photo_3, $this->photo_4])
            ->filter()->count();
        return (int) (($filled / 4) * 100);
    }
}
