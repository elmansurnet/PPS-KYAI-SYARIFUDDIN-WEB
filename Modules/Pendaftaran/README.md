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
| `GET /daftar/terima-kasih` | `daftar.selesai` | butuh data pendaftaran baru di sesi (nama + 4 digit akhir NIK, terenkripsi) yang berlaku `DONE_TTL_MINUTES` (10) menit, jadi halaman bisa dimuat ulang tanpa kehilangan tombol WhatsApp; kedaluwarsa atau membuka `/daftar` lagi menghapusnya; tanpa data diarahkan ke `/daftar` |

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

- Disk `kk` (config/filesystems.php, driver `kk_private` = `Support/PrivateDiskAdapter`): `storage/app/kk` (di luar root disk `local` = `storage/app/private`),
  `url()`/`temporaryUrl()` melempar pengecualian, tidak ada route storage.
- Nama `YYYY/MM/<ulid>.kk`; nama dan ekstensi asli tidak dipakai.
- Isi: `Crypt::encryptString(json_encode({"v":1,"mime":"...","data":"<base64>"}))`. `contents()` mengembalikan
  byte asli, `mime()` MIME yang terdeteksi saat disimpan (untuk unduhan Task 12), `delete()`.
- Hanya JPEG/PNG/PDF, maks `pendaftaran.kk_max_kb` (5120 KB = 5 MB; foto KK dari kamera ponsel umumnya 2-5 MB).
  File dienkripsi utuh di memori (puncak sekitar 3-4x ukuran file, kurang dari 25 MB untuk 5 MB). Isi diperiksa `KkFileInspector`: finfo,
  tanda tangan biner + `getimagesize` untuk gambar, `%PDF-` dan `%%EOF` untuk PDF, tolak penanda `<?php`,
  `<script`, `<html`; PDF berkonten aktif (`/JavaScript`, `/OpenAction`, `/Launch`, dan sejenisnya) ditolak.
  Pastikan `upload_max_filesize`/`post_max_size` PHP di server di atas batas ini (8M). Unggahan yang ditolak PHP
  (melebihi `upload_max_filesize`) memunculkan pesan `kk.uploaded` berbahasa Indonesia.
- `RegisterApplicant`: cek duplikat -> simpan file -> transaksi (pendaftar + audit `pendaftaran.baru` berisi
  id saja). Gagal di transaksi: file dihapus. Duplikat (termasuk balapan, via `isDuplicateNikViolation`) ->
  pesan ramah tanpa info pendaftar lama. Log hanya pesan tetap + nama kelas pengecualian.

## Sisi admin dan panitia (`/admin/pendaftar`)

`ApplicantResource` (grup navigasi "Penerimaan Santri Baru"): daftar + detail saja. Tidak ada halaman
buat (pendaftar hanya dari formulir publik) dan tidak ada form edit data pendaftar; panitia hanya mengubah
tiga bendera status (toggle di tabel atau aksi "Ubah status") dan `catatan_panitia` (teks polos, maks 2000).
Pencarian global Filament dimatikan untuk resource ini. Kata kunci pencarian tabel TIDAK diikat ke URL
(`ListApplicants::$tableSearch` dideklarasikan ulang tanpa `#[Url]`) dan tidak disimpan di sesi, supaya NIK yang
diketik tidak masuk riwayat peramban, log akses, atau header Referer. Saringan/urutan tetap di URL (tanpa data pribadi).

### Izin (gaya Shield, `ApplicantPolicy` ditulis tangan)

| Izin | admin | panitia_pendaftaran | penulis_berita |
|---|---|---|---|
| `ViewAny:Applicant`, `View:Applicant` | ya | ya | - |
| `Update:Applicant` (status + catatan) | ya | ya | - |
| `Export:Applicant` (Excel/PDF) | ya | ya | - |
| `RevealNik:Applicant` (NIK lengkap) | ya | ya | - |
| `DownloadKk:Applicant` | ya | ya | - |
| `Delete:Applicant`, `DeleteAny:Applicant` | ya | - | - |

superadmin lewat `Gate::before`. Izin khusus `Export`, `RevealNik`, dan `DownloadKk` didaftarkan ke Shield di
`config/filament-shield.php` (`resources.manage` untuk `ApplicantResource`), sehingga tampil di editor role dan
tidak hilang saat superadmin menyimpan role.

`PendaftaranSeeder` memberi izin bawaan lewat `DefaultPermissions::grantOnce` (Core): setiap pasangan (role, izin)
diberikan SEKALI dan dicatat di tabel `seeded_role_permissions`. Menjalankan seeder lagi tidak memberi ulang izin
yang sudah dicabut superadmin dari panel dan tidak pernah mencabut izin; hanya izin baru yang belum pernah
diberikan yang ditambahkan. Cache izin dibersihkan sebelum dan sesudah. `MasterDataSeeder` hanya mengisi tabel
jenjang/kelas dan pendidikan terakhir yang masih KOSONG (instalasi baru); setelah itu data master dikelola admin,
dan baris yang dihapus atau diganti namanya tidak muncul lagi. Hapus massal memakai
`authorizeIndividualRecords('delete')`. Menghapus pendaftar ikut menghapus file KK-nya.

### Pencarian dan saringan

