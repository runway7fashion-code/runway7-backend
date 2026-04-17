<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DesignerMaterial extends Model
{
    use HasFactory;

    protected $fillable = [
        'designer_id', 'event_id', 'show_id', 'name',
        'description', 'drive_link', 'drive_folder_id', 'drive_folder_url',
        'status', 'status_flow', 'type', 'upload_by', 'is_readonly', 'order',
    ];

    protected function casts(): array
    {
        return ['is_readonly' => 'boolean'];
    }

    // Status flow constants
    const FLOW_COLLABORATIVE = 'collaborative'; // pending → in_progress → completed → confirmed / observed
    const FLOW_SIMPLE = 'simple';               // pending → completed

    // All possible statuses
    const STATUS_PENDING     = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED   = 'completed';
    const STATUS_CONFIRMED   = 'confirmed';
    const STATUS_OBSERVED    = 'observed';

    // Material names (matching the 10 folders in Drive)
    const MATERIALS = [
        'Background'       => ['flow' => 'collaborative', 'upload_by' => 'operation', 'order' => 1],
        'Music'            => ['flow' => 'collaborative', 'upload_by' => 'designer',  'order' => 2],
        'Images'           => ['flow' => 'simple',        'upload_by' => 'designer',  'order' => 3],
        'Runway Logo'      => ['flow' => 'simple',        'upload_by' => 'operation', 'order' => 4, 'is_readonly' => true],
        'Bio'              => ['flow' => 'simple',        'upload_by' => 'designer',  'order' => 5],
        'Hair Mood Board'  => ['flow' => 'simple',        'upload_by' => 'operation', 'order' => 6],
        'Makeup Mood Board'=> ['flow' => 'simple',        'upload_by' => 'operation', 'order' => 7],
        'Brand Logo'       => ['flow' => 'simple',        'upload_by' => 'designer',  'order' => 8],
        'Designer Photo'   => ['flow' => 'simple',        'upload_by' => 'designer',  'order' => 9],
        'Artworks'         => ['flow' => 'simple',        'upload_by' => 'tickets',   'order' => 10, 'is_readonly' => true],
    ];

    // Relationships
    public function designer() { return $this->belongsTo(User::class, 'designer_id'); }
    public function event()    { return $this->belongsTo(Event::class); }
    public function show()     { return $this->belongsTo(Show::class); }

    public function files()          { return $this->hasMany(MaterialFile::class, 'material_id'); }
    public function bioContent()     { return $this->hasOne(MaterialBioContent::class, 'material_id'); }
    public function moodboardItems() { return $this->hasMany(MaterialMoodboardItem::class, 'material_id')->orderBy('order'); }

    // Helper methods
    public function isCollaborative(): bool { return $this->status_flow === self::FLOW_COLLABORATIVE; }
    public function isSimple(): bool        { return $this->status_flow === self::FLOW_SIMPLE; }
    public function isBio(): bool           { return $this->name === 'Bio'; }
    public function isMoodboard(): bool     { return in_array($this->name, ['Hair Mood Board', 'Makeup Mood Board']); }
}
