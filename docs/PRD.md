# PRD Website PPs Kyai Syarifuddin

Sumber: `docs/superpowers/specs/2026-11-25-website-pps-design.md` (tanggal 2026-11-25). Bila PRD dan spec berbeda, spec yang berlaku. Nomor bagian di bawah merujuk ke spec.

## 1. Latar dan tujuan (spec 1, 2)

Wali santri perlu (1) mengenal profil pondok, (2) mengikuti berita terbaru, dan (3) mendaftarkan anaknya secara online. Kesan yang diinginkan: profesional, modern, agamis. Wali yang tertarik dari profil pondok harus bisa mendaftar dengan mudah dari HP. Proyek berjangka panjang: sekarang tiga fitur, fitur baru harus bisa ditambah tanpa mengganggu fitur yang sudah jalan.

**Tujuan**
- Landing page yang meyakinkan: video profil, sejarah, kegiatan unggulan, proses belajar mengajar.
- Berita yang mudah diterbitkan oleh penulis berita.
- Pendaftaran santri baru online yang nyaman di HP, data tersimpan rapi, wali diarahkan ke WhatsApp panitia.
- Semua konten dan pengaturan bisa diubah dari admin panel tanpa menyentuh kode.
- Arsitektur modular untuk fitur masa depan.

**Bukan-tujuan (untuk sekarang)**
- Pembayaran online. Biaya hanya informasi, dibayar di luar website.
- Tes, wawancara, atau pengumuman kelulusan. Semua pendaftar diterima.
- Komentar pengunjung pada berita, akun untuk wali santri, portal santri/akademik.
- Pembangun halaman bebas (drag-and-drop) atau formulir pendaftaran yang strukturnya bisa diubah admin.

**Anti-tujuan** (dianggap gagal walau secara teknis jalan)
- Data pribadi santri (NIK, kartu keluarga) bisa diakses publik.
- Tampilan terasa seperti template generik hasil AI.
- Menambah satu fitur baru memaksa mengubah modul lain.
- Formulir sulit diisi dari HP.

## 2. Persona dan role (spec 3)

| Persona | Peran |
|---|---|
| Pengunjung / calon wali santri | Membaca profil dan berita, mengunduh PDF biaya, mengisi formulir. Tanpa login. |
| Superadmin | Semua akses, ditambah mengatur izin tiap role. |
| Admin | Semua fitur, tanpa mengatur izin. |
| Penulis berita | Hanya modul berita. |
| Panitia pendaftaran | Hanya modul pendaftar. |

Izin tiap role bisa diatur superadmin dari admin panel (bukan hardcode).

### Matriks role-izin (tingkat modul)

| Kemampuan | Pengunjung | Penulis berita | Panitia | Admin | Superadmin |
|---|---|---|---|---|---|
| Lihat landing, berita terbit, unduh PDF biaya | Ya | Ya | Ya | Ya | Ya |
| Kirim formulir pendaftaran | Ya | Ya | Ya | Ya | Ya |
| Kelola berita (admin panel) | Tidak | Ya | Tidak | Ya | Ya |
| Lihat, cari, saring, ekspor pendaftar; ubah 3 status; unduh KK | Tidak | Tidak | Ya | Ya | Ya |
| Kelola konten landing dan pengaturan situs | Tidak | Tidak | Tidak | Ya | Ya |
| Kelola pengguna | Tidak | Tidak | Tidak | Ya | Ya |
| Atur izin per role | Tidak | Tidak | Tidak | Tidak | Ya |

Catatan: spec hanya menetapkan cakupan per modul dan bahwa izin dapat diatur superadmin. Rincian izin per aksi (lihat, ubah, hapus) berada di data izin yang diatur superadmin, dengan nilai awal mengikuti tabel ini.

## 3. Fitur, user story, dan kriteria penerimaan (spec 4)

### 3.1 Landing page (modul Landing, spec 4.1)

Bagian: hero, video profil, sejarah, kegiatan unggulan, proses belajar mengajar, berita terbaru, ajakan mendaftar, unduh PDF biaya, footer/kontak.

User story
- Sebagai pengunjung, saya ingin melihat video profil, sejarah, kegiatan, dan proses belajar agar yakin pada pondok ini.
- Sebagai pengunjung, saya ingin mengunduh PDF biaya dan langsung menemukan tombol daftar.
- Sebagai admin, saya ingin mengisi teks dan gambar tiap bagian, mengubah urutan, dan menyembunyikan bagian tanpa menyentuh kode.
- Sebagai admin, saya ingin mengatur identitas situs (nama, logo, kontak, tautan sosial, nomor WhatsApp panitia).

