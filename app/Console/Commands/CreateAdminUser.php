<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * Cria o primeiro administrador de forma segura (sem senha fixa no código).
 *
 * - Interativo: pergunta a senha (entrada oculta).
 * - --generate: gera uma senha aleatória, grava em um arquivo local fora do Git
 *   (storage/app/private) e exige a troca no primeiro acesso. A senha N�O � impressa.
 */
class CreateAdminUser extends Command
{
    protected $signature = 'library:create-admin
        {--name= : Nome do administrador}
        {--email= : E-mail de acesso}
        {--generate : Gera senha aleatória e a salva em arquivo local (troca obrigatória no 1º acesso)}';

    protected $description = 'Cria um usuário Administrador de forma segura';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Nome do administrador');
        $email = $this->option('email') ?: $this->ask('E-mail de acesso');

        $validator = Validator::make(['name' => $name, 'email' => $email], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $generated = (bool) $this->option('generate');

        if ($generated) {
            $password = Str::password(16, symbols: false);
        } else {
            $password = $this->secret('Senha (mín. 10 caracteres, com letras e números)');
            $confirm = $this->secret('Repita a senha');
            if ($password !== $confirm) {
                $this->error('As senhas não conferem.');

                return self::FAILURE;
            }
            $check = Validator::make(['p' => $password], ['p' => [PasswordRule::min(10)->letters()->numbers()]]);
            if ($check->fails()) {
                $this->error('Senha fraca: use ao menos 10 caracteres, com letras e números.');

                return self::FAILURE;
            }
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => UserRole::Admin,
            'active' => true,
            'must_change_password' => $generated,
        ]);

        $this->info("Administrador criado: {$email}");

        if ($generated) {
            $path = storage_path('app/private/first-admin-credentials.txt');
            File::ensureDirectoryExists(dirname($path));
            File::put($path, "E-mail: {$email}\nSenha temporária: {$password}\n\nTroque a senha no primeiro acesso e APAGUE este arquivo.\n");
            @chmod($path, 0600);
            $this->warn("Senha temporária salva em: {$path}");
            $this->line('Ela não é exibida aqui. Será exigida a troca no primeiro acesso; depois apague o arquivo.');
        }

        return self::SUCCESS;
    }
}
