<?php

return [
    'admin' => [
        'sections' => ['dashboard', 'events', 'models', 'designers', 'volunteers', 'media', 'attendance', 'chats', 'banners', 'users', 'settings', 'accounting', 'accounting_dashboard', 'accounting_payments', 'tickets_dashboard', 'tickets_management', 'activity_logs', 'sales_dashboard', 'sales_designers', 'designer_categories', 'designer_packages'],
        'label' => 'Administrador',
    ],
    'accounting' => [
        'sections' => ['accounting_dashboard', 'accounting_payments'],
        'label' => 'Contabilidad',
    ],
    'operation' => [
        'sections' => ['dashboard', 'events', 'models', 'designers', 'volunteers', 'media', 'attendance', 'chats', 'designer_categories'],
        'label' => 'Operaciones',
    ],
    'tickets_manager' => [
        'sections' => ['tickets_dashboard', 'tickets_management'],
        'label' => 'Tickets',
    ],
    'marketing' => [
        'sections' => ['banners', 'marketing_dashboard'],
        'label' => 'Marketing',
    ],
    'public_relations' => [
        'sections' => ['pr_dashboard'],
        'label' => 'Relaciones Públicas',
    ],
    'sales' => [
        'sections' => ['sales_dashboard', 'sales_designers', 'designer_packages'],
        'label' => 'Ventas',
    ],
    'assistant' => [
        'sections' => [],
        'label' => 'Asistente',
    ],
];
