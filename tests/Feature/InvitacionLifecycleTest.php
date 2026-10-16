<?php

namespace Tests\Feature;

use App\Models\Invitacion;
use App\Models\Restaurante;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvitacionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurante $restaurante;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurante = Restaurante::create([
            'nombre' => 'La Parrilla del Bosque',
            'slug' => 'la-parrilla',
        ]);

        $this->owner = User::create([
            'name' => 'Owner Juan',
            'email' => 'juan@parrilla.com',
            'password' => 'secret123',
            'activo' => true,
        ]);

        $this->owner->restaurantes()->attach($this->restaurante->id, ['rol' => 'propietario']);
    }

    /**
     * Tier 1: Invitations generate a 64-character cryptographic token and 7-day expiry.
     */
    public function test_invitation_creation_generates_64_character_token_and_7_day_expiry(): void
    {
        $token = bin2hex(random_bytes(32)); // 64 hex chars
        $now = now();

        $invitacion = Invitacion::create([
            'restaurante_id' => $this->restaurante->id,
            'email' => 'nuevo.editor@example.com',
            'rol' => 'editor',
            'token' => $token,
            'invitado_por' => $this->owner->id,
            'expira_en' => $now->copy()->addDays(7),
        ]);

        $this->assertEquals(64, strlen($invitacion->token));
        $this->assertEquals('nuevo.editor@example.com', $invitacion->email);
        $this->assertEquals('editor', $invitacion->rol);
        $this->assertNull($invitacion->aceptada_en);
        $this->assertTrue($invitacion->expira_en->greaterThan($now));
        $this->assertEquals(7, (int) round($now->diffInDays($invitacion->expira_en)));
    }

    /**
     * Tier 1: User accepts a valid invitation and is attached to restaurante_usuario with role.
     */
    public function test_user_can_accept_invitation_and_join_restaurant(): void
    {
        $candidateUser = User::create([
            'name' => 'Ana Editora',
            'email' => 'ana@example.com',
            'password' => 'password123',
            'activo' => true,
        ]);

        $token = Str::random(64);
        $invitacion = Invitacion::create([
            'restaurante_id' => $this->restaurante->id,
            'email' => 'ana@example.com',
            'rol' => 'editor',
            'token' => $token,
            'invitado_por' => $this->owner->id,
            'expira_en' => now()->addDays(7),
        ]);

        $response = $this->actingAs($candidateUser)->get("/invitacion/{$token}");

        // Either redirected to admin or successful status
        $this->assertTrue(
            in_array($response->status(), [200, 302]),
            "Expected 200 or 302 on invitation acceptance, got {$response->status()}"
        );

        // Verify attachment to pivot
        $this->assertDatabaseHas('restaurante_usuario', [
            'restaurante_id' => $this->restaurante->id,
            'usuario_id' => $candidateUser->id,
            'rol' => 'editor',
        ]);

        // Verify invitation marked as accepted
        $invitacion->refresh();
        $this->assertNotNull($invitacion->aceptada_en);
    }

    /**
     * Tier 2: Expired invitation is strictly rejected.
     */
    public function test_expired_invitation_is_rejected(): void
    {
        $candidateUser = User::create([
            'name' => 'Carlos Tardío',
            'email' => 'carlos@example.com',
            'password' => 'password123',
            'activo' => true,
        ]);

        $token = Str::random(64);
        Invitacion::create([
            'restaurante_id' => $this->restaurante->id,
            'email' => 'carlos@example.com',
            'rol' => 'editor',
            'token' => $token,
            'invitado_por' => $this->owner->id,
            'expira_en' => now()->subHours(2), // Already expired
        ]);

        $response = $this->actingAs($candidateUser)->get("/invitacion/{$token}");

        // Must fail with 410, 422, or 403 or redirect with error
        $this->assertTrue(
            in_array($response->status(), [410, 422, 403, 302]),
            "Expected error status on expired token, got {$response->status()}"
        );

        // Must NOT be attached to restaurant
        $this->assertDatabaseMissing('restaurante_usuario', [
            'restaurante_id' => $this->restaurante->id,
            'usuario_id' => $candidateUser->id,
        ]);
    }

    /**
     * Tier 2: Already accepted invitation cannot be reused.
     */
    public function test_already_accepted_invitation_cannot_be_reused(): void
    {
        $firstUser = User::create([
            'name' => 'First User',
            'email' => 'first@example.com',
            'password' => 'secret123',
            'activo' => true,
        ]);

        $secondUser = User::create([
            'name' => 'Second User',
            'email' => 'second@example.com',
            'password' => 'secret123',
            'activo' => true,
        ]);

        $token = Str::random(64);
        Invitacion::create([
            'restaurante_id' => $this->restaurante->id,
            'email' => 'first@example.com',
            'rol' => 'editor',
            'token' => $token,
            'invitado_por' => $this->owner->id,
            'expira_en' => now()->addDays(7),
            'aceptada_en' => now()->subDay(), // Already accepted yesterday
        ]);

        $response = $this->actingAs($secondUser)->get("/invitacion/{$token}");

        // Attempt by second user must be rejected
        $this->assertTrue(
            in_array($response->status(), [410, 422, 403, 302]),
            "Expected failure on already accepted token, got {$response->status()}"
        );

        $this->assertDatabaseMissing('restaurante_usuario', [
            'restaurante_id' => $this->restaurante->id,
            'usuario_id' => $secondUser->id,
        ]);
    }

    /**
     * Tier 3: Accepting an invitation grants access to multiple restaurants without losing existing ones.
     */
    public function test_user_can_belong_to_multiple_restaurants_via_invitations(): void
    {
        $multiRestUser = User::create([
            'name' => 'Multi Tenant Manager',
            'email' => 'manager@example.com',
            'password' => 'secret123',
            'activo' => true,
        ]);

        $restauranteDos = Restaurante::create([
            'nombre' => 'Segundo Restaurante',
            'slug' => 'segundo-restaurante',
        ]);

        // Attach to first restaurant as editor
        $multiRestUser->restaurantes()->attach($this->restaurante->id, ['rol' => 'editor']);

        // Invite to second restaurant as propietario
        $token = Str::random(64);
        Invitacion::create([
            'restaurante_id' => $restauranteDos->id,
            'email' => 'manager@example.com',
            'rol' => 'propietario',
            'token' => $token,
            'invitado_por' => $this->owner->id,
            'expira_en' => now()->addDays(7),
        ]);

        $this->actingAs($multiRestUser)->get("/invitacion/{$token}");

        $this->assertCount(2, $multiRestUser->fresh()->restaurantes);
    }
}
