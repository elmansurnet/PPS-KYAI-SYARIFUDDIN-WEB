<?php

namespace Modules\Core\Support;

use Illuminate\Database\Eloquent\Model;

class AuditLog
{
    /**
     * Catat aksi sensitif (mis. lihat/ekspor data pendaftar). Pelaku (causer) diambil
     * otomatis dari user yang sedang login. Jangan masukkan data sensitif ke $properties.
     *
     * @param  array<string, mixed>  $properties
     */
    public static function record(string $event, ?Model $subject = null, array $properties = []): void
    {
        $logger = activity();

        if ($user = auth()->user()) {
            $logger->causedBy($user);
        }

        if ($subject) {
            $logger->performedOn($subject);
        }

        $logger->event($event)->withProperties($properties)->log($event);
    }
}
