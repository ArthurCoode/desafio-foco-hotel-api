<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthenticationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        // O cast "hashed" do model User faz o hash da senha ao criar.
        $user = User::factory()->create([
            'password' => 'senha-segura-123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'senha-segura-123',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'message',
                'token',
                'token_type',
                'user' => ['id', 'name', 'email'],
            ])
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.name', $user->name)
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonMissingPath('user.password');

        // Verifica apenas que o token existe e não é vazio, sem checar o valor.
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_login_fails_with_invalid_password(): void
    {
        $user = User::factory()->create([
            'password' => 'senha-segura-123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'senha-incorreta',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Credenciais inválidas.')
            ->assertJsonMissingPath('token');
    }

    public function test_rooms_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/rooms')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_authenticated_user_can_access_rooms_endpoint(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/rooms')
            ->assertOk();
    }

    public function test_user_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/user')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_authenticated_user_can_access_user_endpoint(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('id', $user->id);
    }
}