Kriteria penerimaan
- Semua bagian di atas tampil di halaman beranda sesuai urutan yang diatur admin.
- Bagian yang disembunyikan tidak tampil di publik.
- Perubahan teks, gambar, urutan, dan identitas situs dari admin panel langsung tercermin di publik.
- Bagian berita terbaru menampilkan berita berstatus terbit; bila modul Berita tidak aktif, bagian ini kosong tanpa galat.
- Tautan unduh PDF biaya memberi file yang diunggah admin.
- Tidak ada lorem ipsum atau stok foto generik di versi rilis.

### 3.2 Berita (modul Berita, spec 4.2)

Data: judul, slug, gambar sampul, isi (rich text), kategori, status draf/terbit, tanggal terbit, penulis.

User story
- Sebagai penulis berita, saya ingin menulis, menyimpan sebagai draf, dan menerbitkan berita.
- Sebagai pengunjung, saya ingin melihat daftar berita dan membuka halaman detail.
- Sebagai admin, saya ingin mengelola kategori berita.

Kriteria penerimaan
- Berita draf tidak tampil di publik; berita terbit tampil di daftar dan detail.
- Halaman daftar dan detail dapat diakses tanpa login dan nyaman di HP.
- Penulis berita hanya melihat modul berita di admin panel.
- Tidak ada fitur komentar.

### 3.3 Penerimaan santri baru (modul Pendaftaran, spec 4.3)

Alur wali
```
Buka website -> (opsional) unduh PDF biaya
-> isi formulir 4 langkah -> kirim
-> data tersimpan -> diarahkan ke WhatsApp panitia
   dengan pesan terisi otomatis
-> panitia menindaklanjuti lewat WhatsApp
```

Formulir 4 langkah
1. Data calon santri: NIK, nama lengkap (sesuai KK), tempat lahir, tanggal lahir, alamat (jalan/dusun, RT, RW, desa, kecamatan, kabupaten, provinsi).
2. Data akademik: pernah mondok (ya/tidak); jika ya, muncul nama pondok sebelumnya dan pendidikan pondok sebelumnya. Pendidikan terakhir, jenjang formal yang dituju, kelas.
3. Data orang tua: nama lengkap ayah dan ibu, alamat, pekerjaan, nomor HP masing-masing. Jika kedua orang tua sudah meninggal, wali memilih "Wali" dan mengisi data wali sebagai pengganti.
4. Upload kartu keluarga.

User story
- Sebagai wali, saya ingin mengisi formulir bertahap dari HP dengan indikator langkah dan pesan galat yang jelas.
- Sebagai wali, setelah kirim saya ingin diarahkan ke WhatsApp panitia dengan pesan yang sudah terisi.
- Sebagai wali yang orang tuanya sudah meninggal, saya ingin mengisi data wali sebagai pengganti.
- Sebagai panitia, saya ingin mencari dan menyaring pendaftar, mengekspor Excel dan PDF, dan mencentang status.
- Sebagai panitia, saya ingin mengunduh kartu keluarga pendaftar lewat admin panel.
- Sebagai admin, saya ingin mengganti nomor WhatsApp tujuan, isi pesan, dan PDF biaya.
- Sebagai admin, saya ingin mengubah daftar pilihan (jenjang formal, kelas, pendidikan terakhir, pekerjaan orang tua) tanpa mengubah kode (usulan spec 9).

Kriteria penerimaan
- Formulir terdiri atas 4 langkah dengan indikator langkah; field "nama pondok sebelumnya" dan "pendidikan pondok sebelumnya" hanya muncul bila "pernah mondok" = ya.
- Pada langkah 3, pilihan "Wali" memungkinkan mengisi data wali sebagai pengganti orang tua.
- Bila ada isian yang salah, pesan galat berbahasa Indonesia tampil jelas dan data yang sudah diisi tidak hilang.
- Data tersimpan lebih dulu; baru setelah itu wali diarahkan ke WhatsApp. Nomor tujuan, isi pesan, dan PDF biaya berasal dari pengaturan admin.
- NIK yang sama tidak dapat didaftarkan dua kali (cek duplikat lewat hash).
- Daftar pendaftar mendukung pencarian (termasuk NIK) dan penyaringan; ekspor Excel dan ekspor PDF mengikuti penyaringan yang aktif.
- Tiga status terpisah dan dapat dicentang bebas: sudah dihubungi, sudah bayar, sudah masuk pesantren.
- File KK hanya bisa diunduh lewat route yang mengecek login dan izin; tidak ada URL publik.
- Semua pendaftar diterima; tidak ada alur tes, wawancara, atau pengumuman.

