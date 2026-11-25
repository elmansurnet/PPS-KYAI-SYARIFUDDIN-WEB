# Panduan deploy: aaPanel + Cloudflare Tunnel

Panduan ini untuk server lokal/VPS dengan aaPanel (Nginx + PHP-FPM + MySQL) yang dipublikasikan lewat Cloudflare Zero Trust Tunnel (`cloudflared`). Perintah dijalankan dari folder proyek (mis. `/www/wwwroot/pps`) sebagai user web server (biasanya `www`).

> **Peringatan utama.** NIK dan file KK terenkripsi dengan `APP_KEY`; pencarian dan cek duplikat NIK memakai `NIK_HMAC_KEY`. **Bila `APP_KEY` hilang, NIK dan KK hilang selamanya**, cadangan secanggih apa pun tidak bisa menolong. Baca [Penyimpanan kunci](#10-penyimpanan-kunci-wajib-dibaca) sebelum go-live.

## 1. Prasyarat

- PHP 8.3 dengan ekstensi: `pdo_mysql`, `mbstring`, `intl`, `gd`, `zip`, `fileinfo`, `bcmath`, `openssl`, `curl`, `xml`, `dom`, `exif`, `ctype`, `tokenizer`. (`sqlite` tidak diperlukan di production; hanya untuk tes.) Fungsi `proc_open` dan `exec` jangan masuk `disable_functions` (dipakai `mysqldump` oleh cadangan; di aaPanel biasanya perlu dihapus dari daftar itu).
- MySQL 8 (atau MariaDB setara) dan biner `mysqldump` (aaPanel: `/www/server/mysql/bin`).
- Composer 2.
- Node 22 hanya untuk build aset (boleh di mesin lain, lalu salin `public/build`).
- `cloudflared` terpasang di host yang sama.
- Pengaturan PHP (aaPanel: App Store > PHP 8.3 > Setting > Configuration):
  - `upload_max_filesize = 8M` dan `post_max_size = 8M` (file KK maks 2 MB; nilai harus di atasnya).
  - `memory_limit = 256M` atau lebih (ekspor PDF).
  - `max_execution_time = 120` atau lebih (ekspor Excel/PDF).
  - `expose_php = Off`.
  - `request_terminate_timeout` PHP-FPM jangan lebih kecil dari `max_execution_time`.

## 2. Situs di aaPanel (Nginx)

1. Website > Add site: domain = host publik tunnel (mis. `pendaftaran.contoh.sch.id`), PHP 8.3, database MySQL (catat nama/user/sandi).
2. Site directory = folder proyek; **Running directory = `/public`** (Nginx root = `public/`). Bila open_basedir aaPanel aktif, pastikan mencakup folder proyek, bukan hanya `public/`.
3. Rewrite Laravel (Pseudo-static):

   ```nginx
   location / {
       try_files $uri $uri/ /index.php?$query_string;
   }
   ```

4. Tolak berkas tersembunyi dan direktori internal (di konfigurasi situs, sebelum blok `location ~ \.php$`):

   ```nginx
   location ~ /\.(?!well-known) { deny all; }
   location ~ ^/(storage|vendor|bootstrap|config|database|Modules|tests) { deny all; }
   location ~* \.(env|log|sql|bak|zip|tar|gz|md|lock)$ { deny all; }
   ```

   Karena root = `public/`, folder itu sebenarnya sudah tidak terjangkau; aturan ini lapis kedua. Jangan pernah menaruh `.env`, cadangan, atau ekspor di dalam `public/`.
5. Origin hanya untuk tunnel (bagian 7): Nginx **harus** mendengarkan `127.0.0.1` saja (mis. `listen 127.0.0.1:80;`) atau port 80/443 diblokir dari luar oleh firewall.

## 3. Checklist `.env` production

```
APP_NAME="PPs Kyai Syarifuddin"
APP_ENV=production
APP_DEBUG=false
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
BACKUP_NOTIFICATION_EMAIL=                  # opsional; butuh MAIL_* yang benar
```

Catatan:

- `APP_URL` menentukan host tepercaya (`TrustHosts` hanya mengizinkan host ini, tanpa subdomain atau `www`) dan memaksa skema/URL akar. Salah isi = situs menolak permintaan atau tautan salah.
- `.env` berizin `640`, pemilik user web; tidak masuk git; tidak ada di `public/`.
- Jangan menaruh nilai nyata di `.env.example`.

## 4. Pemasangan pertama

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build            # atau build di mesin lain dan salin public/build
cp .env.example .env               # lalu isi sesuai bagian 3
php artisan key:generate           # SEKALI SAJA. Menjalankannya lagi membuat data terenkripsi tak terbaca.
php artisan migrate --force
php artisan db:seed --force        # RoleSeeder (role + superadmin), lalu seeder modul (Landing, Berita, Pendaftaran + data master)
php artisan shield:generate --all --panel=admin --option=permissions
php artisan filament:upgrade       # publikasi aset Filament
php artisan storage:link           # hanya untuk storage/app/public (gambar situs/berita)
php artisan optimize               # config, route, view, event cache
```

Catatan:

- `--optimize-autoloader` pernah menggantung di mesin pengembangan; di server produksi seharusnya normal. Bila menggantung, jalankan `composer dump-autoload -o` terpisah.
- **Jangan** menjalankan `shield:generate` tanpa `--option=permissions`: opsi lain menimpa policy yang sudah disesuaikan (mis. `UserPolicy`).
- Setelah seed pertama berhasil, **hapus `SUPERADMIN_EMAIL` dan `SUPERADMIN_PASSWORD` dari `.env`**, lalu `php artisan config:cache`.
- Jalankan `php artisan optimize:clear && php artisan optimize` setiap `.env` berubah.
- Izin berkas: `chown -R www:www storage bootstrap/cache` dan `chmod -R ug+rwX storage bootstrap/cache`.
- Instalasi lama yang menyimpan KK di `storage/app/private/kk` (lokasi lama): pindahkan sekali dengan `[ -d storage/app/private/kk ] && mv storage/app/private/kk storage/app/kk`. Root disk `kk` sekarang `storage/app/kk`, di luar root disk `local` (`storage/app/private`).

## 5. Penjadwal (scheduler)

Cadangan harian berjalan lewat penjadwal Laravel. Tambahkan cron (aaPanel: Cron > Shell script, tiap menit, user `www`):

```
* * * * * cd /www/wwwroot/pps && php artisan schedule:run >> /dev/null 2>&1
```

Periksa dengan `php artisan schedule:list` (harus tampil `backup:run` 02:00, `backup:clean` 02:30, `backup:monitor` 03:00). Cadangan hanya berjalan bila `BACKUP_ARCHIVE_PASSWORD` terisi; tanpa sandi arsip tidak terenkripsi, jadi sengaja ditolak dan dicatat ke log.

## 6. Pembaruan dan rollback

Pembaruan:

```bash
php artisan backup:run             # cadangan dulu; pastikan berhasil
php artisan down --retry=60
git pull                           # atau unggah rilis baru
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan filament:upgrade
php artisan optimize:clear && php artisan optimize
php artisan up
```

Rollback: `php artisan down`, kembalikan kode ke rilis sebelumnya (`git checkout <tag>` lalu `composer install --no-dev`), bila migrasi baru merusak data pulihkan database dari cadangan (bagian 9), lalu `optimize` dan `up`. Jangan pernah mengubah `APP_KEY` saat rollback.

## 7. Cloudflare Tunnel

- `cloudflared` berjalan di host yang sama. Public hostname di Zero Trust (Networks > Tunnels) menunjuk ke `http://127.0.0.1:80`.
- **Host publik tunnel harus sama dengan host `APP_URL`.** `TrustHosts` hanya menerima host itu; tidak ada alias `www` atau apex kecuali ditambahkan ke kode. Header Host yang diteruskan ke origin harus host publik (jangan ditimpa ke `localhost`).
- **Origin hanya `127.0.0.1`.** Aplikasi mempercayai `X-Forwarded-For` dan `X-Forwarded-Proto` dari semua proxy (`*`) karena satu-satunya pihak yang berbicara ke origin adalah `cloudflared` lokal. Bila origin bisa dijangkau langsung dari LAN/internet, penyerang dapat memalsukan IP (melewati pembatas laju per IP dan mengotori log audit) dan skema. Karena itu: Nginx `listen 127.0.0.1:80`, firewall menutup 80/443/3306 dari luar, dan port panel aaPanel jangan dibuka ke publik tanpa pembatasan.
- Health check/uptime harus mengirim header `Host` publik, mis. `curl -H "Host: pendaftaran.contoh.sch.id" http://127.0.0.1/up`; tanpa itu hasilnya 400 (host tak tepercaya).
- Pindah ke VPS tanpa tunnel: sempitkan `trustProxies` ke IP proxy yang sah; jangan biarkan `*` pada origin yang terbuka ke internet.

### Pengaturan Cloudflare yang disarankan

- SSL/TLS **Full (strict)**, **Always Use HTTPS** aktif, Automatic HTTPS Rewrites aktif, TLS minimum 1.2.
- **HSTS**: aplikasi mengirim `Strict-Transport-Security` dengan `includeSubDomains`. Situs harus hidup di subdomainnya sendiri, atau seluruh domain apex dan subdomain lain sudah penuh HTTPS; jika tidak, subdomain HTTP lain tak terjangkau di browser yang pernah membuka situs ini.
- **WAF/Rate limiting**: batasi `POST /daftar/*` dan `/admin/login` (mis. 20 permintaan per menit per IP lalu challenge/block); blokir path `/.env`, `/.git`, `/storage`, `/vendor` sebagai lapis tambahan. Aplikasi sudah punya pembatas laju sendiri (login admin 5 percobaan per menit per IP; pendaftaran 3 per menit per IP+sesi, 10 per menit dan 30 per jam per IP).
- **Turnstile**: buat widget (Managed) untuk host publik; salin site key dan secret ke `.env`.
- Bot Fight Mode boleh aktif; pastikan tidak menantang `/up` dan unduhan admin.

## 8. Cadangan

- Terjadwal `backup:run` tiap 02:00: dump database MySQL + `storage/app/kk` (file KK, terenkripsi `APP_KEY`) + `storage/app/public` (unggahan) menjadi satu zip terenkripsi AES (sandi `BACKUP_ARCHIVE_PASSWORD`) di `storage/app/backups/<APP_NAME>/` (di luar `public/`, tidak dilayani web). Retensi (`config/backup.php`): semua 7 hari, harian 16 hari, mingguan 8 minggu, bulanan 6 bulan, tahunan 1 tahun, batas 5000 MB. `backup:clean` 02:30 menghapus yang lewat batas; `backup:monitor` 03:00 memeriksa umur cadangan terbaru (maks 2 hari).
- **Salin keluar server** (cadangan di disk yang sama tidak menolong bila disk rusak). Contoh rclone ke penyimpanan cloud/NAS (cron harian 04:00):

  ```
  0 4 * * * rclone copy /www/wwwroot/pps/storage/app/backups remote:pps-backups --max-age 3d
  ```

  atau `rsync -a --ignore-existing /www/wwwroot/pps/storage/app/backups/ user@host:/path/`. Arsip sudah bersandi, tetapi tetap simpan di tempat berakses terbatas.
- Notifikasi surel mati bawaan. Isi `BACKUP_NOTIFICATION_EMAIL` dan `MAIL_*` untuk kabar gagal/tidak sehat; atau periksa `storage/logs` dan `php artisan backup:list` berkala.
- Uji pemulihan minimal sekali sebelum go-live dan tiap kuartal.

## 9. Pemulihan (restore)

Prosedur (ditelaah di atas kertas; latih di mesin cadangan sebelum go-live):

1. Siapkan server baru sesuai bagian 1-4 **tanpa** `key:generate` dan **tanpa** seed. Pasang kode yang sama.
2. Isi `.env` dengan `APP_KEY` dan `NIK_HMAC_KEY` **yang sama persis** dengan server lama (dari penyimpanan kunci, bagian 10).
3. Ekstrak arsip (butuh sandi AES; pakai `7z`, `unzip` lama tidak mendukung AES): `7z x -p"$BACKUP_ARCHIVE_PASSWORD" backup.zip -o/tmp/restore`. Isi: `db-dumps/*.sql` dan `storage/app/kk`, `storage/app/public` (path relatif terhadap folder proyek).
4. Database: buat DB kosong lalu `mysql -u USER -p DBNAME < /tmp/restore/db-dumps/mysql-DBNAME.sql`.
5. File: salin `storage/app/kk` dan `storage/app/public` hasil ekstraksi ke folder proyek, `chown -R www:www storage`, `php artisan storage:link`.
6. `php artisan optimize:clear && php artisan optimize`, lalu masuk admin, buka satu pendaftar (NIK tampil) dan unduh satu KK untuk memastikan kunci benar.
7. `php artisan pendaftaran:rehash-nik` **hanya bila** `NIK_HMAC_KEY` berbeda dari yang dipakai saat data dibuat (cek duplikat dan pencarian NIK akurat lagi). NIK tetap terbaca selama `APP_KEY` sama.
8. Bila `APP_KEY` tidak ada: NIK dan KK **tidak dapat dipulihkan**; data lain (nama, alamat, dan seterusnya) tetap ada.

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
3. **Data master pendaftaran**: jenjang, kelas per jenjang, pendidikan terakhir (aktifkan/nonaktifkan yang relevan).
4. **Berita**: kategori dan beberapa tulisan awal.
5. **Pengguna dan role**: buat akun panitia/penulis; ganti sandi superadmin awal; jangan membagikan akun superadmin.
6. Uji alur pendaftaran dari ponsel, lalu hapus data uji dari admin.
