# Website PPs Kyai Syarifuddin Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membangun website PPs Kyai Syarifuddin (landing, berita, penerimaan santri baru) di Laravel dengan admin panel penuh dan arsitektur modular.

**Architecture:** Satu aplikasi Laravel dengan inti (`Modules/Core`) dan modul fitur (`Modules/Landing`, `Modules/Berita`, `Modules/Pendaftaran`) di `Modules/<Nama>`. Tiap modul punya service provider, migrasi, route, tampilan, dan Filament plugin sendiri. Modul hanya bergantung pada Core, tidak pada modul lain.

**Tech Stack:** Laravel + Filament (admin), Spatie Permission + Filament Shield, Spatie Settings, Spatie Activitylog, Livewire (wizard), Blade + Tailwind CSS, Pest, MySQL, maatwebsite/excel, barryvdh/laravel-dompdf, Cloudflare Turnstile. Versi dipilih builder: harus masih didukung keamanannya dan lolos `composer audit`.

**Spec:** `docs/superpowers/specs/2026-11-25-website-pps-design.md`

## Global Constraints

- Bahasa antarmuka: Indonesia. Mobile first, area sentuh minimal 44px, kontras WCAG AA.
- Gaya: hijau segar modern + aksen emas hemat, mode terang saja. Font dihosting sendiri: judul Fraunces, isi Figtree, Arab Amiri. Ikon satu set (Lucide), tanpa emoji sebagai ikon, tanpa gradien ungu/blob/efek kaca, tanpa lorem ipsum di rilis.
- Semua konten dan pengaturan bisa diubah dari admin panel.
- Role: Superadmin (semua + atur izin), Admin (semua fitur, tanpa atur izin), Penulis berita (hanya Berita), Panitia pendaftaran (hanya Pendaftar). Izin tersimpan di database dan bisa diatur superadmin.
- NIK santri: dienkripsi (enkripsi bawaan Laravel) + `nik_hash` HMAC terpisah. File KK: disk privat, dienkripsi, nama acak, hanya lewat route ber-izin.
- Status pendaftar: tiga boolean bebas `sudah_dihubungi`, `sudah_bayar`, `sudah_masuk`.
- Tanpa pembayaran online, tanpa tes/pengumuman, tanpa komentar berita, tanpa akun wali.
- Ekspor Excel dan PDF berjalan langsung (tanpa queue) dan mengikuti penyaringan aktif.
- `composer audit` dan `npm audit` harus bersih. `APP_DEBUG=false` di produksi. Rate limiting dan Turnstile pada formulir pendaftaran dan login admin. Log aktivitas admin termasuk lihat/ekspor data pendaftar.
- Modul tidak boleh saling memanggil kelas modul lain. Komunikasi lewat kontrak/event di Core.
- Proyek belum repo git: Task 2 menjalankan `git init`. `.superpowers/` masuk `.gitignore`.

## Review Focus

1. NIK ganda dan kirim ganda (klik dua kali): satu NIK tidak boleh menghasilkan dua pendaftar, wali melihat pesan jelas. (Task 11)
2. NIK salah format (spasi, huruf, 15 atau 17 digit): ditolak dengan pesan Indonesia. (Task 11)
3. Upload KK menyamar (file PHP berekstensi .jpg, file terlalu besar): ditolak berdasarkan isi dan ukuran. (Task 11)
4. Berita draf dibuka langsung lewat URL oleh pengunjung: 404. Bagian landing yang disembunyikan tidak tampil. (Task 8, 9)
5. Nomor WhatsApp admin berformat `08xx`, `+62`, atau berspasi: tautan `wa.me` selalu berformat `62xxxxxxxx`. Ekspor dengan 0 baris tetap menghasilkan file valid. (Task 5, 12)

---

### Task 1: Dokumen proyek (CLAUDE.md dan PRD)

**Files:**
- Create: `CLAUDE.md`, `docs/PRD.md`

**Interfaces:**
- Produces: aturan kerja yang dibaca semua task berikutnya (struktur modul, aturan keamanan, gaya kode, perintah uji).

