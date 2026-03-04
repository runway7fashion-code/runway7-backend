<?php

namespace App\Enums;

enum ActivityAction: string
{
    case Registered = 'registered';
    case Login = 'login';
    case StatusChanged = 'status_changed';
    case EventAssigned = 'event_assigned';
    case EventRemoved = 'event_removed';
    case EmailSent = 'email_sent';
    case PhotoUploaded = 'photo_uploaded';
    case PhotoDeleted = 'photo_deleted';
    case ProfileUpdated = 'profile_updated';
    case DesignerShowAssigned = 'designer_show_assigned';

    public function label(): string
    {
        return match ($this) {
            self::Registered => 'Registro',
            self::Login => 'Login',
            self::StatusChanged => 'Cambio de estado',
            self::EventAssigned => 'Asignado a evento',
            self::EventRemoved => 'Removido de evento',
            self::EmailSent => 'Email enviado',
            self::PhotoUploaded => 'Foto subida',
            self::PhotoDeleted => 'Foto eliminada',
            self::ProfileUpdated => 'Perfil actualizado',
            self::DesignerShowAssigned => 'Diseñador asignado a show',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Registered => 'green',
            self::Login => 'blue',
            self::StatusChanged => 'yellow',
            self::EventAssigned => 'purple',
            self::EventRemoved => 'red',
            self::EmailSent => 'indigo',
            self::PhotoUploaded => 'teal',
            self::PhotoDeleted => 'orange',
            self::ProfileUpdated => 'gray',
            self::DesignerShowAssigned => 'pink',
        };
    }
}
