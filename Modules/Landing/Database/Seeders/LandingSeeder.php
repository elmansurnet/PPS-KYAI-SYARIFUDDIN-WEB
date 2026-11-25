<?php

namespace Modules\Landing\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Landing\Models\LandingSection;
use Modules\Core\Support\DefaultPermissions;
use Spatie\Permission\PermissionRegistrar;

/**
 * Struktur awal beranda dan izin Landing. Aman dijalankan ulang: bagian yang sudah ada
 * tidak ditimpa (firstOrCreate; bagian tidak dapat dihapus dari panel), dan izin bawaan diberikan ke
 * admin SEKALI saja (DefaultPermissions::grantOnce): izin yang dicabut superadmin tidak diberikan lagi.
 *
 * Bagian baru dibuat tersembunyi sampai admin mengisinya, kecuali hero dan ajakan_daftar
 * yang punya teks netral cadangan di tampilan (nama situs, tautan ke /daftar).
 * Isi sengaja kosong: tidak ada sejarah, tanggal, atau angka karangan.
 */
class LandingSeeder extends Seeder
{
    public const PERMISSIONS = [
        'ViewAny:LandingSection', 'View:LandingSection', 'Update:LandingSection', 'Reorder:LandingSection',
    ];

    public function run(): void
    {
        $sections = [
            ['key' => 'hero', 'title' => null, 'body' => 'Kenali pondok kami, lalu daftarkan santri baru langsung dari website ini.', 'is_visible' => true],
            ['key' => 'video_profil', 'title' => 'Mengenal Pondok Kami', 'is_visible' => false],
            ['key' => 'sejarah', 'title' => 'Sejarah Pondok', 'is_visible' => false],
            ['key' => 'kegiatan_unggulan', 'title' => 'Kegiatan Unggulan', 'is_visible' => false],
            ['key' => 'proses_belajar', 'title' => 'Proses Belajar', 'is_visible' => false],
            ['key' => 'ajakan_daftar', 'title' => null, 'body' => null, 'is_visible' => true],
        ];

        foreach ($sections as $index => $attributes) {
            LandingSection::firstOrCreate(
                ['key' => $attributes['key']],
                $attributes + ['sort_order' => ($index + 1) * 10],
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DefaultPermissions::ensure(self::PERMISSIONS);

        // Hanya admin; penulis_berita dan panitia_pendaftaran tidak menerima izin Landing.
        DefaultPermissions::grantOnce('admin', self::PERMISSIONS);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