- [ ] **Step 1: Tulis `docs/PRD.md`** dari spec: latar, tujuan/bukan-tujuan, persona dan role, user story per modul (pengunjung, wali, panitia, penulis, admin, superadmin), alur pendaftaran, matriks role-izin, kebutuhan non-fungsional (keamanan, mobile first, performa), dan kriteria penerimaan per fitur. Jangan menyalin keputusan teknis yang belum ada di spec.
- [ ] **Step 2: Tulis `CLAUDE.md`** berisi: ringkasan proyek, tumpukan teknologi, struktur `Modules/<Nama>/{Providers,Database/Migrations,Routes,Resources/views,Filament,Models,Tests}`, aturan modul (tidak saling memanggil, kontrak di Core, cara menambah modul baru langkah demi langkah), aturan keamanan wajib dari spec bagian 5, pedoman UI bagian 6, perintah (`composer test`, `composer security`, `npm run build`), dan aturan "jangan commit `.env`, `.superpowers/`, dan `storage/`".
- [ ] **Step 3: Verifikasi** — setiap bagian spec (1-6) punya padanan di PRD, dan tiap perintah di CLAUDE.md nanti ada di Task 2/13. Perbaiki bila ada yang tak cocok.
- [ ] **Step 4: Commit** setelah Task 2 melakukan `git init` (simpan perubahan sampai saat itu).

### Task 2: Scaffolding Laravel, tooling uji, dan git

**Files:**
- Create: aplikasi Laravel di root proyek (`composer.json`, `app/`, `config/`, dst.), `.gitignore`, `phpunit/pest` config, `.github/workflows/ci.yml` (opsional bila repo di GitHub)
- Modify: `composer.json` (scripts), `.env.example`

**Interfaces:**
- Produces: `composer test` (Pest), `composer security` (menjalankan `composer audit` lalu `npm audit --audit-level=high`), autoload PSR-4 `Modules\\` => `Modules/`.

- [ ] **Step 1: Buat proyek Laravel di folder sementara**, lalu pindahkan isinya ke root tanpa menimpa `docs/`, `CLAUDE.md`, dan `.superpowers/`. Alasan: root tidak kosong.
- [ ] **Step 2: Konfigurasi `.env.example`** untuk MySQL, `APP_DEBUG=false`, `APP_LOCALE=id`, `APP_TIMEZONE=Asia/Jakarta`. Pasang Pest dan Tailwind; tambahkan `Modules\\` ke autoload PSR-4 `composer.json`.
- [ ] **Step 3: Tulis tes dasar** `tests/Feature/SmokeTest.php::test_homepage_returns_200` yang memanggil `/` dan mengharapkan status 200. Jalankan `composer test`, harapkan lulus (tes ini dipakai ulang di Task 8).
- [ ] **Step 4: Tambah script `composer security`**; jalankan, harapkan keluaran "No security vulnerability advisories found".
- [ ] **Step 5: `git init`, pastikan `.gitignore` memuat `.env`, `.superpowers/`, `storage/*.key`, `vendor`, `node_modules`.** Commit semua termasuk Task 1.

### Task 3: Sistem modul di Core

**Files:**
- Create: `Modules/Core/Providers/ModuleServiceProvider.php`, `Modules/Core/Providers/CoreServiceProvider.php`, `config/modules.php`
- Test: `Modules/Core/Tests/ModuleRegistrationTest.php`

**Interfaces:**
- Produces: `abstract class ModuleServiceProvider extends ServiceProvider` dengan `abstract protected function moduleName(): string` dan `protected function filamentPlugin(): ?\Filament\Contracts\Plugin`. Pada `boot()` otomatis memuat migrasi `Database/Migrations`, route `Routes/web.php`, dan view dengan namespace `strtolower(moduleName())`. `config('modules.enabled')` adalah array nama kelas provider. `CoreServiceProvider::registeredFilamentPlugins(): array`. Kontrak `Modules\Core\Contracts\LatestNewsProvider` dengan `latest(int $limit): Collection`, terikat di container ke implementasi kosong (mengembalikan koleksi kosong) sampai modul Berita menggantinya.

