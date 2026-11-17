<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password as PasswordRule;

class ResetUserPassword extends Command
{
    protected $signature = 'library:reset-password {email : E-mail do usuário}';

    protected $description = 'Redefine com segurança a senha de um usuário existente';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user) {
            $this->error('Nenhum usuário foi encontrado com esse e-mail.');

            return self::FAILURE;
        }

        $password = $this->secret('Nova senha (mín. 10 caracteres, com letras e números)');
        $confirmation = $this->secret('Repita a nova senha');

        if ($password !== $confirmation) {
            $this->error('As senhas não conferem. Nenhuma alteração foi realizada.');

            return self::FAILURE;
        }

        $validator = Validator::make(
            ['password' => $password],
            ['password' => ['required', PasswordRule::min(10)->letters()->numbers()]],
        );

        if ($validator->fails()) {
            $this->error('Senha fraca: use ao menos 10 caracteres, com letras e números.');

            return self::FAILURE;
        }

        $user->forceFill([
            'password' => $password,
            'remember_token' => null,
            'must_change_password' => false,
        ])->save();

        $this->info("Senha redefinida para {$user->email}.");
        $this->line('A senha não foi exibida nem gravada em logs.');

        return self::SUCCESS;
    }
}
