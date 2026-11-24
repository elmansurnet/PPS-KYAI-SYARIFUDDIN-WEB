<?php

/*
 * Modul fitur yang aktif: daftar nama kelas provider modul.
 * Core selalu aktif (didaftarkan di bootstrap/providers.php), jangan dicantumkan di sini.
 * Modul yang tidak tercantum tidak dimuat.
 */
return [
    'enabled' => [
        //
    ],

    /*
     * Akun superadmin awal, dibuat oleh RoleSeeder dari .env.
     * Bila salah satu kosong, akun tidak dibuat (tidak ada kata sandi bawaan).
     */
    'superadmin' => [
        'email' => env('SUPERADMIN_EMAIL'),
        'password' => env('SUPERADMIN_PASSWORD'),
    ],
];