- [ ] **Step 1: Tulis tes** `test_enabled_module_loads_its_views_and_routes` (modul tiruan di `Modules/Core/Tests/Fixtures/Dummy`) dan `test_module_not_in_config_is_not_loaded`.
- [ ] **Step 2: Jalankan tes**, harapkan GAGAL.
- [ ] **Step 3: Implementasi** `ModuleServiceProvider` dan pendaftaran provider dari `config('modules.enabled')` di `CoreServiceProvider`.
- [ ] **Step 4: Jalankan tes**, harapkan LULUS. Tambah tes `test_disabling_a_module_does_not_break_others` (matikan Dummy, route lain tetap 200).
- [ ] **Step 5: Commit.**

### Task 4: Panel admin, role, dan izin

**Files:**
- Create: `app/Providers/Filament/AdminPanelProvider.php`, `Modules/Core/Database/Seeders/RoleSeeder.php`, `Modules/Core/Filament/Resources/UserResource.php`
- Modify: `app/Models/User.php`
- Test: `Modules/Core/Tests/RoleAccessTest.php`

**Interfaces:**
- Consumes: `CoreServiceProvider::registeredFilamentPlugins()` untuk mendaftarkan plugin modul ke panel.
- Produces: role `superadmin`, `admin`, `penulis_berita`, `panitia_pendaftaran`; helper `User::isSuperadmin(): bool`. Panel di path `/admin`. Halaman Shield untuk mengelola izin hanya bisa diakses superadmin.

- [ ] **Step 1: Tulis tes** `RoleAccessTest`: `test_superadmin_can_manage_role_permissions`, `test_admin_cannot_manage_role_permissions`, `test_admin_can_open_all_feature_resources`, `test_penulis_berita_cannot_open_pendaftar_resource` (sebelum resource ada, gunakan resource tiruan), `test_guest_is_redirected_to_admin_login`.
- [ ] **Step 2: Jalankan**, harapkan GAGAL.
- [ ] **Step 3: Implementasi** panel, seeder role (izin awal per role sesuai Global Constraints), `UserResource` (kelola akun admin, hanya superadmin dan admin), dan pembatasan halaman izin ke superadmin.
- [ ] **Step 4: Jalankan**, harapkan LULUS. Seeder juga membuat satu superadmin dari variabel `.env` (`SUPERADMIN_EMAIL`, `SUPERADMIN_PASSWORD`), tidak ada kredensial di kode.
- [ ] **Step 5: Commit.**

### Task 5: Pengaturan situs dan nomor WhatsApp

**Files:**
- Create: `Modules/Core/Settings/SiteSettings.php`, `Modules/Core/Filament/Pages/ManageSiteSettings.php`, `Modules/Core/Support/WhatsAppLink.php`
- Test: `Modules/Core/Tests/WhatsAppLinkTest.php`, `Modules/Core/Tests/SiteSettingsTest.php`

**Interfaces:**
- Produces: `SiteSettings` dengan properti `site_name: string`, `logo_path: ?string`, `address: ?string`, `contact_email: ?string`, `social_links: array`, `whatsapp_number: string`, `whatsapp_message_template: string`, `fee_pdf_path: ?string`. `WhatsAppLink::normalizeNumber(string $raw): string` (selalu `62...`, tanpa spasi/plus/nol awal) dan `WhatsAppLink::make(string $number, string $message): string` mengembalikan `https://wa.me/<nomor>?text=<urlencoded>`.

- [ ] **Step 1: Tulis tes** `WhatsAppLinkTest`: `normalizeNumber('0812 3456 789') === '628123456789'`, `'+62 812-3456-789' => '628123456789'`, `'628123456789'` tidak berubah, dan `make()` meng-encode pesan berisi spasi, baris baru, dan tanda `&`.
- [ ] **Step 2: Jalankan**, harapkan GAGAL.
- [ ] **Step 3: Implementasi** `WhatsAppLink` dan `SiteSettings` (Spatie Settings). Halaman `ManageSiteSettings` hanya untuk role dengan izin `manage_site_settings` (superadmin, admin). Unggah PDF biaya dan logo ke disk publik dengan validasi jenis file.
- [ ] **Step 4: Jalankan**, harapkan LULUS. Tes `SiteSettingsTest::test_penulis_berita_cannot_open_settings_page`.
- [ ] **Step 5: Commit.**

