<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_aplikasi_mengembalikan_respon_sukses(): void
    {
        $response = $this->get('/api');

        $response->assertStatus(200);
    }
}
