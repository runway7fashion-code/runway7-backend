<?php

return [
    'drive' => [
        'credentials' => env('GOOGLE_DRIVE_CREDENTIALS', storage_path('app/google/drive-service-account.json')),
        'parent_folder_id' => env('GOOGLE_DRIVE_PARENT_FOLDER_ID', '1JBQ2f5Ck5D4iRqQPbyb8Bi2KSN1c2Rtu'),
    ],
];
