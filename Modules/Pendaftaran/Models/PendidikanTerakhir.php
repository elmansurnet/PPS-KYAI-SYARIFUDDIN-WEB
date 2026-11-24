<?php

namespace Modules\Pendaftaran\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $name
 * @property int $sort_order
 * @property bool $is_active
 */
class PendidikanTerakhir extends Model
{
    protected $table = 'pendidikan_terakhirs';

    protected $fillable = ['name', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean'];
    }

    /** @return HasMany<Applicant, $this> */
    public function applicants(): HasMany
    {
        return $this->hasMany(Applicant::class);
    }
}
