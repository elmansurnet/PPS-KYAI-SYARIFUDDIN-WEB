# CLAUDE.md

Panduan kerja untuk developer dan agen di proyek ini. Spec: `docs/superpowers/specs/2026-11-25-website-pps-design.md`. Kebutuhan produk: `docs/PRD.md`. Rencana: `docs/superpowers/plans/2026-11-25-website-pps.md`.

## Ringkasan

Website PPs Kyai Syarifuddin (pesantren): landing page, berita, dan pendaftaran santri baru online. Bahasa antarmuka dan dokumen: Indonesia. Mobile first. Semua konten dan pengaturan diubah dari admin panel.

## Tumpukan teknologi

Keputusan pemilik: Laravel, admin panel untuk semua pengaturan, arsitektur modular, mobile first, modul buatan sendiri (bukan paket modul), hosting aaPanel + Cloudflare Tunnel (bisa pindah ke VPS).

Keputusan saya (usulan, dapat diubah, bukan kesepakatan): Filament untuk admin; Spatie Permission + Filament Shield untuk role/izin; Blade + Tailwind CSS untuk tampilan publik; wizard pendaftaran server-rendered tanpa JavaScript/Livewire/Alpine (CSP publik ketat, HP Android lama); ekspor Excel/PDF langsung tanpa queue. Pengujian memakai Pest. Versi PHP/Laravel/Filament dipilih yang masih mendapat dukungan keamanan, dibuktikan dengan `composer audit`.

## Perintah

- `composer test` : jalankan SEMUA tes (Pest). Lambat di mesin berRAM kecil (bisa berjam-jam); saat bekerja jalankan tes terfokus: `php artisan test Modules/Core/Tests tests/Feature/ProductionSafetyTest.php --compact`. Tes memakai SQLite di memori (phpunit.xml). `composer.json` mengatur `process-timeout: 0`.
- `composer security` : `composer audit` + `npm audit --audit-level=high`. Harus bersih sebelum rilis.
- `npm run build` : build aset front-end. Jalankan SEBELUM tes pada checkout baru/CI (`PublicLayoutTest` membaca manifest Vite di `public/build`).
- `php artisan shield:generate --all --panel=admin --option=permissions` : buat izin Shield setelah menambah resource. SELALU dengan `--option=permissions`; opsi lain menimpa policy kustom.
- Deploy, cadangan, dan pemulihan: `docs/DEPLOY-aapanel.md`. CI: `.github/workflows/ci.yml`.

## Struktur modul

```
Modules/<Nama>/
  Providers/            service provider modul
  Database/Migrations/
  Routes/               web.php
  Resources/views/      namespace view = strtolower(Nama)
  Filament/             plugin, resource, page
  Models/
  Tests/
```

Modul: Core (inti, kontrak, pengaturan situs, role, keamanan), Landing, Berita, Pendaftaran. Modul aktif didaftarkan di `config/modules.php` (`enabled` = daftar kelas provider); modul yang tidak terdaftar tidak dimuat.

## Aturan modul

1. Modul tidak boleh memanggil kelas modul lain. Komunikasi hanya lewat kontrak (interface) atau event yang didaftarkan di Core (`Modules\Core\Contracts\...`).
2. Modul yang membutuhkan data modul lain memakai kontrak Core. Contoh: Landing membaca berita lewat `LatestNewsProvider` (elemen `NewsItem`); Core mengikat implementasi kosong sampai Berita menggantinya.
   Tautan publik antarmodul lewat registri `Modules\Core\Support\Navigation`: tiap modul mendaftarkan item navigasinya di `boot()` (Core hanya `beranda`); modul lain memakai `Navigation::path('kunci')`/`has()` dan menyembunyikan tautan bila modulnya nonaktif. Jangan menulis path modul lain (mis. `/daftar`) langsung.
3. Provider modul mewarisi `Modules\Core\Providers\ModuleServiceProvider` (memuat migrasi, route, view, dan plugin Filament otomatis).
4. Tes modul ada di `Modules/<Nama>/Tests`.

### Menambah modul baru

