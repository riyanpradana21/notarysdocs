<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test the root URL displays dashboard directly and login page is accessible.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);

        $rootResponse = $this->get('/');
        $rootResponse->assertStatus(200);
        $rootResponse->assertSee('Garis Draf Dinamis');
    }
}
