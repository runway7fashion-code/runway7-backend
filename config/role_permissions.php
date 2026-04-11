<?php

return [
    'admin' => [
        'sections' => ['dashboard', 'events', 'models', 'designers', 'volunteers', 'media', 'attendance', 'chats', 'banners', 'users', 'settings', 'accounting', 'accounting_dashboard', 'accounting_payments', 'tickets_dashboard', 'tickets_management', 'activity_logs', 'sales_dashboard', 'sales_designers', 'sales_leads', 'sales_calendar', 'designer_categories', 'designer_packages', 'countries', 'incoming_leads', 'communications'],
        'label' => 'Administrador',
    ],
    'accounting' => [
        'sections' => ['accounting_dashboard', 'accounting_payments'],
        'label' => 'Contabilidad',
    ],
    'operation' => [
        'sections' => ['dashboard', 'events', 'models', 'designers', 'volunteers', 'media', 'attendance', 'chats', 'designer_categories', 'countries', 'incoming_leads', 'communications'],
        'label' => 'Operaciones',
    ],
    'tickets_manager' => [
        'sections' => ['tickets_dashboard', 'tickets_management', 'communications'],
        'label' => 'Tickets',
    ],
    'marketing' => [
        'sections' => ['banners', 'marketing_dashboard', 'communications'],
        'label' => 'Marketing',
    ],
    'public_relations' => [
        'sections' => ['pr_dashboard', 'communications'],
        'label' => 'Relaciones Públicas',
    ],
    'sales' => [
        'sections' => ['sales_dashboard', 'sales_designers', 'sales_leads', 'sales_calendar', 'designer_packages', 'communications'],
        'label' => 'Ventas',
    ],
    'assistant' => [
        'sections' => [],
        'label' => 'Asistente',
    ],
];