## 4. Kebutuhan non-fungsional

### 4.1 Keamanan dan privasi (spec 5, wajib)

| Data | Perlindungan |
|---|---|
| NIK santri | Dienkripsi di database (enkripsi bawaan Laravel) + hash HMAC terpisah untuk cek duplikat dan pencarian NIK. Hash saja tidak cukup karena panitia harus bisa membaca NIK. |
| File kartu keluarga | Disk privat, dienkripsi, nama file diacak, tanpa URL publik; diunduh hanya lewat route yang mengecek login dan izin. |
| Data lain | Tidak dienkripsi, hanya tampil untuk role yang berhak. |

Konsekuensi: `APP_KEY` harus dicadangkan aman; bila hilang, NIK dan KK tidak bisa dibuka.

Kriteria penerimaan
- `composer audit` dan audit dependensi dijalankan sebelum rilis; build gagal bila ada kerentanan. Hanya versi PHP, Laravel, dan paket yang masih mendapat dukungan keamanan.
- Upload divalidasi ketat: jenis, ukuran, dan isi file, bukan hanya ekstensi.
- Rate limiting pada formulir pendaftaran dan login admin, ditambah Cloudflare Turnstile.
- Header keamanan (CSP, HSTS dan sejenisnya); `APP_DEBUG=false` di produksi.
- Log aktivitas admin, termasuk siapa melihat dan mengekspor data pendaftar.
- Cadangan berkala database dan file, terenkripsi.
- Tidak ada klaim "lolos semua"; yang dijanjikan adalah daftar periksa ini diuji otomatis dan hasilnya dilaporkan.

### 4.2 Mobile first dan pengalaman pengguna (spec 6)

- Dirancang untuk HP dulu, lalu diperlebar ke desktop. Area sentuh minimal 44px.
- Arah visual pilihan pemilik: Gaya B (hijau segar modern) dengan aksen emas hemat. Latar putih bersih, hijau lembut, kartu bersudut bulat. Logo disediakan pemilik.
- Tidak generik: tata letak tidak serba rata tengah, ritme antarbagian bervariasi, ruang kosong cukup; tanpa emoji sebagai ikon, gradien ungu, blob, atau efek kaca berlebihan; foto dan video asli pondok.
- Kontras teks memenuhi WCAG AA. Hanya mode terang pada tahap awal.
- Bahasa antarmuka: Indonesia.
- Pemilihan final font, ikon, dan token warna diserahkan ke builder (spec 9); usulan spec: Fraunces, Figtree atau Public Sans, Amiri atau Noto Naskh Arabic, Lucide, dihosting sendiri.

### 4.3 Performa

Spec tidak menetapkan angka performa. Arahan dari spec: font dihosting sendiri demi kecepatan, tampilan publik ringan (Blade + Tailwind), ekspor Excel/PDF berjalan langsung tanpa antrean. Target terukur ditetapkan builder dan dicatat di sini bila sudah disepakati.

### 4.4 Arsitektur dan operasional (spec 7, 8)

- Pendekatan B: Filament + modul buatan sendiri yang ringan, dipilih pemilik. Modul tidak saling memanggil kecuali lewat kontrak (interface/event) di inti. Menambah modul tidak mengubah modul lain.
- Hosting: server lokal aaPanel lewat Cloudflare Zero Trust Tunnel, dapat pindah ke VPS.
- Item "keputusan saya" (usulan, dapat diubah): Filament, Spatie Permission + Filament Shield, Blade + Tailwind, Livewire atau Alpine, ekspor tanpa antrean, struktur `Modules/<Nama>`.

## 5. Diserahkan ke builder (spec 9)

Versi PHP/Laravel/Filament yang masih didukung keamanannya dan bukti `composer audit`; bentuk struktur modul dan kontrak (di `CLAUDE.md`); data master pilihan; aturan validasi (NIK 16 digit, nomor HP Indonesia, batas ukuran dan jenis file KK) dan pilihan data wilayah (dropdown bertingkat atau teks); pembuatan PDF/Excel dan isi pesan WhatsApp default; strategi cadangan dan pemulihan; pipeline CI; font, ikon, token warna.

## 6. Yang perlu disiapkan pemilik (spec 10)

File logo, video profil, foto kegiatan, teks sejarah, PDF rincian biaya, dan nomor WhatsApp panitia. Tidak ada pertanyaan terbuka yang menahan perencanaan.
