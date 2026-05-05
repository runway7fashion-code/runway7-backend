<?php

return [
    'admin' => [
        'sections' => ['dashboard', 'events', 'models', 'designers', 'volunteers', 'media', 'attendance', 'chats', 'banners', 'users', 'settings', 'accounting', 'accounting_dashboard', 'accounting_payments', 'accounting_subscriptions', 'tickets_dashboard', 'tickets_management', 'activity_logs', 'sales_dashboard', 'sales_designers', 'sales_leads', 'sales_calendar', 'designer_categories', 'designer_packages', 'countries', 'incoming_leads', 'communications', 'sponsorship_companies', 'sponsorship_categories', 'sponsorship_packages', 'sponsorship_benefits', 'sponsorship_tags', 'sponsorship_leads', 'sponsorship_calendar', 'sponsorship_sponsors', 'sponsorship_dashboard'],
        'label' => 'Administrador',
    ],
    'accounting' => [
        'sections' => ['accounting_dashboard', 'accounting_payments', 'accounting_subscriptions'],
        'label' => 'Contabilidad',
    ],
    'operation' => [
        'sections' => ['dashboard', 'events', 'models', 'designers', 'volunteers', 'media', 'attendance', 'chats', 'designer_categories', 'countries', 'incoming_leads', 'communications'],
        'label' => 'Operaciones',
    ],
    'tickets_manager' => [
        'sections' => ['tickets_dashboard', 'tickets_management', 'communications', 'chats'],
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
    'creative' => [
        'sections' => ['designers', 'chats', 'communications'],
        'label' => 'Creative',
    ],
    'sponsorship' => [
        'sections' => ['communications', 'sponsorship_companies', 'sponsorship_categories', 'sponsorship_packages', 'sponsorship_benefits', 'sponsorship_tags', 'sponsorship_leads', 'sponsorship_calendar', 'sponsorship_sponsors', 'sponsorship_dashboard'],
        'label' => 'Sponsorship',
    ],
    'assistant' => [
        'sections' => [],
        'label' => 'Asistente',
    ],
];
