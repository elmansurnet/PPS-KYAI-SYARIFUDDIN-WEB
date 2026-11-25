<?php

return [
    /*
     * Kunci HMAC untuk nik_hash. WAJIB diisi lewat env NIK_HMAC_KEY; sengaja tanpa nilai bawaan
     * dan tidak pernah jatuh ke APP_KEY.
     * Minimal 32 byte acak (64 karakter hex): php -r "echo bin2hex(random_bytes(32));". Kunci lebih pendek
     * dari 32 karakter ditolak NikHasher. Lihat .env.example.
     */
    'nik_hmac_key' => env('NIK_HMAC_KEY'),

    /*
     * Rentang usia calon santri yang dianggap wajar (tahun). Tanggal lahir harus jatuh di antara
     * hari ini dikurangi usia_maks dan hari ini dikurangi usia_min.
     */
    'usia_min' => 5,
    'usia_maks' => 30,

    /*
     * Ukuran maksimum file kartu keluarga (kilobyte). 5 MB: foto KK dari kamera ponsel umumnya 2-5 MB.
     * File dienkripsi utuh di memori (sekitar 3-4x ukuran file selama proses, kurang dari 25 MB untuk 5 MB),
     * jadi jangan dinaikkan jauh di atas ini. upload_max_filesize dan post_max_size PHP harus di atasnya
     * (8M, lihat docs/DEPLOY-aapanel.md).
     */
    'kk_max_kb' => 5120,

    /* Masa berlaku draf formulir di sesi (menit), diperpanjang setiap langkah disimpan. */
    'draft_ttl_minutes' => 120,

    /*
     * Batas permintaan POST formulir. "sesi" = per IP + sesi; "ip" = per IP saja. Batas per IP sengaja
     * longgar karena banyak operator seluler Indonesia memakai CGNAT (banyak keluarga berbagi satu IP);
     * perlindungan utama tetap Turnstile.
     */
    'rate_limits' => [
        'langkah_per_menit_sesi' => 30,
        'langkah_per_menit_ip' => 120,
        'kirim_per_menit_sesi' => 3,
        'kirim_per_menit_ip' => 10,
        'kirim_per_jam_ip' => 30,
    ],

    /*
     * Batas baris ekspor PDF (dompdf menyusun seluruh tabel di memori). Bila hasil saringan melebihi
     * batas, ekspor PDF ditolak dengan pesan agar saringan dipersempit atau memakai Ekspor Excel
     * (tanpa batas, dibaca bertahap per potongan).
     */
    'pdf_max_rows' => (int) env('PENDAFTARAN_PDF_MAX_ROWS', 500),
];