1. Buat folder `Modules/<Nama>/` dengan subfolder di atas.
2. Buat `Providers/<Nama>ServiceProvider.php` yang mewarisi `ModuleServiceProvider`; isi `moduleName()` dan, bila ada panel admin, `filamentPlugin()`.
3. Tambahkan migrasi di `Database/Migrations`, model di `Models`, route di `Routes/web.php`, view di `Resources/views`.
4. Bila perlu data dari modul lain, tambahkan kontrak di `Modules/Core/Contracts` dan ikat di Core; jangan impor kelas modul lain.
5. Daftarkan kelas provider di `config/modules.php`.
6. Izin: buat seeder izin modul (`Database/Seeders/<Nama>Seeder.php`) yang memberi izin bawaan lewat `Modules\Core\Support\DefaultPermissions::grantOnce()` (sekali per role+izin; jangan `syncPermissions`, jangan mencabut izin), lalu panggil dari `database/seeders/DatabaseSeeder.php` di dalam pemeriksaan modul aktif. Izin khusus di luar aksi bawaan Shield WAJIB didaftarkan di `config/filament-shield.php` (`resources.manage` atau `custom_permissions`) agar tampil di editor role; bila tidak, menyimpan role menghapusnya. Admin/superadmin mengatur sisanya dari panel.
7. Tulis tes di `Modules/<Nama>/Tests`, termasuk tes bahwa modul tetap jalan bila modul lain dinonaktifkan (boot aplikasi tes hanya dengan modul tertentu lewat `Tests\TestCase::$modulesOverride`; contoh `Modules/Landing/Tests/LandingWithoutOtherModulesTest.php`), lalu jalankan `composer test` dan `composer security`.

## Keamanan wajib (spec bagian 5)

- NIK santri: dienkripsi (enkripsi bawaan Laravel) + hash HMAC terpisah untuk cek duplikat dan pencarian. Jangan simpan NIK polos; jangan cukup dengan hash.
- File KK: disk privat `kk` di `storage/app/kk` (di luar root disk lain), dienkripsi, nama file diacak, tanpa URL publik; diunduh hanya lewat route yang mengecek login dan izin (attachment, nosniff, CSP sandbox). PDF dengan konten aktif ditolak.
- Data lain tidak dienkripsi tetapi hanya tampil untuk role yang berhak.
- `APP_KEY` wajib dicadangkan aman; bila hilang, NIK dan KK tidak bisa dibuka. Simpan `APP_KEY`, `NIK_HMAC_KEY`, dan `BACKUP_ARCHIVE_PASSWORD` terpisah dari cadangan.
- Jalankan `composer security` sebelum rilis; build gagal bila ada kerentanan. Pakai hanya versi yang masih didukung keamanannya.
- Validasi upload ketat: jenis, ukuran, dan isi file (bukan hanya ekstensi).
- Rate limiting pada formulir pendaftaran dan login admin, plus Cloudflare Turnstile.
- Header keamanan (CSP, HSTS, dan sejenisnya); `APP_DEBUG=false` di produksi.
- Log aktivitas admin, termasuk siapa melihat dan mengekspor data pendaftar.
- Cadangan harian database + `storage/app/kk` + `storage/app/public`, zip terenkripsi sandi (`spatie/laravel-backup`, `config/backup.php`, jadwal di `routes/console.php`).
- Jangan klaim "lolos semua"; laporkan hasil tes dan audit apa adanya.

## Pedoman UI (spec bagian 6)

Arah pemilik: Gaya B, hijau segar modern dengan aksen emas hemat; latar putih bersih, kartu bersudut bulat; mobile first; mode terang saja; bahasa Indonesia. Bagian di bawah adalah usulan yang dimatangkan builder:

- Font: judul serif hangat (usulan Fraunces), isi sans (usulan Figtree atau Public Sans), Arab Amiri atau Noto Naskh Arabic. Hosting sendiri, bukan CDN (kecepatan dan CSP). Hindari Inter + Poppins.
- Warna sebagai token (hijau utama, hijau lembut, emas, netral hangat); kontras teks WCAG AA.
- Satu set ikon konsisten (usulan Lucide). Tanpa emoji sebagai ikon, gradien ungu, blob, atau efek kaca berlebihan.
- Foto/video asli pondok; tanpa lorem ipsum atau stok foto generik di rilis.
- Tata letak tidak serba rata tengah, ritme antarbagian bervariasi, area sentuh minimal 44px.
- Formulir bertahap dengan indikator langkah, pesan galat jelas berbahasa Indonesia, data tidak hilang saat salah isi.

## Jangan commit

- `.env` (dan rahasia lain)
- `.superpowers/`
- `storage/`

## Di luar cakupan

Pembayaran online, tes/wawancara/pengumuman kelulusan, komentar berita, akun wali, portal santri, page builder, dan formulir pendaftaran yang strukturnya bisa diubah admin.
