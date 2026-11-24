<?php

namespace Modules\Landing\Http\Controllers;

use Illuminate\Contracts\View\View;
use Modules\Core\Contracts\LatestNewsProvider;
use Modules\Landing\Models\LandingSection;

class HomeController
{
    private const NEWS_LIMIT = 3;

    public function __invoke(LatestNewsProvider $news): View
    {
        $sections = LandingSection::visible()->get()
            ->filter(fn (LandingSection $section) => $section->hasPublicContent());

        return view('landing::home', [
            'sections' => $sections,
            'news' => $news->latest(self::NEWS_LIMIT),
        ]);
    }
}
