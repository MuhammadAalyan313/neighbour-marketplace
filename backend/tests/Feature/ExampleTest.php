<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_api_only_application_returns_404_for_homepage(): void
    {
        $response = $this->get('/');

        $response->assertNotFound();
    }
}
