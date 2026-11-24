<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tes tidak boleh melakukan panggilan HTTP nyata (mis. Turnstile); wajib Http::fake().
        Http::preventStrayRequests();
    }
}
