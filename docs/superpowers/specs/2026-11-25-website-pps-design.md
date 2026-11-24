# Desain Website PPs Kyai Syarifuddin

Tanggal: 2026-11-25. Status: menunggu persetujuan.
Catatan: folder proyek belum berupa repositori git, jadi dokumen ini belum di-commit.

## 1. Maksud dan alasan

Wali santri perlu (1) mengenal profil pondok, (2) mengikuti berita terbaru, dan (3) mendaftarkan anaknya secara online. Kesan yang diinginkan: profesional, modern, agamis. Wali yang tertarik dari profil pondok harus bisa mendaftar dengan mudah dari HP.

Proyek ini jangka panjang. Sekarang hanya tiga fitur, tetapi fitur baru harus bisa ditambah nanti tanpa mengganggu fitur yang sudah jalan.

## 2. Tujuan, bukan-tujuan, anti-tujuan

**Tujuan**
- Landing page yang meyakinkan: video profil, sejarah, kegiatan unggulan, proses belajar mengajar.
- Berita yang mudah diterbitkan oleh penulis berita.
- Pendaftaran santri baru online yang nyaman di HP, data tersimpan rapi, dan wali diarahkan ke WhatsApp panitia.
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

## 3. Pengguna dan role

- **Pengunjung / calon wali santri:** membaca profil dan berita, mengunduh PDF biaya, mengisi formulir. Tanpa login.
- **Superadmin:** semua akses, ditambah mengatur izin tiap role.
- **Admin:** semua fitur, tanpa mengatur izin.
- **Penulis berita:** hanya modul berita.
- **Panitia pendaftaran:** hanya modul pendaftar.

Izin tiap role bisa diatur superadmin dari admin panel (bukan hardcode).

## 4. Fitur

### 4.1 Landing page (modul Landing)
Bagian: hero, video profil, sejarah berdirinya pondok, kegiatan unggulan, proses belajar mengajar, berita terbaru, ajakan mendaftar, unduh PDF biaya, footer/kontak. Admin bisa mengisi teks dan gambar tiap bagian, mengubah urutan, dan menyembunyikan bagian. Admin juga mengatur identitas situs (nama, logo, kontak, tautan sosial, nomor WhatsApp panitia).

### 4.2 Berita (modul Berita)
Judul, slug, gambar sampul, isi (rich text), kategori, status draf/terbit, tanggal terbit, penulis. Halaman daftar berita dan halaman detail untuk publik. Belum ada komentar.

### 4.3 Penerimaan santri baru (modul Pendaftaran)

Alur wali:
```
Buka website -> (opsional) unduh PDF biaya
-> isi formulir 4 langkah -> kirim
-> data tersimpan -> diarahkan ke WhatsApp panitia
   dengan pesan terisi otomatis
-> panitia menindaklanjuti lewat WhatsApp
```

Formulir 4 langkah:
1. **Data calon santri:** NIK, nama lengkap (sesuai KK), tempat lahir, tanggal lahir, alamat (jalan/dusun, RT, RW, desa, kecamatan, kabupaten, provinsi).
2. **Data akademik:** pernah mondok (ya/tidak). Jika ya, muncul nama pondok sebelumnya dan pendidikan pondok sebelumnya. Pendidikan terakhir, jenjang formal yang dituju, kelas.
3. **Data orang tua:** nama lengkap ayah dan ibu, alamat, pekerjaan, nomor HP masing-masing. Jika kedua orang tua sudah meninggal, wali memilih "Wali" dan mengisi data wali sebagai pengganti.
4. **Upload kartu keluarga.**

Setelah kirim, data tersimpan lebih dulu, baru wali diarahkan ke WhatsApp. Nomor tujuan, isi pesan, dan PDF biaya bisa diganti admin.

Sisi admin/panitia: daftar pendaftar dengan pencarian, penyaringan, ekspor Excel, dan ekspor PDF (mengikuti penyaringan aktif). Tiga status terpisah dan bisa dicentang bebas: **sudah dihubungi**, **sudah bayar**, **sudah masuk pesantren**.

## 5. Keamanan dan privasi (syarat wajib)

| Data | Perlindungan |
|---|---|
| NIK santri | Dienkripsi di database (enkripsi bawaan Laravel) + hash HMAC terpisah untuk cek duplikat dan pencarian NIK. Hash saja tidak cukup karena panitia harus bisa membaca NIK. |
| File kartu keluarga | Disimpan di disk privat, dienkripsi, nama file diacak, tidak punya URL publik. Hanya diunduh lewat route yang mengecek login dan izin. |
| Data lain | Tidak dienkripsi, tetapi hanya tampil untuk role yang berhak. |

Konsekuensi: `APP_KEY` harus dicadangkan aman. Bila hilang, NIK dan KK tidak bisa dibuka.

Syarat lain:
- `composer audit` dan audit dependensi sebelum rilis, build gagal bila ada kerentanan. Hanya versi PHP, Laravel, dan paket yang masih mendapat dukungan keamanan.
- Validasi upload ketat: jenis, ukuran, dan isi file (bukan hanya ekstensi).
- Rate limiting pada formulir pendaftaran dan login admin, plus Cloudflare Turnstile.
- Header keamanan (CSP, HSTS dan sejenisnya), `APP_DEBUG=false` di produksi.
- Log aktivitas admin, termasuk siapa melihat dan mengekspor data pendaftar.
- Cadangan berkala database dan file, terenkripsi.
- Tidak ada klaim "lolos semua". Yang dijanjikan adalah daftar periksa ini diuji otomatis dan hasilnya dilaporkan.

