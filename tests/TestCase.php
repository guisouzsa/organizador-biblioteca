<?php

namespace Tests;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Autentica um usuário ativo com o perfil informado (Administrador por padrão). */
    protected function signIn(UserRole $role = UserRole::Admin): User
    {
        $user = User::factory()->role($role)->create();
        $this->actingAs($user);

        return $user;
    }
}
