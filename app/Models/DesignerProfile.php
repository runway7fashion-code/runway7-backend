<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DesignerProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'category_id', 'sales_rep_id', 'brand_name', 'collection_name', 'website',
        'tracking_link', 'instagram', 'skype', 'social_media', 'bio', 'country',
    ];

    protected function casts(): array
    {
        return ['social_media' => 'array'];
    }

    public function user() { return $this->belongsTo(User::class); }
    public function category() { return $this->belongsTo(DesignerCategory::class, 'category_id'); }
    public function salesRep() { return $this->belongsTo(User::class, 'sales_rep_id'); }
}
