<?php

namespace Modules\Pendaftaran\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pilihan kelas milik satu jenjang. Pendaftar menyimpan labelnya sebagai teks (Applicant::kelas).
 *
 * @property int $jenjang_id
 * @property string $name
 * @property int $sort_order
 * @property bool $is_active
 */
class KelasOption extends Model
{
    protected $fillable = ['jenjang_id', 'name', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean'];
    }

    /** @return BelongsTo<Jenjang, $this> */
    public function jenjang(): BelongsTo
    {
        return $this->belongsTo(Jenjang::class);
    }
}
