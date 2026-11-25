# Modul Pendaftaran

## Aturan modul (keamanan NIK dan data pribadi)

- Kode pendaftaran WAJIB cek duplikat dengan `Applicant::findByNik()` lalu tetap menangkap pelanggaran unik
  database dengan `Applicant::isDuplicateNikViolation($e)` (balapan antar permintaan). Jangan pernah mencatat
  atau menampilkan `$e->getMessage()`: pesan `QueryException` memuat semua binding (nama, tanggal lahir, alamat,
  HP orang tua, NIK terenkripsi).
- `nik_hash` hanya terisi lewat instance model (`setAttribute`/`fill`/`update`). `insert`, `upsert`,
  `insertOrIgnore`, `DB::table()` dan `replicate()` melewati logika itu; jangan dipakai untuk pendaftar.
- NIK harus tepat 16 digit; model melempar `InvalidArgumentException` (tanpa menyebut nilainya) bila tidak.
- `NIK_HMAC_KEY`: minimal 32 byte acak (64 karakter hex). Bila diganti, jalankan `php artisan pendaftaran:rehash-nik`.
- `kk_path` dan bendera status tidak bisa diisi massal; gunakan `forceFill`.

## Wizard pendaftaran publik (`/daftar`)

Server-rendered, tanpa Livewire/Alpine/JavaScript buatan sendiri: CSP publik melarang skrip/style inline,
dan banyak pengguna memakai HP Android lama. Satu-satunya skrip adalah widget Turnstile dari
`challenges.cloudflare.com` (langkah 4).

### Alur dan route

| Route | Nama | Isi |
|---|---|---|
| `GET /daftar` | `daftar` | langkah 1 (calon santri) |
| `GET /daftar/langkah/{2-4}` | `daftar.langkah` | langkah 2 (akademik), 3 (orang tua/wali), 4 (KK + Turnstile + ringkasan) |
| `POST /daftar/langkah/{1-3}` | `daftar.simpan` | validasi satu langkah (FormRequest per langkah), simpan ke draf, 303 ke langkah berikut; `aksi=kembali` menyimpan isian mentah lalu ke langkah sebelumnya |
| `POST /daftar/kirim` | `daftar.kirim` | validasi ulang SELURUH draf + KK + Turnstile, lalu `RegisterApplicant::handle()` |
| `GET /daftar/terima-kasih` | `daftar.selesai` | butuh flash sekali pakai; tanpa flash diarahkan ke `/daftar` |

Langkah yang belum boleh dibuka (langkah sebelumnya belum sah) diarahkan ke langkah pertama yang belum lengkap.
Semua halaman `Cache-Control: no-store, private`.

### Draf (`Support/RegistrationDraft`)

- Disimpan di sesi saja (tidak pernah lewat URL), terenkripsi utuh: `Crypt::encryptString(json_encode(...))`.
- `valid[n]` = data langkah n yang lolos validasi; `old[n]` = isian mentah terakhir yang belum lolos (salah isi
  atau tombol Kembali). Menyimpan `old[n]` menghapus `valid[n]`, jadi yang tampil selalu sama dengan yang dikirim.
- Kedaluwarsa `pendaftaran.draft_ttl_minutes` (120) menit sejak terakhir disimpan; payload rusak/kedaluwarsa dibuang.
- Saat validasi gagal, isian TIDAK di-flash (`withInput()` bawaan Laravel menyimpan NIK polos di sesi);
  hanya pesan galat yang di-flash, dan pesan tidak pernah memuat nilai isian.
- Data baru masuk database di kiriman akhir. Draf dihapus setelah berhasil.

### Isian bersyarat tanpa JavaScript

Panel bersyarat selalu ada di DOM dan ditampilkan murni CSS (`resources/css/app.css`):
`.reveal-on:checked ~ .reveal-panel` (radio "Ya") dan `.reveal-off:not(:checked) ~ .reveal-panel` (centang
"alamat sama"). Radio/centang asli tetap fokus-able (panah/Space bekerja). Alamat orang tua disembunyikan
dengan `:has()` bila kedua orang tua meninggal (peningkatan progresif). Server tetap acuan: `exclude_unless`/
`exclude` membuang isian panel yang tidak berlaku, sehingga tidak tersimpan.

### Aturan isian (ringkas)

NIK: spasi/titik/tanda hubung dibuang, lalu tepat 16 digit. Nama: huruf, spasi, `' . , -`, maks 100. Tanggal lahir:
tanggal nyata (`Y-m-d`, atau `d-m-Y`/`d/m/Y` untuk peramban tanpa pemilih tanggal), usia `usia_min`-`usia_maks`
(5-30) tahun. RT/RW 1-3 digit (disimpan 3 digit). Teks lain: tanpa `<`, `>`, dan karakter kontrol; spasi dirapatkan.
Jenjang dan pendidikan terakhir harus aktif; kelas harus label KelasOption aktif milik jenjang terpilih. HP:
`^(\+?62|0)8[0-9]{8,12}$` setelah pemisah dibuang, disimpan `08...`. Orang tua masih ada: minimal satu HP ayah/ibu,
pekerjaan wajib. Keduanya meninggal: wali (nama, pekerjaan, HP, alamat) wajib, HP/pekerjaan orang tua boleh kosong.

### File KK (`Services/KkStorage`)

- Disk `kk` (config/filesystems.php, driver `kk_private` = `Support/PrivateDiskAdapter`): `storage/app/private/kk`,
  `url()`/`temporaryUrl()` melempar pengecualian, tidak ada route storage.
- Nama `YYYY/MM/<ulid>.kk`; nama dan ekstensi asli tidak dipakai.
- Isi: `Crypt::encryptString(json_encode({"v":1,"mime":"...","data":"<base64>"}))`. `contents()` mengembalikan
  byte asli, `mime()` MIME yang terdeteksi saat disimpan (untuk unduhan Task 12), `delete()`.
- Hanya JPEG/PNG/PDF, maks `pendaftaran.kk_max_kb` (2048 KB). Isi diperiksa `KkFileInspector`: finfo,
  tanda tangan biner + `getimagesize` untuk gambar, `%PDF-` dan `%%EOF` untuk PDF, tolak penanda `<?php`,
  `<script`, `<html`. Pastikan `upload_max_filesize`/`post_max_size` PHP di server di atas batas ini (mis. 8M).
- `RegisterApplicant`: cek duplikat -> simpan file -> transaksi (pendaftar + audit `pendaftaran.baru` berisi
  id saja). Gagal di transaksi: file dihapus. Duplikat (termasuk balapan, via `isDuplicateNikViolation`) ->
  pesan ramah tanpa info pendaftar lama. Log hanya pesan tetap + nama kelas pengecualian.

### Pembatasan dan Turnstile

- RateLimiter `daftar-langkah`: 30/menit per IP+sesi, 120/menit per IP. `daftar-kirim`: 3/menit per IP+sesi,
  10/menit per IP, 30/jam per IP (config `pendaftaran.rate_limits`; batas per IP longgar karena CGNAT seluler).
  Kunci "sesi" memakai penanda acak di sesi, bukan ID sesi. Respons 429 berupa halaman berbahasa Indonesia.
- Turnstile wajib di kiriman akhir (`TurnstileRule`). Tanpa `TURNSTILE_SITE_KEY`: pengguna melihat
  "Verifikasi keamanan tidak tersedia, coba lagi nanti", kiriman diblokir, detail dicatat di log (gagal tertutup).
- Disarankan `SESSION_ENCRYPT=true` di produksi sebagai lapisan tambahan (flash terima kasih dan draf sudah terenkripsi).
