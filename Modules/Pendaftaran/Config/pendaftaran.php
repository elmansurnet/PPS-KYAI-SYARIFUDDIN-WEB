<?php

return [
    /*
     * Kunci HMAC untuk nik_hash. WAJIB diisi lewat env NIK_HMAC_KEY; sengaja tanpa nilai bawaan
     * dan tidak pernah jatuh ke APP_KEY. Lihat .env.example.
     */
    'nik_hmac_key' => env('NIK_HMAC_KEY'),
];
