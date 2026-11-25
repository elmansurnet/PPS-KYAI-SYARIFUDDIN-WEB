# Panduan deploy: VPS + aaPanel (Nginx/Apache) + DNS Cloudflare

Panduan ini untuk memasang website di **VPS dengan IP publik**, memakai aaPanel (Nginx atau Apache) dan **DNS domain dikelola di Cloudflare** (rekaman DNS ber-proxy, tanpa `cloudflared`). Panduan ini melengkapi [`DEPLOY-aapanel.md`](DEPLOY-aapanel.md); bagian yang sama (checklist `.env`, pemasangan aplikasi, pembaruan, cadangan, pemulihan, kunci, daftar periksa, isian admin) **tidak diulang** dan harus dibaca di sana.

> **Peringatan utama.** NIK dan file KK terenkripsi dengan `APP_KEY`; **bila `APP_KEY` hilang, data itu hilang selamanya**. Snapshot VPS bukan pengganti cadangan terenkripsi dan kunci yang disimpan terpisah (bagian 8 dan 10 panduan aaPanel).

## 0. Pilih topologi

| | **A. Tunnel** (`cloudflared`) | **B. DNS ber-proxy + VPS terbuka** (panduan ini) |
|---|---|---|
| Port masuk di VPS | tidak ada (hanya SSH) | 443 untuk IP Cloudflare saja |
| Rekaman DNS | dibuat otomatis (CNAME ke tunnel) | A (dan opsional AAAA) ke IP VPS, **Proxied** |
| TLS ke origin | tidak perlu (HTTP di 127.0.0.1) | wajib (Origin CA + mode **Full (strict)**) |
| Risiko utama | `cloudflared` mati | origin bisa dijangkau langsung bila firewall bocor |
| Kerja awal | paling sedikit | lebih banyak, bagian 5-6 wajib |

Bila ragu, pakai topologi A (bagian 7 panduan aaPanel): tidak ada port web yang terbuka, jadi tidak ada cara melewati Cloudflare. Pakai B bila Anda memang membutuhkan origin dengan IP publik. Aplikasi ini mempercayai `X-Forwarded-For`/`X-Forwarded-Proto` dari pihak yang terhubung ke origin (`trustProxies('*')`, hanya header For dan Proto), sehingga **syarat mutlak topologi B**: origin tidak boleh menerima koneksi dari siapa pun selain Cloudflare (bagian 6). Bila syarat itu bocor, penyerang bisa memalsukan IP klien (melewati pembatas laju per IP dan mengotori log audit).

## 1. Menyiapkan VPS

Spesifikasi awal yang masuk akal: 2 vCPU, 2 GB RAM (4 GB bila ekspor PDF besar dan banyak admin), 40 GB SSD, Ubuntu 22.04/24.04 LTS atau Debian 12 (OS yang didukung aaPanel; cek halaman resmi aaPanel).

1. Perbarui sistem dan atur zona waktu (jadwal cadangan 02:00 mengikuti `APP_TIMEZONE`):

   ```bash
   sudo apt update && sudo apt full-upgrade -y
   sudo timedatectl set-timezone Asia/Jakarta
   sudo apt install -y unattended-upgrades fail2ban
   ```

2. Tambah swap bila RAM ≤ 2 GB (`fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile`, lalu tambahkan ke `/etc/fstab`).
3. Akses SSH: buat user non-root dengan `sudo`, pakai **kunci SSH**, nonaktifkan login root dan sandi (`PermitRootLogin no`, `PasswordAuthentication no` di `/etc/ssh/sshd_config`). Uji dari sesi kedua sebelum menutup sesi pertama.
4. Firewall sementara (sebelum Cloudflare siap): izinkan SSH saja dari IP pengurus. Aturan web (443 hanya dari Cloudflare) dibuat di bagian 6. **Pakai satu pengelola firewall saja**: panel aaPanel (Security) atau `ufw`/`firewalld` manual, jangan campur. Bila penyedia VPS punya firewall tingkat jaringan (security group), gunakan juga; itu lapis terbaik karena paket ditolak sebelum menyentuh VPS.

## 2. Memasang aaPanel dan tumpukan aplikasi