### Task 6: Keamanan lintas modul

**Files:**
- Create: `Modules/Core/Http/Middleware/SecurityHeaders.php`, `Modules/Core/Services/TurnstileVerifier.php`, `Modules/Core/Rules/TurnstileRule.php`
- Modify: `config/activitylog.php`, `bootstrap/app.php`
- Test: `Modules/Core/Tests/SecurityHeadersTest.php`, `Modules/Core/Tests/TurnstileTest.php`

**Interfaces:**
- Produces: `TurnstileVerifier::passes(string $token, ?string $ip = null): bool` (HTTP ke Cloudflare, dapat di-fake dengan `Http::fake`), `TurnstileRule` sebagai `ValidationRule`. Middleware menambah `Content-Security-Policy`, `Strict-Transport-Security`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy` pada respons web publik. Activitylog aktif untuk aksi admin.

- [ ] **Step 1: Tulis tes** `test_public_pages_send_security_headers`, `test_turnstile_rejects_token_when_cloudflare_says_fail`, `test_turnstile_accepts_valid_token` (dengan `Http::fake`), `test_app_debug_is_false_in_production_config`.
- [ ] **Step 2: Jalankan**, harapkan GAGAL.
- [ ] **Step 3: Implementasi.** CSP mengizinkan skrip Turnstile dan YouTube/Vimeo embed untuk video profil, tanpa CDN font/ikon luar.
- [ ] **Step 4: Jalankan**, harapkan LULUS.
- [ ] **Step 5: Commit.**

### Task 7: Sistem desain dan layout publik

**Files:**
- Create: `resources/css/app.css` (token), `tailwind.config.js`, `resources/views/components/layouts/public.blade.php`, `resources/views/components/{navbar,footer,button,section-heading}.blade.php`, `public/fonts/*` (Fraunces, Figtree, Amiri .woff2 dihosting sendiri)
- Test: `tests/Feature/PublicLayoutTest.php`

**Interfaces:**
- Produces: komponen `<x-layouts.public :title="...">` yang memuat nama situs dan logo dari `SiteSettings`, meta viewport, `lang="id"`, tautan skip-to-content; komponen `<x-button variant="primary|gold|ghost">`.
- Token warna di `:root`: hijau utama, hijau lembut, emas, netral hangat, dengan nilai yang lolos kontras AA untuk teks.

- [ ] **Step 1: Tulis tes** `test_layout_has_lang_id_and_viewport`, `test_layout_loads_no_external_font_or_icon_hosts` (tidak ada `fonts.googleapis.com`, `cdn.`), `test_navbar_links_to_home_news_and_registration`.
- [ ] **Step 2: Jalankan**, harapkan GAGAL.
- [ ] **Step 3: Implementasi** token, font lokal dengan `font-display: swap`, layout, navbar mobile (menu geser, target sentuh 44px), footer. Ikon Lucide sebagai SVG inline. Terapkan pedoman anti-generik dari spec bagian 6.
- [ ] **Step 4: Jalankan**, harapkan LULUS. Lihat hasil di lebar 360px dan 1280px lewat `npm run build` dan peramban, catat hasilnya.
- [ ] **Step 5: Commit.**

### Task 8: Modul Landing

**Files:**
- Create: `Modules/Landing/Providers/LandingServiceProvider.php`, `Modules/Landing/Models/LandingSection.php`, migrasi `create_landing_sections_table`, `Modules/Landing/Filament/LandingPlugin.php`, `Modules/Landing/Filament/Resources/LandingSectionResource.php`, `Modules/Landing/Http/Controllers/HomeController.php`, `Modules/Landing/Resources/views/home.blade.php`, seeder awal
- Test: `Modules/Landing/Tests/LandingPageTest.php`

**Interfaces:**
- Consumes: `ModuleServiceProvider`, `<x-layouts.public>`, `SiteSettings`.
- Produces: `LandingSection` (kolom `key` unik: `hero|video_profil|sejarah|kegiatan_unggulan|proses_belajar|ajakan_daftar`, `title`, `body`, `media_path`, `video_url`, `sort_order`, `is_visible`). Scope `LandingSection::visible()` terurut `sort_order`. Route `GET /` bernama `home`. Bagian berita terbaru memakai kontrak Core `LatestNewsProvider::latest(int $limit): Collection` (diimplementasi Task 9, default kosong bila modul Berita mati).

- [ ] **Step 1: Tulis tes** `test_home_renders_visible_sections_in_sort_order`, `test_hidden_section_is_not_rendered`, `test_home_works_when_news_module_disabled`, `test_video_url_only_accepts_youtube_or_vimeo`. Hapus `SmokeTest` Task 2 bila redundan.
- [ ] **Step 2: Jalankan**, harapkan GAGAL.
- [ ] **Step 3: Implementasi** modul, resource Filament dengan urut ulang (reorder), toggle tampil, unggah gambar. Seeder berisi struktur bagian dengan teks penanda yang jelas bertanda "isi dari admin" (bukan lorem ipsum).
- [ ] **Step 4: Jalankan**, harapkan LULUS. Tambah tes `test_penulis_berita_cannot_open_landing_resource`.
- [ ] **Step 5: Commit.**

### Task 9: Modul Berita

**Files:**
- Create: `Modules/Berita/Providers/BeritaServiceProvider.php`, `Modules/Berita/Models/{Post,Category}.php`, migrasi `create_categories_table`, `create_posts_table`, `Modules/Berita/Filament/BeritaPlugin.php`, `Modules/Berita/Filament/Resources/{PostResource,CategoryResource}.php`, `Modules/Berita/Http/Controllers/PostController.php`, view `index` dan `show`, `Modules/Berita/Support/LatestNews.php`
- Test: `Modules/Berita/Tests/PostPublicTest.php`, `PostAdminTest.php`

**Interfaces:**
- Consumes: kontrak `LatestNewsProvider` dari Core (Task 8).
- Produces: `Post` (`title`, `slug` unik, `cover_path`, `body`, `category_id`, `status: draft|published`, `published_at`, `author_id`) dengan scope `Post::published()` (status terbit dan `published_at <= now()`). Route `GET /berita` (`berita.index`) dan `GET /berita/{slug}` (`berita.show`).

- [ ] **Step 1: Tulis tes** `test_index_lists_only_published_posts`, `test_draft_post_url_returns_404`, `test_future_dated_post_is_not_public`, `test_slug_is_generated_and_unique`, `test_penulis_berita_can_create_post_but_not_open_settings`, `test_published_post_has_meta_title_and_cover`.
- [ ] **Step 2: Jalankan**, harapkan GAGAL.
- [ ] **Step 3: Implementasi.** Isi berita lewat editor rich text Filament; HTML disanitasi sebelum ditampilkan. Paginasi 9 per halaman, filter kategori.
- [ ] **Step 4: Jalankan**, harapkan LULUS.
- [ ] **Step 5: Commit.**

### Task 10: Pendaftaran, model data dan data master

**Files:**
- Create: `Modules/Pendaftaran/Providers/PendaftaranServiceProvider.php`, `Modules/Pendaftaran/Models/{Applicant,Jenjang,KelasOption,PendidikanTerakhir}.php`, migrasi masing-masing, `Modules/Pendaftaran/Support/NikHasher.php`, `Modules/Pendaftaran/Filament/PendaftaranPlugin.php`, resource master data
- Test: `Modules/Pendaftaran/Tests/ApplicantModelTest.php`

**Interfaces:**
- Produces: `NikHasher::hash(string $nik): string` (HMAC-SHA256 dengan kunci dari `config('pendaftaran.nik_hmac_key')`, bukan `APP_KEY`). `Applicant` dengan kolom: `nik` (cast `encrypted`), `nik_hash` (unik), `nama_lengkap`, `tempat_lahir`, `tanggal_lahir`, alamat santri (`jalan_dusun, rt, rw, desa, kecamatan, kabupaten, provinsi`), `pernah_mondok: bool`, `pondok_sebelumnya`, `pendidikan_pondok_sebelumnya`, `pendidikan_terakhir_id`, `jenjang_id`, `kelas`, `ayah_nama, ibu_nama, ayah_pekerjaan, ibu_pekerjaan, ayah_hp, ibu_hp`, alamat orang tua (kolom sama), `orang_tua_meninggal: bool`, `wali_nama, wali_pekerjaan, wali_hp, wali_alamat...`, `kk_path`, `sudah_dihubungi, sudah_bayar, sudah_masuk` (boolean, default false), timestamps. Setter `Applicant::setNikAttribute` otomatis mengisi `nik_hash`.

- [ ] **Step 1: Tulis tes** `test_nik_is_encrypted_at_rest` (baca nilai mentah via `DB::table`, tidak sama dengan NIK asli), `test_nik_hash_is_set_and_deterministic`, `test_duplicate_nik_hash_is_rejected_by_database`, `test_status_flags_default_to_false`.
- [ ] **Step 2: Jalankan**, harapkan GAGAL.
- [ ] **Step 3: Implementasi** model, migrasi (indeks unik di `nik_hash`), seeder data master awal yang bisa diubah admin, resource Filament master data.
- [ ] **Step 4: Jalankan**, harapkan LULUS.
- [ ] **Step 5: Commit.**

### Task 11: Pendaftaran, wizard publik dan simpan KK

**Files:**
- Create: `Modules/Pendaftaran/Livewire/RegistrationWizard.php`, `Modules/Pendaftaran/Http/Requests/` (aturan per langkah), `Modules/Pendaftaran/Services/RegisterApplicant.php`, `Modules/Pendaftaran/Services/KkStorage.php`, view wizard dan halaman terima kasih, route `GET /daftar` (`daftar`)
- Test: `Modules/Pendaftaran/Tests/RegistrationWizardTest.php`, `KkStorageTest.php`

**Interfaces:**
- Consumes: `NikHasher`, `TurnstileRule`, `WhatsAppLink`, `SiteSettings`.
- Produces: `RegisterApplicant::handle(array $data, UploadedFile $kk): Applicant` (transaksi: simpan pendaftar dan file; gagal salah satu, batal keduanya). `KkStorage::store(UploadedFile $file): string` menyimpan terenkripsi ke disk privat dengan nama acak dan mengembalikan path; `KkStorage::contents(string $path): string` mendekripsi. Wizard mengarahkan ke `WhatsAppLink::make(...)` dengan pesan dari template admin yang diisi nama dan NIK terakhir 4 digit saja.

- [ ] **Step 1: Tulis tes**: `test_valid_submission_saves_applicant_and_redirects_to_whatsapp`, `test_nik_must_be_exactly_16_digits` (cek 15, 17 digit, huruf, spasi), `test_duplicate_nik_shows_clear_message_and_creates_no_second_record`, `test_double_submit_creates_one_record`, `test_pernah_mondok_requires_previous_pondok_fields`, `test_parents_deceased_requires_wali_fields_and_makes_parent_phone_optional`, `test_kk_rejects_php_file_renamed_to_jpg`, `test_kk_rejects_file_over_limit` (batas dari config, usulan 2 MB), `test_kk_is_stored_encrypted_and_not_publicly_reachable`, `test_registration_requires_valid_turnstile`, `test_form_is_rate_limited`, `test_alamat_orang_tua_can_copy_from_santri`.
- [ ] **Step 2: Jalankan**, harapkan GAGAL.
- [ ] **Step 3: Implementasi** wizard 4 langkah dengan indikator, validasi per langkah, data tetap saat salah isi, dropdown jenjang/kelas dari data master. Validasi file memeriksa isi (MIME asli) dan hanya JPG, PNG, atau PDF. Pesan galat dalam bahasa Indonesia.
- [ ] **Step 4: Jalankan**, harapkan LULUS. Uji manual di lebar 360px, catat hasil.
- [ ] **Step 5: Commit.**

### Task 12: Pendaftaran, sisi admin dan panitia

**Files:**
- Create: `Modules/Pendaftaran/Filament/Resources/ApplicantResource.php` (dan halaman daftar/lihat), `Modules/Pendaftaran/Exports/ApplicantsExport.php`, `Modules/Pendaftaran/Exports/ApplicantsPdf.php`, view PDF, `Modules/Pendaftaran/Http/Controllers/KkDownloadController.php`
- Test: `Modules/Pendaftaran/Tests/ApplicantAdminTest.php`, `ApplicantExportTest.php`

**Interfaces:**
- Consumes: `KkStorage::contents`, role izin dari Task 4, activitylog dari Task 6.
- Produces: route `GET /admin/pendaftar/{applicant}/kk` (`pendaftar.kk`) yang memerlukan login dan izin `view_applicant`, mencatat aktivitas. `ApplicantsExport::fromQuery(Builder $query)` dan `ApplicantsPdf::render(Builder $query): string`.

- [ ] **Step 1: Tulis tes**: `test_panitia_can_list_search_by_name_and_by_nik`, `test_filter_by_each_status_flag`, `test_status_flags_can_be_toggled_independently`, `test_penulis_berita_cannot_open_applicants`, `test_guest_cannot_download_kk`, `test_kk_download_is_decrypted_and_logged`, `test_excel_export_respects_active_filters`, `test_pdf_export_respects_active_filters`, `test_export_with_zero_rows_returns_valid_file`, `test_viewing_and_exporting_applicants_creates_activity_log`.
- [ ] **Step 2: Jalankan**, harapkan GAGAL.
- [ ] **Step 3: Implementasi** resource dengan kolom, pencarian (nama, dan NIK lewat `NikHasher::hash`), filter tiga status, toggle di tabel, aksi ekspor Excel dan PDF yang memakai query terfilter, halaman detail dengan tautan unduh KK.
- [ ] **Step 4: Jalankan**, harapkan LULUS.
- [ ] **Step 5: Commit.**

### Task 13: Pengerasan, cadangan, dan panduan deploy

**Files:**
- Create: `docs/DEPLOY-aapanel.md`, `config/backup.php`, `tests/Feature/ProductionSafetyTest.php`
- Modify: `composer.json`, `.github/workflows/ci.yml`

**Interfaces:**
- Produces: perintah terjadwal cadangan (`backup:run`) terenkripsi; panduan deploy (aaPanel, Nginx, PHP-FPM, Cloudflare Tunnel, `APP_KEY` dan `NIK_HMAC_KEY` dicadangkan terpisah, direktori `storage/app/private` tidak di-expose, `php artisan optimize`); CI menjalankan `composer test` dan `composer security`.

- [ ] **Step 1: Tulis tes** `ProductionSafetyTest`: `test_private_disk_has_no_public_url`, `test_env_and_storage_not_reachable_via_http`, `test_login_is_rate_limited`, `test_sensitive_models_hidden_from_public_routes`.
- [ ] **Step 2: Jalankan**, harapkan GAGAL bila ada yang belum terpenuhi, lalu perbaiki.
- [ ] **Step 3: Tulis panduan deploy** dan jadwal cadangan, termasuk cara memulihkan dan peringatan kehilangan `APP_KEY`.
- [ ] **Step 4: Jalankan `composer test` dan `composer security`**, harapkan semuanya lulus dan bersih.
- [ ] **Step 5: Commit.**

### Task 14: Pemeriksaan akhir

**Files:**
- Modify: `docs/PRD.md`, `CLAUDE.md` bila ada yang berubah selama pembangunan

- [ ] **Step 1: Jalankan seluruh tes dan `composer security`**, tempel keluarannya di catatan serah terima.
- [ ] **Step 2: Telusuri tiap kriteria penerimaan di PRD** terhadap aplikasi yang berjalan (HP 360px dan desktop): landing, berita, pendaftaran sampai tautan WhatsApp, ekspor, tiga status, empat role.
- [ ] **Step 3: Periksa pedoman anti-generik** (font, ikon, warna emas hemat, tidak ada lorem ipsum, tidak ada tata letak serba tengah) dan catat hal yang perlu foto/video/teks asli dari pemilik.
- [ ] **Step 4: Perbarui dokumen** dan commit.