- `Support/ApplicantSearch`: kata kunci yang (setelah spasi/titik/tanda hubung dibuang) tepat 16 digit dicocokkan
  ke `nik_hash = NikHasher::hash(...)`; NIK sebagian tidak bisa dicari. Kolom `nik` tidak pernah dipakai di LIKE.
  Angka 4-15 digit dicari di HP ayah/ibu/wali. Selain itu tiap kata dicari di nama santri/ayah/ibu/wali, HP,
  desa/kecamatan/kabupaten. Wildcard `%`/`_` di-escape (`ESCAPE '!'`, portabel SQLite/MySQL).
- Saringan (bisa digabung dengan pencarian): tiga status (Semua/Ya/Belum), jenjang, kelas, pernah mondok,
  rentang tanggal daftar. Urutan bawaan terbaru dulu, 25 per halaman.

### Audit (`Modules\Core\Support\AuditLog`, tanpa data pribadi)

| Event | Kapan | Properti |
|---|---|---|
| `pendaftaran.dilihat` | halaman detail dibuka (sekali per mount) | - (subjek saja) |
| `pendaftaran.nik_dilihat` | modal "Tampilkan NIK" dibuka | - |
| `pendaftaran.kk_diunduh` | KK berhasil diunduh | - |
| `pendaftaran.status` | bendera status berubah | `flag`, `value`, `applicant_id` |
| `pendaftaran.catatan` | catatan panitia berubah | `applicant_id` (isi tidak dicatat) |
| `pendaftaran.ekspor` | ekspor Excel/PDF | `format`, `count`, `nik_lengkap` (bool: NIK lengkap ikut diekspor) |
| `pendaftaran.dihapus` | pendaftar dihapus | `applicant_id` |

### Unduh KK (`GET /admin/pendaftar/{id}/kk`, `pendaftar.kk`)

Middleware `panel:admin` + `Filament\Http\Middleware\AuthenticateSession` (sesi berakhir bila kata sandi diganti,
sama seperti panel) + `Filament\Http\Middleware\Authenticate` (tamu ke login admin, akun tanpa role 403) +
`can:downloadKk,applicant`; id hanya angka. Respons: byte asli dari `KkStorage`, `Content-Type` dari amplop,
`Content-Disposition: attachment; filename="kk-<id>.<ext>"`, `nosniff`, `Content-Security-Policy: sandbox;
default-src 'none'` (controller memberi atribut request `SecurityHeaders::KEEP_SANDBOX_CSP`; Core hanya
mempertahankan CSP yang diawali `sandbox` pada request bertanda itu, selain itu selalu menimpa), `Cache-Control:
private, no-store`. File tidak ada/rusak: 404 teks polos, log berisi id + nama kelas pengecualian saja.

### Ekspor (`Exports/`)

- Header "Ekspor Excel"/"Ekspor PDF" memakai `getFilteredSortedTableQuery()` (saringan + pencarian + urutan yang
  sedang aktif, semua halaman); aksi massal mengekspor rekaman terpilih. Butuh `Export:Applicant`.
- Excel (`ApplicantsExport::fromQuery`, maatwebsite/excel 4): dibaca per 200 baris, tanpa batas. Kolom: No, Nama,
  NIK, TTL, Alamat lengkap, Pernah mondok, Pondok sebelumnya, Pendidikan terakhir, Jenjang, Kelas, Nama/HP
  ayah, Nama/HP ibu, Wali, tiga status (Ya/Belum), Tanggal daftar. Semua sel bertipe string (nomor urut
  angka), kolom NIK/HP berformat teks. Teks berawalan `=`, `+`, `-`, `@`, tab, CR, LF, atau pemicu formula setelah spasi/tab/CR/LF di depan, diberi
  apostrof (injeksi formula). Audit ekspor dicatat SEBELUM file dibuat: bila audit gagal, ekspor batal (gagal
  tertutup) dan tidak ada file sementara berisi data pribadi yang tertinggal.
- PDF (`ApplicantsPdf::render`, dompdf, A4 lanskap): judul nama situs, tanggal ekspor, ringkasan saringan (kata
  kunci NIK tidak ditulis ulang), jumlah. Maks `pendaftaran.pdf_max_rows` (500, env `PENDAFTARAN_PDF_MAX_ROWS`);
  lebih dari itu aksi ditolak dengan pesan. Remote/PHP/JS dompdf mati, semua nilai di-escape.
- NIK lengkap hanya bila pengekspor punya `RevealNik:Applicant`; selain itu tersamar. NIK yang tidak bisa
  didekripsi tampil "(tidak terbaca)" dan ekspor tetap jalan (di tabel/detail: "— (tidak terbaca)").
- Unduhan lewat respons Livewire (isi file dikirim base64 dalam respons JSON): cocok untuk ratusan sampai
  beberapa ribu pendaftar.

### Pembatasan dan Turnstile

- RateLimiter `daftar-langkah`: 30/menit per IP+sesi, 120/menit per IP. `daftar-kirim`: 3/menit per IP+sesi,
  10/menit per IP, 30/jam per IP (config `pendaftaran.rate_limits`; batas per IP longgar karena CGNAT seluler).
  Kunci "sesi" memakai penanda acak di sesi, bukan ID sesi. Respons 429 berupa halaman berbahasa Indonesia.
- Turnstile wajib di kiriman akhir (`TurnstileRule`). Tanpa `TURNSTILE_SITE_KEY`: pengguna melihat
  "Verifikasi keamanan tidak tersedia, coba lagi nanti", kiriman diblokir, detail dicatat di log (gagal tertutup).
- `SESSION_ENCRYPT=true` di produksi (bawaan `.env.example`) sebagai lapisan tambahan (data terima kasih dan draf sudah terenkripsi).
