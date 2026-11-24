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
