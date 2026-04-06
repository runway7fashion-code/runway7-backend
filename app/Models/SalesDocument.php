<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_registration_id', 'uploaded_by', 'type',
        'file_path', 'original_name', 'notes',
    ];

    public function registration() { return $this->belongsTo(SalesRegistration::class, 'sales_registration_id'); }
    public function uploader()     { return $this->belongsTo(User::class, 'uploaded_by'); }
}
