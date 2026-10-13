<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The Settings page is hidden on a live site; tests open it unless they test the hiding.
        config(['shop.settings_access' => true]);
    }
}