## 6. Tampilan dan pengalaman pengguna

Arah yang dipilih pemilik: **Gaya B (hijau segar modern) dengan aksen emas**. Latar putih bersih, hijau lembut, kartu bersudut bulat, aksen emas dipakai hemat (misalnya garis, ikon, dan tombol sekunder). **Mobile first**: dirancang untuk HP dulu, lalu diperlebar ke desktop. Logo pondok sudah ada dan akan disediakan pemilik.

Pedoman agar tidak terlihat generik (usulan saya, bagian yang diserahkan ke builder untuk dimatangkan):
- Font: judul memakai serif lembut yang hangat (usulan: Fraunces), isi memakai sans yang nyaman dibaca di HP (usulan: Figtree atau Public Sans), teks Arab memakai Amiri atau Noto Naskh Arabic. Dihosting sendiri (bukan CDN) demi kecepatan dan CSP. Hindari kombinasi default seperti Inter + Poppins.
- Warna dalam token (hijau utama, hijau lembut, emas, netral hangat), emas tidak dominan, kontras teks memenuhi WCAG AA.
- Satu set ikon yang konsisten (usulan: Lucide). Tidak ada emoji sebagai ikon, tidak ada gradien ungu, blob, atau efek kaca berlebihan.
- Foto dan video asli pondok. Tidak boleh ada teks lorem ipsum atau stok foto generik di versi rilis.
- Tata letak tidak serba rata tengah. Variasi ritme antarbagian, ruang kosong yang cukup, area sentuh minimal 44px.
- Formulir bertahap dengan indikator langkah, pesan galat yang jelas dalam bahasa Indonesia, dan data tidak hilang saat salah isi.
- Ada mode terang saja pada tahap awal (mode gelap bukan tujuan).
- Bahasa antarmuka: Indonesia.

## 7. Pendekatan

Tiga cara yang dipertimbangkan:
- **A. Filament + paket modul siap pakai (nwidart/laravel-modules).** Struktur baku, tetapi risiko tertinggal saat upgrade Laravel/Filament.
- **B. Filament + modul buatan sendiri yang ringan.** Dipilih. Tidak bergantung paket pihak ketiga, polanya mudah dipelajari. Aturan modul harus tegas di CLAUDE.md.
- **C. Admin dari nol.** Kendali penuh tapi jauh lebih lama dan rawan bug (pencarian, penyaringan, ekspor, manajemen role dibangun sendiri).

**Dipilih: B**, dengan alasan kebutuhan jangka panjang dan modul yang tidak saling mengganggu.

## 8. Keputusan teknis

Diputuskan pemilik:
- Laravel, admin panel untuk semua pengaturan, modular, mobile first.
- Pendekatan B.
- Hosting: server lokal dengan aaPanel yang dipublikasikan lewat Cloudflare Zero Trust Tunnel, dapat pindah ke VPS. Pemilik menilai pilihan ini tidak memengaruhi fitur.

Keputusan saya (dapat diubah, bukan hasil kesepakatan):
- Admin panel: Filament. Role/izin: Spatie Permission + Filament Shield.
- Tampilan publik: Blade + Tailwind CSS. Wizard pendaftaran: Livewire atau Alpine.
- Ekspor Excel dan PDF dijalankan langsung tanpa antrean (queue).
- Struktur: `Modules/<Nama>` berisi service provider, migrasi, route, tampilan publik, dan Filament plugin sendiri. Modul tidak boleh saling memanggil kecuali lewat kontrak (interface/event) yang didaftarkan di inti.

## 9. Diserahkan ke builder

- Versi tepat PHP, Laravel, Filament (pilih yang masih didukung keamanannya saat dibangun) dan pembuktiannya lewat `composer audit`.
- Bentuk teknis struktur modul, kontrak antarmodul, dan aturan penamaan, lalu dituliskan di CLAUDE.md.
- Daftar nilai pilihan: jenjang formal, kelas, pendidikan terakhir, pekerjaan orang tua, kategori berita. Usulan: disimpan sebagai data master yang bisa diubah admin.
- Aturan validasi (NIK 16 digit, format nomor HP Indonesia, batas ukuran upload, jenis file KK) dan data wilayah (provinsi sampai desa), apakah memakai dropdown bertingkat atau isian teks.
- Pembuatan PDF/Excel, tampilan dan isi PDF ekspor, serta isi pesan WhatsApp default.
- Strategi cadangan dan prosedur pemulihan, pipeline CI, serta struktur CLAUDE.md dan PRD.
- Pemilihan final font, ikon, dan token warna, selaras dengan logo.

## 10. Hal yang perlu dipastikan pemilik

Tidak ada pertanyaan terbuka yang menahan perencanaan. Yang perlu disiapkan: file logo, video profil, foto kegiatan, teks sejarah, PDF rincian biaya, dan nomor WhatsApp panitia.
