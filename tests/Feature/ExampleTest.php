<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_page_is_accessible()
    {
        $response = $this->get('/');
        $response->assertStatus(200);
    }

    public function test_superadmin_login_page_is_accessible()
    {
        $response = $this->get('/superadmin/login');
        $response->assertStatus(200);
    }
}
