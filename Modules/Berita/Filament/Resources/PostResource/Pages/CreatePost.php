<?php

namespace Modules\Berita\Filament\Resources\PostResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Modules\Berita\Filament\Resources\PostResource;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    // Penulis selalu akun yang sedang masuk, tidak pernah dari input.
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['author_id'] = Auth::id();

        return $data;
    }
}
