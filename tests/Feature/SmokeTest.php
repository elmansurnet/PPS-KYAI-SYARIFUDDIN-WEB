<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

// Beranda memakai layout publik yang membaca SiteSettings dari database.
uses(RefreshDatabase::class);

test('test_homepage_returns_200', function () {
    $this->get('/')->assertStatus(200);
});
