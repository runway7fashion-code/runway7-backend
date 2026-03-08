<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesRegistration extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_rep_id', 'designer_id', 'event_id', 'package_id',
        'agreed_price', 'downpayment', 'notes', 'status',
        'onboarded_at', 'onboarded_by', 'confirmed_at', 'confirmed_by',
    ];

    protected function casts(): array
    {
        return [
            'agreed_price'  => 'decimal:2',
            'downpayment'   => 'decimal:2',
            'onboarded_at'  => 'datetime',
            'confirmed_at'  => 'datetime',
        ];
    }

    public function salesRep()    { return $this->belongsTo(User::class, 'sales_rep_id'); }
    public function designer()    { return $this->belongsTo(User::class, 'designer_id'); }
    public function event()       { return $this->belongsTo(Event::class); }
    public function package()     { return $this->belongsTo(DesignerPackage::class, 'package_id'); }
    public function onboardedBy() { return $this->belongsTo(User::class, 'onboarded_by'); }
    public function confirmedBy() { return $this->belongsTo(User::class, 'confirmed_by'); }
    public function documents()   { return $this->hasMany(SalesDocument::class); }
}
