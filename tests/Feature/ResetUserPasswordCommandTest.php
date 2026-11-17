<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResetUserPasswordCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resets_an_existing_user_password_without_echoing_it(): void
    {
        $user = User::factory()->admin()->create(['must_change_password' => true]);
        $newPassword = 'SenhaNova12345';

        $this->artisan('library:reset-password', ['email' => strtoupper($user->email)])
            ->expectsQuestion('Nova senha (mín. 10 caracteres, com letras e números)', $newPassword)
            ->expectsQuestion('Repita a nova senha', $newPassword)
            ->expectsOutput("Senha redefinida para {$user->email}.")
            ->assertSuccessful();

        $user->refresh();
        $this->assertTrue(Hash::check($newPassword, $user->password));
        $this->assertFalse($user->must_change_password);
        $this->assertNull($user->remember_token);
    }

    public function test_it_does_not_change_password_when_confirmation_differs(): void
    {
        $user = User::factory()->admin()->create();
        $oldHash = $user->password;

        $this->artisan('library:reset-password', ['email' => $user->email])
            ->expectsQuestion('Nova senha (mín. 10 caracteres, com letras e números)', 'SenhaNova12345')
            ->expectsQuestion('Repita a nova senha', 'OutraSenha12345')
            ->assertFailed();

        $this->assertSame($oldHash, $user->fresh()->password);
    }
}
