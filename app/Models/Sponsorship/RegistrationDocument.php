<?php

namespace App\Models\Sponsorship;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class RegistrationDocument extends Model
{
    protected $table = 'sponsorship_registration_documents';

    protected $fillable = [
        'registration_id',
        'uploaded_by_user_id',
        'type',
        'original_name',
        'path',
        'mime_type',
        'size',
        'note',
    ];

    public function registration()
    {
        return $this->belongsTo(Registration::class, 'registration_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
