<?php

namespace Modules\Core\Tests\Fixtures\Applicant;

use Filament\Resources\Resource;

/** Resource tiruan khusus tes; sengaja tidak didaftarkan ke panel. */
class FakeApplicantResource extends Resource
{
    protected static ?string $model = FakeApplicant::class;

    public static function getPages(): array
    {
        return [];
    }
}
