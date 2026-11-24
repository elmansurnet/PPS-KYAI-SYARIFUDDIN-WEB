<?php

namespace Modules\Pendaftaran\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use Modules\Pendaftaran\Database\Factories\ApplicantFactory;
use Modules\Pendaftaran\Support\NikHasher;
use Stringable;

/**
 * Calon santri. Kelas disimpan sebagai teks (label KelasOption saat mendaftar) supaya riwayat
 * tidak berubah bila data master diedit atau dihapus; jenjang dan pendidikan terakhir memakai FK
 * (restrict: master yang dipakai tidak bisa dihapus, cukup dinonaktifkan).
 *
 * Keamanan: nik terenkripsi (cast encrypted), nik_hash HMAC diisi otomatis saat nik di-set dan
 * tidak bisa diisi massal. Bendera status dan kk_path tidak ada di $fillable: ubah lewat forceFill
 * atau setter eksplisit di kode admin.
 *
 * ATURAN KODE PENDAFTARAN (lihat Modules/Pendaftaran/README.md):
 * - Cek dulu dengan Applicant::findByNik(), LALU tangkap pelanggaran unik lewat
 *   Applicant::isDuplicateNikViolation(). Jangan pernah mencatat atau menampilkan pesan QueryException:
 *   pesannya memuat semua binding (nama, tanggal lahir, alamat, HP orang tua, NIK terenkripsi).
 * - Jalur yang melewati Eloquent (insert/upsert/DB::table/replicate/insertOrIgnore) tidak menjalankan
 *   logika nik_hash. Selalu lewat instance model.
 *
 * @property string $nik
 * @property string $nik_hash
 * @property string $nama_lengkap
 * @property bool $sudah_dihubungi
 * @property bool $sudah_bayar
 * @property bool $sudah_masuk
 */
class Applicant extends Model
{
    /** @use HasFactory<ApplicantFactory> */
    use HasFactory;

    protected $fillable = [
        'nik', 'nama_lengkap', 'tempat_lahir', 'tanggal_lahir',
        'jalan_dusun', 'rt', 'rw', 'desa', 'kecamatan', 'kabupaten', 'provinsi',
        'pernah_mondok', 'pondok_sebelumnya', 'pendidikan_pondok_sebelumnya',
        'pendidikan_terakhir_id', 'jenjang_id', 'kelas',
        'ayah_nama', 'ibu_nama', 'ayah_pekerjaan', 'ibu_pekerjaan', 'ayah_hp', 'ibu_hp',
        'ortu_jalan_dusun', 'ortu_rt', 'ortu_rw', 'ortu_desa', 'ortu_kecamatan', 'ortu_kabupaten', 'ortu_provinsi',
        'orang_tua_meninggal',
        'wali_nama', 'wali_pekerjaan', 'wali_hp',
        'wali_jalan_dusun', 'wali_rt', 'wali_rw', 'wali_desa', 'wali_kecamatan', 'wali_kabupaten', 'wali_provinsi',
    ];

    protected $hidden = ['nik', 'nik_hash', 'kk_path'];

    protected function casts(): array
    {
        return [
            'nik' => 'encrypted',
            'tanggal_lahir' => 'date',
            'pernah_mondok' => 'boolean',
            'orang_tua_meninggal' => 'boolean',
            'sudah_dihubungi' => 'boolean',
            'sudah_bayar' => 'boolean',
            'sudah_masuk' => 'boolean',
        ];
    }

    protected static function newFactory(): Factory
    {
        return ApplicantFactory::new();
    }

    /**
     * Semua jalur penetapan (fill, update, forceFill, properti) lewat sini: NIK disimpan sebagai
     * digit saja (terenkripsi) dan nik_hash selalu mengikuti.
     */
    public function setAttribute($key, $value)
    {
        if ($key === 'nik') {
            $value = self::cleanNik($value);
            parent::setAttribute('nik_hash', NikHasher::hash($value));
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * Normalisasi dan validasi NIK: hanya string, int, atau Stringable; hasil harus tepat 16 digit.
     * Pesan galat sengaja tidak memuat nilainya (bisa data pribadi).
     */
    private static function cleanNik(mixed $nik): string
    {
        if (! (is_string($nik) || is_int($nik) || $nik instanceof Stringable)) {
            throw new InvalidArgumentException('NIK harus berupa teks atau angka.');
        }

        $clean = NikHasher::normalize((string) $nik);

        if (! preg_match('/^\d{16}$/', $clean)) {
            throw new InvalidArgumentException('NIK harus tepat 16 digit.');
        }

        return $clean;
    }

    public static function findByNik(string $nik): ?self
    {
        return static::query()->where('nik_hash', NikHasher::hash(self::cleanNik($nik)))->first();
    }

    /**
     * True bila pengecualian adalah pelanggaran unik pada nik_hash (NIK sudah terdaftar).
     * Memeriksa pesan driver (errorInfo), bukan pesan QueryException yang memuat seluruh binding (data pribadi).
     */
    public static function isDuplicateNikViolation(QueryException $e): bool
    {
        $driverMessage = (string) ($e->errorInfo[2] ?? '');

        return (string) ($e->errorInfo[0] ?? $e->getCode()) === '23000'
            && str_contains($driverMessage, 'nik_hash')
            && (stripos($driverMessage, 'unique') !== false || stripos($driverMessage, 'duplicate') !== false);
    }

    public function nikLast4(): string
    {
        return substr((string) $this->nik, -4);
    }

    public function nikMasked(): string
    {
        $nik = (string) $this->nik;

        return str_repeat('*', max(strlen($nik) - 4, 0)).substr($nik, -4);
    }

    /** @return BelongsTo<Jenjang, $this> */
    public function jenjang(): BelongsTo
    {
        return $this->belongsTo(Jenjang::class);
    }

    /** @return BelongsTo<PendidikanTerakhir, $this> */
    public function pendidikanTerakhir(): BelongsTo
    {
        return $this->belongsTo(PendidikanTerakhir::class);
    }
}