1. Pasang aaPanel dengan perintah **dari situs resmi aaPanel** (jangan menjalankan skrip dari sumber lain; jalankan sebagai root di VPS baru). Catat alamat panel, port, dan "security entrance" yang ditampilkan.
2. Amankan panel: ganti port panel dan entrance bawaan, aktifkan 2FA dan sandi kuat, **batasi port panel hanya untuk IP pengurus** (firewall), dan jangan membuat rekaman DNS ber-proxy ke panel (panel dibuka lewat `https://IP:PORT/ENTRANCE`).
3. App Store: pasang **satu** web server (Nginx **atau** Apache), **MySQL 8.0**, dan **PHP 8.3** dengan ekstensi pada bagian 1 panduan aaPanel (`fileinfo`, `exif`, `intl`, `gd`, `zip`, `bcmath`, dst.). Hapus `proc_open` dari `disable_functions` (dipakai cadangan). Atur `upload_max_filesize`/`post_max_size` = `12M`, `memory_limit` ≥ `256M`, `max_execution_time` = `120`, `expose_php = Off`.
4. Pasang Composer (aaPanel: Terminal/App Store) dan Node 22 (hanya untuk build aset; boleh build di mesin lain lalu salin `public/build`).

## 3. DNS di Cloudflare

### 3.1 Memindahkan domain ke Cloudflare

1. Tambah domain di dasbor Cloudflare (paket **Free** cukup). Cloudflare memindai rekaman yang ada.
2. **Cocokkan hasil pindaian dengan rekaman lama sebelum mengganti nameserver** (situs lama, `MX`, `TXT` SPF/DKIM/DMARC, verifikasi layanan lain); rekaman yang terlewat membuat surel atau layanan lain mati.
3. Ganti nameserver di registrar domain ke dua nameserver yang diberikan Cloudflare. Pada paket Free seluruh zona harus dipindah (tidak bisa hanya satu subdomain). Bila domain induk dikelola pihak lain (mis. domain institusi), minta mereka mendelegasikan zona atau memakai domain/subdomain terpisah.
4. Tunggu status zona **Active**. Aktifkan **DNSSEC** (Cloudflare DNS > Settings), lalu pasang rekaman DS yang diberikan di registrar.

### 3.2 Rekaman untuk website

Misal host publik `pendaftaran.contoh.sch.id` (harus sama dengan host di `APP_URL`):

| Tipe | Nama | Isi | Proxy |
|---|---|---|---|
| A | `pendaftaran` | IP publik VPS | **Proxied** (awan oranye) |
| CNAME | `www.pendaftaran` (opsional) | `pendaftaran.contoh.sch.id` | Proxied |

Catatan:

- **Jangan membuat AAAA** kecuali VPS punya IPv6 dan firewall IPv6-nya seketat IPv4. Pengunjung tetap bisa memakai IPv6 ke tepi Cloudflare tanpa AAAA di origin.
- **Aplikasi hanya menerima satu host** (host `APP_URL`; `TrustHosts` menolak yang lain dengan 404). Jadi alias seperti `www` **tidak boleh** dilayani langsung: buat aturan **Redirect Rules** (Rules > Redirect Rules) dari `www.…` ke host utama (301, pertahankan path dan query), atau jangan membuat alias sama sekali.
- Rekaman surel (`MX`, SPF/DKIM `TXT`) selalu **DNS only** (awan abu-abu); hanya lalu lintas HTTP(S) yang boleh Proxied. Aplikasi ini tidak mengirim surel kecuali notifikasi cadangan opsional lewat SMTP; bila dipakai, tambahkan SPF/DKIM penyedia SMTP di sini.
- TTL "Auto" untuk rekaman ber-proxy. Sebelum pindah server, turunkan TTL rekaman DNS only terkait.
- Rekaman ber-proxy menyembunyikan IP origin, tetapi **jangan** mengandalkannya sebagai pengaman: IP bisa bocor (rekaman lama, surel, subdomain DNS only). Pengamannya adalah bagian 6.
- Dengan topologi A (tunnel) rekaman CNAME dibuat otomatis oleh tunnel; hapus rekaman A lama dengan nama yang sama.

Verifikasi: `dig +short pendaftaran.contoh.sch.id` harus menampilkan IP Cloudflare (bukan IP VPS); `dig NS contoh.sch.id +short` menampilkan nameserver Cloudflare.

## 4. Memasang aplikasi

Ikuti panduan aaPanel: bagian 2 (situs, Nginx atau Apache), 3 (`.env`), 4 (pemasangan pertama), 5 (penjadwal). Hal khusus VPS:

- `APP_URL=https://pendaftaran.contoh.sch.id` (URL HTTPS publik, tanpa slash akhir): menentukan host tepercaya dan memaksa skema.
- Web server pada situs ini **melayani HTTPS di 443** (bagian 5 di bawah); bagian 2.3 panduan aaPanel (bind `127.0.0.1`) **tidak berlaku** untuk topologi B, diganti oleh bagian 6.
- Kode ditarik dengan deploy key baca-saja (`git clone git@github.com:…`) atau diunggah; jangan menaruh token pribadi di VPS.

## 5. TLS ke origin: Cloudflare Origin CA + mode Full (strict)

Mode SSL/TLS Cloudflare **harus Full (strict)**. Mode **Flexible** membuat Cloudflare berbicara HTTP ke origin dan bersama "Always Use HTTPS" menyebabkan loop pengalihan; jangan dipakai. (Sertifikat Let's Encrypt via HTTP-01 sulit di sini karena port 80 ditutup; gunakan Origin CA.)

1. Cloudflare > SSL/TLS > **Origin Server** > Create Certificate: RSA 2048, hostname `pendaftaran.contoh.sch.id` (dan wildcard bila perlu), masa berlaku 15 tahun. Salin **sertifikat** dan **kunci privat** (kunci hanya ditampilkan sekali; simpan di pengelola sandi).
2. aaPanel: Website > situs > Settings > **SSL** > "Other certificate" (Lainnya): tempel kunci dan sertifikat, simpan. **Jangan** mengaktifkan "Force HTTPS" di aaPanel: Cloudflare yang melakukan pengalihan ke HTTPS, dan port 80 di origin ditutup.
3. Cloudflare > SSL/TLS > **Overview**: pilih **Full (strict)**. Edge Certificates: **Always Use HTTPS** aktif, **Minimum TLS 1.2**, Automatic HTTPS Rewrites aktif. **Jangan** mengaktifkan HSTS di Cloudflare: aplikasi sudah mengirim `Strict-Transport-Security` (dengan `includeSubDomains`), header ganda tidak perlu.

Sertifikat Origin CA hanya dipercaya oleh Cloudflare; mengakses origin langsung lewat IP akan tampak tidak tepercaya di browser, dan itu memang disengaja.

## 6. Mengunci origin hanya untuk Cloudflare (wajib)

Dua lapis; pasang keduanya.

### 6.1 Firewall: 443 hanya dari IP Cloudflare

Daftar IP resmi: `https://www.cloudflare.com/ips-v4` dan `https://www.cloudflare.com/ips-v6`. Contoh dengan `ufw` (Ubuntu/Debian). **Aturan SSH harus ada sebelum `ufw enable`** atau Anda terkunci keluar; uji dari sesi SSH kedua.

```bash
# 1. Dasar (SSH dan panel hanya dari IP pengurus)
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow from <IP-PENGURUS> to any port 22 proto tcp
sudo ufw allow from <IP-PENGURUS> to any port <PORT-PANEL> proto tcp

# 2. Simpan skrip ini sebagai /usr/local/sbin/cf-allow.sh (chmod +x) lalu jalankan:
#!/usr/bin/env bash
set -euo pipefail
for ip in $(curl -fsS https://www.cloudflare.com/ips-v4) $(curl -fsS https://www.cloudflare.com/ips-v6); do
  ufw allow proto tcp from "$ip" to any port 443 comment cloudflare
done

# 3. Aktifkan dan periksa
sudo ufw enable
sudo ufw status numbered
```

- **Tutup port 80** (tidak diperlukan: Cloudflare menghubungi origin di 443 pada mode Full (strict)), serta 3306 dan semua port lain dari luar. MySQL hanya `127.0.0.1`.
- Daftar IP Cloudflare jarang berubah. Ulangi skrip tiap bulan (cron) dan tinjau aturan lama yang tidak ada lagi di daftar resmi (`ufw status numbered`, lalu `ufw delete N`). Di aaPanel, padanannya: Security > Firewall > aturan port dengan sumber IP/CIDR.
- Bila penyedia VPS punya firewall jaringan, masukkan daftar yang sama di sana juga.

### 6.2 Authenticated Origin Pulls (mTLS)

Firewall bisa keliru atau terlewat. Authenticated Origin Pulls membuat origin menolak koneksi TLS yang tidak membawa sertifikat klien milik Cloudflare, sehingga IP lain (termasuk pengguna Cloudflare lain) tidak bisa menembus meski firewall bocor.

1. Cloudflare > SSL/TLS > Origin Server > **Authenticated Origin Pulls**: aktifkan.
2. Unduh sertifikat CA "Cloudflare Authenticated Origin Pull" dari dokumentasi resmi Cloudflare (topik Authenticated Origin Pulls) ke VPS, mis. `/www/server/panel/vhost/cert/cloudflare-origin-pull-ca.pem`.
3. Tambahkan di konfigurasi situs aaPanel (Settings > Config file; di dalam blok HTTPS situs):

   Nginx (`server { listen 443 ssl; ... }`):

   ```nginx
   ssl_client_certificate /www/server/panel/vhost/cert/cloudflare-origin-pull-ca.pem;
   ssl_verify_client on;
   ```

   Apache (`<VirtualHost *:443>`):

   ```apache
   SSLCACertificateFile /www/server/panel/vhost/cert/cloudflare-origin-pull-ca.pem
   SSLVerifyClient require
   SSLVerifyDepth 1
   ```

4. Simpan, uji sintaks (`nginx -t` atau `/www/server/apache/bin/httpd -t`), muat ulang. aaPanel dapat membuat ulang berkas vhost saat domain/SSL/versi PHP diubah dari panel; periksa Config file setelah perubahan semacam itu.

### 6.3 IP klien di log web server (opsional)

Log Nginx/Apache memperlihatkan IP tepi Cloudflare. Aplikasi sendiri membaca IP klien dari `X-Forwarded-For` dan tidak membutuhkan ini. Bila ingin log web server memuat IP asli: Nginx `set_real_ip_from <rentang CF>;` untuk tiap rentang + `real_ip_header CF-Connecting-IP;`; Apache `mod_remoteip` (`RemoteIPHeader CF-Connecting-IP`, `RemoteIPTrustedProxy <rentang CF>`). Hanya aman bersama bagian 6.1/6.2 dan menambah pekerjaan menjaga daftar rentang; log memuat IP pengunjung (data pribadi), tetapkan masa simpan.

## 7. Pengaturan Cloudflare untuk aplikasi ini

Selain bagian 7 panduan aaPanel (WAF, rate limiting, Turnstile, Access):

- **Matikan yang menyuntik skrip/markup ke halaman**, karena CSP publik ketat (`script-src 'self'` + Turnstile) dan akan memblokirnya atau merusak halaman: Speed > Optimization > **Rocket Loader: Off**; Scrape Shield > **Email Address Obfuscation: Off**; **Web Analytics** tanpa penyuntikan otomatis (Analytics & Logs > Web Analytics: jangan "Enable" otomatis di host ini); **Auto Minify** (bila masih ada) off.
- **Cache**: biarkan bawaan (HTML tidak di-cache). **Jangan** membuat aturan "Cache Everything" untuk halaman dinamis (`/daftar*`, `/admin*`, `/livewire*`, `/berita*`): bisa membocorkan sesi/halaman pribadi. Aman di-cache: `/build/*` (berkas bernama-hash) dan `/fonts/*`.
- **Batas waktu 100 detik**: pada paket Free, Cloudflare memutus respons origin yang lebih lama dari 100 detik (kode 524). Ekspor Excel/PDF besar harus selesai di bawah itu; PDF sudah dibatasi 500 baris. Untuk ekspor sangat besar, saring dulu per jenjang atau rentang tanggal lalu ekspor per bagian.
- **Unggahan**: batas Cloudflare Free jauh di atas kebutuhan (100 MB); batas efektif adalah 12 MB di PHP/web server.
- **Turnstile**: buat widget Managed untuk host publik (Turnstile > Add site); salin site key dan secret ke `.env`. Pastikan VPS bisa keluar HTTPS ke `challenges.cloudflare.com` (firewall `allow outgoing`).
- **WAF/Rate limiting dan Access**: persis bagian 7 panduan aaPanel. Karena origin sekarang di internet, **Cloudflare Access untuk `/admin*` dan `/livewire*` sangat disarankan**; tanpa Access, login admin hanya dijaga pembatas laju aplikasi (5 percobaan per menit per IP).
- **Under Attack Mode** hanya saat diserang; menantang semua pengunjung dan memperlambat pendaftaran.
- **Bot Fight Mode** boleh aktif; pastikan tidak menantang `/up` dan unduhan admin.

## 8. Verifikasi

Dari komputer lain:

```bash
# DNS menunjuk ke Cloudflare, bukan ke VPS
dig +short pendaftaran.contoh.sch.id

# Situs hidup lewat Cloudflare
curl -sI https://pendaftaran.contoh.sch.id/ | grep -iE "^(HTTP|server|cf-ray|strict-transport|content-security|x-content-type)"

# Origin TIDAK terjangkau langsung (harus gagal/timeout/400 "No required SSL certificate")
curl -m 8 -k -I https://IP-VPS/ -H "Host: pendaftaran.contoh.sch.id"
curl -m 8 -I http://IP-VPS/                                        # port 80 harus timeout
nmap -Pn -p 22,80,443,3306,<PORT-PANEL> IP-VPS                     # hanya yang diizinkan terbuka dari IP Anda
```

Di VPS: `ss -ltnp` (MySQL hanya `127.0.0.1:3306`), lalu daftar periksa bagian 11 panduan aaPanel (header, `/.env` 404, login admin dibatasi, Turnstile dari server, `composer security`, cadangan).

Uji bahwa IP klien benar: lakukan pendaftaran percobaan dari ponsel (data seluler) dan dari Wi-Fi, lalu pastikan pembatas laju bekerja per klien (bukan semua pengunjung dihitung satu IP). Tanda salah: semua permintaan tampak berasal dari satu IP Cloudflare; periksa bahwa `X-Forwarded-For` sampai ke aplikasi (jangan ada proxy tambahan yang menimpa).

## 9. Operasional

- **Perpanjangan:** Origin CA berlaku 15 tahun (catat tanggalnya); pantau tanggal kedaluwarsa. Bila memakai Let's Encrypt via DNS-01 + Cloudflare API, pakai token API terbatas (Edit zone DNS untuk satu zona).
- **Pembaruan IP Cloudflare** di firewall: tiap bulan (bagian 6.1).
- **Cadangan keluar VPS** (bagian 8 panduan aaPanel): snapshot penyedia **tidak** menggantikan `backup:run` terenkripsi dan penyimpanan `APP_KEY`/`NIK_HMAC_KEY`/`BACKUP_ARCHIVE_PASSWORD` di luar server.
- **Pindah VPS:** pasang VPS baru (bagian 1-4), pulihkan cadangan dengan kunci yang sama (bagian 9 panduan aaPanel), uji lewat entri `hosts` lokal, lalu ubah isi rekaman A ke IP baru (Proxied; efeknya langsung karena lewat Cloudflare), buka 443 untuk IP Cloudflare di VPS baru, dan matikan VPS lama setelah beberapa hari.
- **Pemantauan:** uptime monitor ke `https://host/up` (tidak memakai Host khusus karena lewat Cloudflare); notifikasi Cloudflare untuk lonjakan lalu lintas/serangan; `BACKUP_NOTIFICATION_EMAIL` terisi.
- **Pembaruan OS/aaPanel/PHP** terjadwal; setelah memperbarui PHP, muat ulang PHP-FPM dan periksa situs.

## 10. Pemecahan masalah

| Gejala | Penyebab umum |
|---|---|
| **521** Web server is down | firewall menolak IP Cloudflare, web server tidak mendengarkan di 443, atau situs mati |
| **522/523** Timeout / tak terjangkau | firewall jaringan penyedia VPS menolak Cloudflare, IP di rekaman A salah, atau VPS mati |
| **524** A timeout occurred | respons origin > 100 detik (ekspor besar); persempit data atau pecah ekspor |
| **525** SSL handshake failed | origin tidak melayani TLS di 443, protokol/cipher tidak cocok, atau AOP diaktifkan di origin tetapi belum di Cloudflare |
| **526** Invalid SSL certificate | mode Full (strict) tetapi sertifikat origin salah host/kedaluwarsa/bukan Origin CA |
| **ERR_TOO_MANY_REDIRECTS** | mode SSL **Flexible**, atau Force HTTPS di origin bersama Always Use HTTPS; pakai Full (strict) dan matikan pengalihan di origin |
| **400 "No required SSL certificate was sent"** | AOP aktif di origin tetapi tidak aktif di Cloudflare (aktifkan di dasbor) |
| 404 di semua halaman, `APP_URL` benar | host yang diminta ≠ host `APP_URL` (mis. `www`); buat Redirect Rule `www` → host utama |
| Semua pengunjung dihitung satu IP / pembatas laju salah | origin terhubung lewat proxy tambahan, atau `X-Forwarded-For` tidak sampai; periksa rantai proxy |
| Halaman publik rusak/skrip diblokir (CSP) | Rocket Loader, Email Obfuscation, atau Web Analytics menyuntik skrip; matikan (bagian 7) |
| Turnstile selalu gagal | VPS tidak bisa HTTPS keluar ke `challenges.cloudflare.com`, atau site key/secret tidak cocok dengan host |
