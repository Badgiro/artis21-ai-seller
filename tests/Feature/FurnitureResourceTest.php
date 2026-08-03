<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FurnitureResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_open_furniture_resource(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/furniture')
            ->assertOk();
    }
}
