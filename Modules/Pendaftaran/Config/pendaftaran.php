<?php

return [
    /*
     * Kunci HMAC untuk nik_hash. WAJIB diisi lewat env NIK_HMAC_KEY; sengaja tanpa nilai bawaan
     * dan tidak pernah jatuh ke APP_KEY.
     * Minimal 32 byte acak (64 karakter hex): php -r "echo bin2hex(random_bytes(32));". Kunci lebih pendek
     * dari 32 karakter ditolak NikHasher. Lihat .env.example.
     */
    'nik_hmac_key' => env('NIK_HMAC_KEY'),
];
