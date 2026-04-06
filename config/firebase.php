<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Firebase Service Account Key
    |--------------------------------------------------------------------------
    |
    | Path to the Firebase service account JSON key file.
    | Download from: Firebase Console > Project Settings > Service accounts
    | > "Generate new private key"
    |
    */
    'credentials' => env('FIREBASE_CREDENTIALS', storage_path('app/firebase/service-account.json')),
];
