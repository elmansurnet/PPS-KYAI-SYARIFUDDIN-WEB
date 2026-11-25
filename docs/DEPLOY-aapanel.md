# Panduan deploy: aaPanel + Cloudflare Tunnel

Panduan ini untuk server lokal/VPS dengan aaPanel (**Nginx atau Apache**, PHP-FPM, MySQL) yang dipublikasikan lewat Cloudflare Zero Trust Tunnel (`cloudflared`). Perintah dijalankan dari folder proyek (mis. `/www/wwwroot/pps`) sebagai user web server (biasanya `www`). Pilihan web server hanya memengaruhi bagian 2; bagian lainnya sama untuk keduanya. Untuk VPS dengan IP publik dan DNS di Cloudflare (tanpa tunnel) lihat juga [`DEPLOY-vps-cloudflare.md`](DEPLOY-vps-cloudflare.md).

> **Peringatan utama.** NIK dan file KK terenkripsi dengan `APP_KEY`; pencarian dan cek duplikat NIK memakai `NIK_HMAC_KEY`. **Bila `APP_KEY` hilang, NIK dan KK hilang selamanya**, cadangan secanggih apa pun tidak bisa menolong. Baca [Penyimpanan kunci](#10-penyimpanan-kunci-wajib-dibaca) sebelum go-live.

## 1. Prasyarat

- PHP 8.3 dengan ekstensi: `pdo_mysql`, `mbstring`, `intl`, `gd`, `zip`, `fileinfo`, `bcmath`, `openssl`, `curl`, `xml`, `dom`, `exif`, `ctype`, `tokenizer`. (`sqlite` tidak diperlukan di production; hanya untuk tes.) Fungsi `proc_open` jangan masuk `disable_functions` (dipakai Symfony Process untuk menjalankan `mysqldump` oleh cadangan; di aaPanel biasanya perlu dihapus dari daftar itu). Fungsi lain (mis. `exec`) tidak perlu dibuka.
- MySQL 8 (atau MariaDB setara) dan biner `mysqldump` (aaPanel: `/www/server/mysql/bin`).
- Composer 2.
- Node 22 hanya untuk build aset (boleh di mesin lain, lalu salin `public/build`).
- `cloudflared` terpasang di host yang sama.
- Pengaturan PHP (aaPanel: App Store > PHP 8.3 > Setting > Configuration):
  - `upload_max_filesize = 12M` dan `post_max_size = 12M` (file KK maks 5 MB, tetapi PDF rincian biaya di pengaturan situs boleh sampai 10 MB; nilai harus di atas keduanya).
  - `memory_limit = 256M` atau lebih (ekspor PDF; enkripsi file KK 5 MB memakai kurang dari 25 MB per permintaan).
  - `max_execution_time = 120` atau lebih (ekspor Excel/PDF).
  - `expose_php = Off`.
  - `request_terminate_timeout` PHP-FPM jangan lebih kecil dari `max_execution_time`.

## 2. Situs di aaPanel (Nginx atau Apache)

Pasang **satu** web server lewat App Store aaPanel (Nginx atau Apache; keduanya tidak bisa memakai port 80 bersamaan). Pilih salah satu subbagian di bawah; langkah umum berlaku untuk keduanya.

Langkah umum:

1. Website > Add site: domain = host publik tunnel (mis. `pendaftaran.contoh.sch.id`), PHP 8.3, database MySQL (catat nama/user/sandi).
2. Site directory = folder proyek; **Running directory = `/public`** (web root = `public/`). Bila open_basedir aaPanel aktif, pastikan mencakup folder proyek, bukan hanya `public/`.
3. Tentukan batas unggah di PHP (bagian 1) **dan** di web server (subbagian di bawah): keduanya harus ≥ 12 MB, bila tidak unggahan KK dan PDF biaya gagal dengan 413.

### 2.1 Bila web server Nginx

1. Rewrite Laravel (Pseudo-static):

   ```nginx
   location / {
       try_files $uri $uri/ /index.php?$query_string;
   }
   ```

   Pastikan `client_max_body_size` Nginx minimal `12m` (bawaan stok Nginx 1 MB akan menolak unggahan KK dan PDF biaya dengan 413; bawaan aaPanel 50m sudah cukup).

2. Tolak berkas tersembunyi dan direktori internal (di konfigurasi situs, sebelum blok `location ~ \.php$`):

   ```nginx
   location ~ /\.(?!well-known) { deny all; }
   location ~ ^/(vendor|bootstrap|config|database|Modules|tests|resources|routes|app)/ { deny all; }
   location ~ ^/storage/(app|framework|logs)/ { deny all; }
   location ~* ^/storage/.*\.php$ { deny all; }   # letakkan SEBELUM blok `location ~ \.php$` (fastcgi)
   location ~* ^/(composer\.(json|lock)|artisan|package(-lock)?\.json)$ { deny all; }
   ```

   Aturan `^/storage/.*\.php$` mencegah berkas `.php` yang entah bagaimana terunggah ke `public/storage` dieksekusi PHP-FPM (nginx memilih regex pertama yang cocok, jadi aturan ini harus berada sebelum handler fastcgi).

   **Jangan memblokir `/storage/` secara menyeluruh**: `storage:link` membuat `public/storage` (isi `storage/app/public`) yang melayani logo, gambar berita, galeri, dan PDF biaya; hanya `app`, `framework`, dan `logs` yang ditolak. Perlindungan utamanya adalah root Nginx = `public/`: folder di luar `public/` memang tidak terjangkau web; aturan di atas hanya lapis kedua (dotfile seperti `.env` sudah ditolak aturan pertama). Jangan pernah menaruh `.env`, cadangan, atau ekspor di dalam `public/`. Jangan memblokir ekstensi umum (`.json`, `.md`, `.zip`) secara global, karena bisa mematikan berkas unggahan yang sah.
3. Origin hanya untuk tunnel: lihat 2.3.

### 2.2 Bila web server Apache

Menu dan path di bawah umum untuk aaPanel; nama menu dapat berbeda antarversi, jadi cocokkan dengan panel Anda. Panel menaruh konfigurasi situs di `/www/server/panel/vhost/apache/<domain>.conf` (Website > situs > Settings > Config file); Apache di `/www/server/apache`.

1. **Modul.** Pastikan `rewrite`, `headers`, dan `proxy_fcgi` aktif: `/www/server/apache/bin/httpd -M | grep -E 'rewrite|headers|proxy_fcgi'` (ketiganya harus tampil; PHP 8.3 dihubungkan panel ke PHP-FPM lewat `proxy_fcgi`).
2. **Rewrite Laravel.** Dengan Running directory `/public`, Apache membaca `public/.htaccess` bawaan Laravel, jadi **kosongkan tab Pseudo-static** (aturan yang diisi di sana ditaruh panel di folder situs di atas web root dan tidak terbaca). Syaratnya hanya `AllowOverride` pada blok `<Directory>` situs mencakup `FileInfo` dan `Options` (bawaan panel biasanya `AllowOverride All`) dan `Options` memuat `FollowSymLinks` (perlu untuk symlink `public/storage`). Periksa di Config file.
3. **Tambahan konfigurasi.** Di Config file, di dalam `<VirtualHost>` situs (di bawah blok `<Directory>` bawaan panel; jangan menduplikasi `SetHandler` PHP yang sudah ada), tambahkan:

   ```apache
   # Berkas/folder tersembunyi (.env, .git, dst.), kecuali .well-known.
   <LocationMatch "/\.(?!well-known/)">
       Require all denied
   </LocationMatch>

   # Jalur internal storage tidak boleh dilayani.
   <LocationMatch "^/storage/(app|framework|logs)/">
       Require all denied
   </LocationMatch>

   # Berkas PHP yang entah bagaimana terunggah ke public/storage tidak boleh dieksekusi.
   <LocationMatch "(?i)^/storage/.*\.php">
       Require all denied
   </LocationMatch>

   # Batas unggahan 12 MB (sejalan dengan post_max_size PHP) dan waktu ekspor besar.
   LimitRequestBody 12582912
   Timeout 150
   ProxyTimeout 150

   # Cache aset statis (berkas /build bernama-hash).
   <LocationMatch "^/build/">
       Header set Cache-Control "public, max-age=31536000, immutable"
   </LocationMatch>
   ```

   Simpan salinan tambahan ini di luar panel: aaPanel dapat membuat ulang berkas vhost saat Anda mengubah domain, SSL, atau versi PHP dari panel; periksa Config file setelah perubahan semacam itu.
4. **Jangan** menambah header keamanan (CSP, HSTS, `X-Content-Type-Options`, dll.) di Apache: aplikasi sudah mengirimnya dan header ganda bertabrakan. **Jangan** mengaktifkan pengalihan HTTP ke HTTPS di panel/Apache: TLS berakhir di Cloudflare dan tunnel berbicara HTTP ke origin, jadi pengalihan membuat loop (pakai "Always Use HTTPS" di Cloudflare).
5. Terapkan: `/www/server/apache/bin/httpd -t` (harus `Syntax OK`), lalu muat ulang Apache dari panel (App Store > Apache > Service > Reload).
6. **Gejala umum:** semua halaman selain `/` 404 = `mod_rewrite` mati atau `AllowOverride` tidak memuat `FileInfo`; kode PHP tampil sebagai teks = handler PHP situs hilang; 403 di semua halaman = izin folder atau `Require all granted` hilang; gambar/PDF unggahan 404 = `storage:link` belum dijalankan atau `FollowSymLinks` mati; 413 = `LimitRequestBody` atau batas PHP terlalu kecil; 404 "host tak tepercaya" = domain situs tidak sama dengan host `APP_URL`.

### 2.3 Origin hanya untuk tunnel (bagian 7)

Web server **harus** hanya terjangkau oleh `cloudflared` lokal. Nginx: `listen 127.0.0.1:80;`. Apache: di `/www/server/apache/conf/httpd.conf` ganti `Listen 80` menjadi `Listen 127.0.0.1:80` (dan tanpa `Listen 443`); aaPanel dapat menimpa port saat situs/SSL diubah, jadi **selalu** tambahkan firewall (aaPanel Security / firewall penyedia) yang menutup 80/443/3306 dari luar. Verifikasi: `ss -ltnp | grep -E 'nginx|httpd'` harus hanya menampilkan `127.0.0.1:80`.

## 3. Checklist `.env` production

```
APP_NAME="PPs Kyai Syarifuddin"
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=Asia/Jakarta                   # jadwal cadangan 02:00 mengikuti zona ini
APP_LOCALE=id                               # antarmuka dan pesan validasi berbahasa Indonesia (lang/id)
APP_FALLBACK_LOCALE=en                      # kunci yang belum diterjemahkan tampil sebagai teks Inggris, bukan kunci mentah
APP_URL=https://pendaftaran.contoh.sch.id   # WAJIB: URL HTTPS publik, tanpa slash akhir
APP_KEY=                                    # php artisan key:generate (SEKALI, bagian 4)

LOG_CHANNEL=daily
LOG_LEVEL=warning
LOG_DAILY_DAYS=14                           # log tidak boleh memuat data pribadi

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=...
DB_USERNAME=...                             # user khusus, bukan root
DB_PASSWORD=...
DB_DUMP_BINARY_PATH=/www/server/mysql/bin   # folder mysqldump, untuk cadangan

SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true                  # wajib: tanpa ini cookie sesi tidak bertanda Secure
SESSION_HTTP_ONLY=true                      # bawaan true; jangan dimatikan
SESSION_SAME_SITE=lax                       # bawaan lax
SESSION_LIFETIME=120
CACHE_STORE=database                        # atau file
QUEUE_CONNECTION=database                   # antrean tidak dipakai (ekspor langsung); nilai ini aman
FILESYSTEM_DISK=local

TURNSTILE_SITE_KEY=...                      # kunci ASLI dari dasbor Cloudflare
TURNSTILE_SECRET_KEY=...                    # kunci uji hanya berlaku bila APP_ENV=testing
NIK_HMAC_KEY=...                            # php -r "echo bin2hex(random_bytes(32));"  (64 hex, bukan APP_KEY)

SUPERADMIN_EMAIL=admin@contoh.sch.id        # hanya untuk seed pertama; hapus setelahnya
SUPERADMIN_PASSWORD=...                     # sandi panjang acak; HAPUS dari .env setelah seed

BACKUP_ARCHIVE_PASSWORD=...                 # php -r "echo bin2hex(random_bytes(24));"
BACKUP_NOTIFICATION_EMAIL=...                # SANGAT disarankan (bagian 8); butuh MAIL_* yang benar
```

Catatan:

- `APP_URL` menentukan host tepercaya (`TrustHosts` hanya mengizinkan host ini, tanpa subdomain atau `www`) dan memaksa skema/URL akar. Salah isi = situs menolak permintaan atau tautan salah.
- `.env` berizin `640`, pemilik user web; tidak masuk git; tidak ada di `public/`.
- Jangan menaruh nilai nyata di `.env.example`. Nilai bawaannya sudah sama dengan blok di atas (produksi);
  untuk pengembangan lokal ubah ke `APP_ENV=local`, `APP_DEBUG=true`, `SESSION_SECURE_COOKIE=false`, `LOG_LEVEL=debug`.

## 4. Pemasangan pertama

**Jalankan semua perintah `composer`/`php artisan` sebagai user `www`, bukan root** (root membuat berkas milik root di `storage/` dan `bootstrap/cache/` yang tidak bisa ditulis PHP-FPM). Di aaPanel `www` tidak punya shell login, jadi pakai `sudo -u www -H <perintah>`; PHP aaPanel ada di `/www/server/php/83/bin/php` (pola `/www/server/php/<versi tanpa titik>/bin/php`). Di bawah, `php` berarti `sudo -u www -H /www/server/php/83/bin/php` dan `composer` berarti `sudo -u www -H /www/server/php/83/bin/php /usr/bin/composer`. Bila ada yang pernah dijalankan sebagai root: `chown -R www:www storage bootstrap/cache`.

Variabel lingkungan untuk satu perintah harus lewat `env` setelah `sudo` (`sudo -u www -H env VAR=nilai ...`), bukan sebagai awalan sebelum `sudo`. Hanya langkah 2 yang memerlukannya; langkah lain membaca `.env`. Bila `sudo` tidak terpasang, cadangan (fallback): `su -s /bin/bash www -c 'cd /www/wwwroot/pps && CACHE_STORE=array /www/server/php/83/bin/php /usr/bin/composer install --no-dev --optimize-autoloader'`.

Urutan (tiap langkah bergantung pada langkah sebelumnya):

```bash
cd /www/wwwroot/pps
cp .env.example .env               # 1. buat dan ISI .env dulu sesuai bagian 3 (APP_KEY dibiarkan kosong)
chown www:www .env && chmod 640 .env
sudo -u www -H env CACHE_STORE=array /www/server/php/83/bin/php /usr/bin/composer install --no-dev --optimize-autoloader
                                   # 2. hook post-autoload-dump menjalankan filament:upgrade yang memakai cache;
                                   #    CACHE_STORE=array menghindari butuh tabel cache sebelum migrasi.
                                   #    `env` WAJIB: awalan VAR=nilai sebelum sudo dibuang sudo (env_reset).
npm ci && npm run build            # 3. atau build di mesin lain dan salin public/build
php artisan key:generate           # 4. SEKALI SAJA. Menjalankannya lagi membuat data terenkripsi tak terbaca.
php artisan migrate --force        # 5. buat tabel (termasuk cache dan sesi)
php artisan shield:generate --all --panel=admin --option=permissions   # 6. izin Shield (SEBELUM seed, lihat catatan)
php artisan db:seed --force        # 7. RoleSeeder (role + superadmin + izin admin), lalu seeder modul (Landing, Berita, Pendaftaran + data master awal)
php artisan filament:upgrade       # 8. publikasi aset Filament
php artisan storage:link           # 9. hanya untuk storage/app/public (gambar situs/berita)
php artisan optimize               # 10. config, route, view, event cache
```

Catatan:

- `--optimize-autoloader` pernah menggantung di mesin pengembangan; di server produksi seharusnya normal. Bila menggantung, jalankan `composer dump-autoload -o` terpisah.
- **Jangan** menjalankan `shield:generate` tanpa `--option=permissions`: opsi lain menimpa policy yang sudah disesuaikan (mis. `UserPolicy`).
- `shield:generate` dijalankan sebelum seed supaya RoleSeeder memberi admin SEMUA izin fitur yang ada (termasuk izin hasil Shield). Bila urutannya terbalik, cukup jalankan `php artisan db:seed --class="Modules\Core\Database\Seeders\RoleSeeder" --force` sekali lagi.
- **Aturan seeder (penting untuk pembaruan):** izin bawaan diberikan ke role SEKALI per pasangan (role, izin) dan dicatat di tabel `seeded_role_permissions`. Menjalankan seeder lagi tidak pernah mencabut izin dan tidak memberi ulang izin yang sudah dicabut superadmin dari panel; hanya izin baru (fitur baru) yang ditambahkan sekali. Data master pendaftaran (jenjang, kelas, pendidikan terakhir) hanya diisi bila tabelnya masih kosong; setelah itu dikelola admin.
- Setelah seed pertama berhasil, **hapus `SUPERADMIN_EMAIL` dan `SUPERADMIN_PASSWORD` dari `.env`**, lalu `php artisan config:cache`.
- Jalankan `php artisan optimize:clear && php artisan optimize` setiap `.env` berubah.
- Izin berkas: `chown -R www:www storage bootstrap/cache` dan `chmod -R ug+rwX storage bootstrap/cache` (ulangi bila sesuatu pernah dijalankan sebagai root).
- Instalasi lama yang menyimpan KK di `storage/app/private/kk` (lokasi lama): pindahkan sekali dengan `[ -d storage/app/private/kk ] && mv storage/app/private/kk storage/app/kk`. Root disk `kk` sekarang `storage/app/kk`, di luar root disk `local` (`storage/app/private`).

## 5. Penjadwal (scheduler)

Cadangan harian berjalan lewat penjadwal Laravel. Tambahkan cron (aaPanel: Cron > Shell script, tiap menit). Tugas cron aaPanel berjalan sebagai **root**; jangan jalankan `artisan` sebagai root (berkas log, cadangan, dan `backup-temp` milik root tidak bisa ditulis PHP-FPM). Jalankan sebagai `www`:

```
cd /www/wwwroot/pps && sudo -u www /www/server/php/83/bin/php artisan schedule:run >> /dev/null 2>&1
```

Periksa dengan `php artisan schedule:list` (harus tampil `backup:run` 02:00, `backup:clean` 02:30, `backup:monitor` 03:00, `activitylog:clean` 03:30; yang terakhir menghapus log aktivitas admin yang lebih tua dari 365 hari, `config/activitylog.php`). Cadangan hanya berjalan bila `BACKUP_ARCHIVE_PASSWORD` terisi; tanpa sandi arsip tidak terenkripsi, jadi sengaja ditolak dan dicatat ke log.

## 6. Pembaruan dan rollback

Pembaruan:

(Semua sebagai `www`, lihat bagian 4.)

```bash
git describe --tags --always       # CATAT rilis yang sedang berjalan (atau beri tag: git tag rilis-YYYYMMDD)
php artisan backup:run             # cadangan dulu; pastikan berhasil
php artisan down --retry=60
git pull                           # atau unggah rilis baru
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan filament:upgrade
# Hanya bila rilis menambah resource/izin/modul (lihat catatan rilis):
php artisan shield:generate --all --panel=admin --option=permissions
php artisan db:seed --class="Modules\Core\Database\Seeders\RoleSeeder" --force   # izin baru untuk admin
php artisan db:seed --class="<seeder izin modul baru>" --force                     # mis. Modules\Berita\Database\Seeders\BeritaSeeder
php artisan optimize:clear && php artisan optimize
php artisan up
```

Setelah kode baru terpasang, muat ulang PHP-FPM dari panel (App Store > PHP 8.3 > Service > Reload) agar OPcache memakai kode baru; berlaku untuk Nginx maupun Apache.

Seeder aman dijalankan ulang dan tidak menimpa pengaturan role dari panel (izin yang dicabut superadmin tetap tercabut; lihat "Aturan seeder" di bagian 4), tetapi tidak perlu dijalankan pada pembaruan biasa. Jangan menjalankan `db:seed` tanpa `--class` hanya untuk "berjaga-jaga".

Rollback: `php artisan down`, kembalikan kode ke rilis yang dicatat sebelum pembaruan (`git checkout <tag>` lalu `composer install --no-dev`), bila migrasi baru merusak data pulihkan database dari cadangan (bagian 9), lalu `optimize` dan `up`. Jangan pernah mengubah `APP_KEY` saat rollback.

## 7. Cloudflare Tunnel

- `cloudflared` berjalan di host yang sama. Public hostname di Zero Trust (Networks > Tunnels) menunjuk ke `http://127.0.0.1:80`.
- **Host publik tunnel harus sama dengan host `APP_URL`.** `TrustHosts` hanya menerima host itu; tidak ada alias `www` atau apex kecuali ditambahkan ke kode. Header Host yang diteruskan ke origin harus host publik (jangan ditimpa ke `localhost`).
- **Origin hanya `127.0.0.1`.** Aplikasi mempercayai `X-Forwarded-For` dan `X-Forwarded-Proto` dari semua proxy (`*`) karena satu-satunya pihak yang berbicara ke origin adalah `cloudflared` lokal. Bila origin bisa dijangkau langsung dari LAN/internet, penyerang dapat memalsukan IP (melewati pembatas laju per IP dan mengotori log audit) dan skema. Karena itu: Nginx `listen 127.0.0.1:80` atau Apache `Listen 127.0.0.1:80` (bagian 2.3), firewall menutup 80/443/3306 dari luar, dan port panel aaPanel jangan dibuka ke publik tanpa pembatasan.
- Health check/uptime harus mengirim header `Host` publik, mis. `curl -H "Host: pendaftaran.contoh.sch.id" http://127.0.0.1/up`; tanpa itu Laravel menjawab 404 (host tak tepercaya) dan pemeriksaan tampak gagal.
- VPS dengan IP publik dan DNS ber-proxy Cloudflare (tanpa tunnel): ikuti [`DEPLOY-vps-cloudflare.md`](DEPLOY-vps-cloudflare.md). Syarat mutlaknya: 443 hanya dari IP Cloudflare **dan** Authenticated Origin Pulls, karena `trustProxies('*')` percaya header pihak yang terhubung ke origin. Origin yang terbuka ke internet tanpa pembatasan itu **tidak aman** untuk konfigurasi ini; bila terpaksa, sempitkan `trustProxies` di `bootstrap/app.php` ke IP proxy yang sah.

### Pengaturan Cloudflare yang disarankan

- SSL/TLS **Full (strict)**, **Always Use HTTPS** aktif, Automatic HTTPS Rewrites aktif, TLS minimum 1.2.
- **HSTS**: aplikasi mengirim `Strict-Transport-Security` dengan `includeSubDomains`. Situs harus hidup di subdomainnya sendiri, atau seluruh domain apex dan subdomain lain sudah penuh HTTPS; jika tidak, subdomain HTTP lain tak terjangkau di browser yang pernah membuka situs ini.
- **WAF/Rate limiting**: batasi `POST /daftar/*`, seluruh `/admin*`, dan `POST /livewire*` (mis. 20 permintaan per menit per IP lalu challenge/block). Catatan: form login Filament tidak mengirim POST ke `/admin/login`, melainkan ke `/livewire-<hash>/update`, jadi aturan khusus `/admin/login` tidak efektif; pembatas laju aplikasi (5 percobaan per menit) tetap kontrol utama. Blokir path `/.env`, `/.git/`, `/vendor/`, `/storage/app/`, `/storage/logs/` sebagai lapis tambahan (jangan blokir `/storage/` seluruhnya: itu jalur gambar, galeri, dan PDF biaya). Aplikasi sudah punya pembatas laju sendiri (login admin 5 percobaan per menit per IP; pendaftaran 3 per menit per IP+sesi, 10 per menit dan 30 per jam per IP).
- **Turnstile**: buat widget (Managed) untuk host publik; salin site key dan secret ke `.env`. Turnstile hanya ada di formulir pendaftaran publik, **tidak** di login admin.
- **Cloudflare Access untuk admin (sangat disarankan)**: di Zero Trust > Access > Applications buat aplikasi self-hosted untuk host publik dengan path `/admin*` dan `/livewire*`, kebijakan Allow hanya untuk surel pengurus/panitia (One-time PIN atau login Google). Login admin tidak memakai Turnstile dan hanya dibatasi 5 percobaan per menit per IP; Access menambah lapis identitas sebelum halaman login terjangkau. Perhatikan: form publik tidak memakai Livewire, jadi menutup `/livewire*` tidak mengganggu pendaftaran; unduh KK (`/admin/pendaftar/*/kk`) ikut terlindungi. Uji setelah mengaktifkan: buka `/admin` dari jendela penyamaran harus memunculkan layar Access.
- Bot Fight Mode boleh aktif; pastikan tidak menantang `/up` dan unduhan admin.

## 8. Cadangan

- Terjadwal `backup:run` tiap 02:00: dump database MySQL + `storage/app/kk` (file KK, terenkripsi `APP_KEY`) + `storage/app/public` (unggahan) menjadi satu zip terenkripsi AES (sandi `BACKUP_ARCHIVE_PASSWORD`) di `storage/app/backups/<APP_NAME>/` (di luar `public/`, tidak dilayani web). Retensi (`config/backup.php`): semua 7 hari, harian 16 hari, mingguan 8 minggu, bulanan 6 bulan, tahunan 1 tahun, batas 5000 MB. `backup:clean` 02:30 menghapus yang lewat batas; `backup:monitor` 03:00 memeriksa umur cadangan terbaru (maks 2 hari).
- **Salin keluar server** (cadangan di disk yang sama tidak menolong bila disk rusak). Contoh rclone ke penyimpanan cloud/NAS (cron harian 04:00):

  ```
  0 4 * * * rclone copy /www/wwwroot/pps/storage/app/backups remote:pps-backups --max-age 3d
  ```

  atau `rsync -a --ignore-existing /www/wwwroot/pps/storage/app/backups/ user@host:/path/`. Arsip sudah bersandi, tetapi tetap simpan di tempat berakses terbatas.
- **Sangat disarankan mengisi `BACKUP_NOTIFICATION_EMAIL`** (plus `MAIL_*` yang benar) atau peringatan lain. Notifikasi mati bawaan; tanpa itu cadangan malam yang gagal hanya tampak di `storage/logs` dan mudah tak terlihat. Cek manual: `php artisan backup:list`.
- `backup:run` manual di production menolak berjalan bila `BACKUP_ARCHIVE_PASSWORD` kosong (arsip tidak boleh tanpa enkripsi).
- Latihan pemulihan (restore drill) **tiap bulan**: pulihkan cadangan terbaru ke mesin lain (bagian 9), buka satu NIK dan satu KK. Cadangan yang belum pernah dipulihkan belum bisa dianggap ada.

## 9. Pemulihan (restore)

Prosedur (ditelaah di atas kertas; latih di mesin cadangan sebelum go-live):

1. Siapkan server baru sesuai bagian 1-4 **tanpa** `key:generate` dan **tanpa** seed. Pasang kode yang sama.
2. Isi `.env` dengan `APP_KEY` dan `NIK_HMAC_KEY` **yang sama persis** dengan server lama (dari penyimpanan kunci, bagian 10).
3. Ekstrak arsip (butuh sandi AES; pakai `7z`, `unzip` lama tidak mendukung AES): `7z x backup.zip -o/tmp/restore` (7z menanyakan sandi; jangan menaruh sandi di baris perintah karena tersimpan di riwayat shell dan daftar proses). Isi: `db-dumps/*.sql` dan `storage/app/kk`, `storage/app/public` (path relatif terhadap folder proyek).
4. Database: buat DB kosong lalu `mysql -u USER -p DBNAME < /tmp/restore/db-dumps/mysql-DBNAME.sql`.
5. File: salin `storage/app/kk` dan `storage/app/public` hasil ekstraksi ke folder proyek, `chown -R www:www storage`, `php artisan storage:link`.
6. `php artisan optimize:clear && php artisan optimize`, lalu masuk admin, buka satu pendaftar (NIK tampil) dan unduh satu KK untuk memastikan kunci benar.
7. `php artisan pendaftaran:rehash-nik` **hanya bila** `NIK_HMAC_KEY` berbeda dari yang dipakai saat data dibuat (cek duplikat dan pencarian NIK akurat lagi). NIK tetap terbaca selama `APP_KEY` sama.
8. Pemulihan di tempat (server yang sama, data rusak/salah): `php artisan down`, kosongkan database (`php artisan db:wipe --force` atau drop/create), impor dump (langkah 4), salin file (langkah 5), pastikan `APP_KEY` dan `NIK_HMAC_KEY` di `.env` tidak berubah, `php artisan optimize:clear && php artisan optimize`, lalu `php artisan up`.
9. Bila `APP_KEY` tidak ada: NIK dan KK **tidak dapat dipulihkan**; data lain (nama, alamat, dan seterusnya) tetap ada.

## 10. Penyimpanan kunci (wajib dibaca)

Tiga rahasia ini **harus disimpan terpisah dari cadangan dan di luar server** (password manager milik pengurus + salinan tercetak dalam amplop di tempat aman; minimal dua orang berwenang tahu tempatnya):

| Rahasia | Bila hilang |
|---|---|
| `APP_KEY` | NIK dan semua file KK **hilang permanen** (tidak bisa didekripsi siapa pun). |
| `NIK_HMAC_KEY` | Cek duplikat/pencarian NIK tidak cocok; bisa dibangun ulang dengan kunci baru + `pendaftaran:rehash-nik` selama `APP_KEY` ada. |
| `BACKUP_ARCHIVE_PASSWORD` | Arsip cadangan tidak bisa dibuka. |

Aturan: jangan menyimpan ketiganya di folder cadangan atau repositori; jangan menjalankan `key:generate` di server yang sudah berisi data; catat tanggal pembuatan; uji bahwa salinan kunci terbaca saat latihan pemulihan.

## 11. Daftar periksa keamanan pasca-deploy

Dari komputer lain (ganti `HOST`):

```bash
# 1. Header keamanan
curl -sI https://HOST/ | grep -iE "content-security|strict-transport|x-content-type|x-frame|referrer-policy|permissions-policy"

# 2. Rahasia tidak terjangkau (semua harus 404/403, bukan 200)
for p in /.env /.git/config /composer.json /storage/logs/laravel.log /storage/app/kk/ /storage/app/private/kk/ /vendor/autoload.php; do
  printf "%s -> " "$p"; curl -s -o /dev/null -w "%{http_code}\n" "https://HOST$p"; done

# 3. Debug mati: halaman error tidak menampilkan stack trace
curl -s https://HOST/rute-tidak-ada | head -c 300

# 4. Origin tidak terjangkau langsung (dari LAN/luar): harus gagal/timeout
curl -m 5 http://IP-SERVER/ ; curl -m 5 http://IP-SERVER:8888/
```

Dan di aplikasi/server:

- **Login admin dibatasi:** salah sandi 6 kali dalam semenit di `/admin/login` harus memunculkan pesan terlalu banyak percobaan.
- **Turnstile benar-benar berfungsi dari server:** dari server jalankan `curl -sS -m 10 -X POST https://challenges.cloudflare.com/turnstile/v0/siteverify -d "secret=SECRET&response=x"` (harus membalas JSON `success:false`, bukan timeout; bila timeout, firewall/DNS memblokir HTTPS keluar ke `challenges.cloudflare.com` dan pendaftaran gagal tertutup). Lalu isi formulir `/daftar` sungguhan sampai langkah terakhir dengan widget Turnstile. Verifikasi asli ke Cloudflare belum pernah diuji dari mesin pengembangan.
- `composer security` (`composer audit` + `npm audit --audit-level=high`) bersih, atau temuan dipahami dan dicatat.
- `php artisan schedule:list` menampilkan cadangan; jalankan `php artisan backup:run` manual sekali, lalu `php artisan backup:list`, dan uji buka arsip dengan sandi.
- Izin: `.env` tidak terbaca user lain; `storage/app/kk` dan `storage/app/backups` tidak dapat dibaca selain user web.
- Zona waktu server dan `APP_TIMEZONE=Asia/Jakarta` cocok agar jadwal 02:00 tepat.

## 12. Yang perlu diisi admin pertama kali

Setelah masuk `/admin` sebagai superadmin:

1. **Pengaturan situs** (`/admin/manage-site-settings`): nama pondok, logo, kontak, alamat, nomor WhatsApp panitia, tautan sosial, **PDF rincian biaya**, status penerimaan.
2. **Bagian landing** (Landing Sections): hero, profil, video profil, galeri; ganti isi contoh dengan foto/teks/video asli.
3. **Data master pendaftaran**: jenjang, kelas per jenjang, pendidikan terakhir (aktifkan/nonaktifkan yang relevan). Isi awal hanya dibuat sekali saat instalasi; perubahan dan penghapusan dari admin tidak dibatalkan oleh seeder.
4. **Berita**: kategori dan beberapa tulisan awal.
5. **Pengguna dan role**: buat akun panitia/penulis; ganti sandi superadmin awal; jangan membagikan akun superadmin. Izin tiap role diatur di Shield > Roles (termasuk izin khusus: ekspor, lihat NIK lengkap, unduh KK, kelola semua berita, pengaturan situs). Role `superadmin` tidak dapat diganti nama atau dihapus.
6. Uji alur pendaftaran dari ponsel, lalu hapus data uji dari admin.
