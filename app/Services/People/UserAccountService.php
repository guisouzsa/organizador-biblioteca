<?php

namespace App\Services\People;

use App\Enums\ErrorCode;
use App\Enums\UserRole;
use App\Exceptions\Domain\DomainException;
use App\Models\User;
use App\Services\Admin\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Contas do sistema. Contas nunca são excluídas, apenas desativadas (RN-PES-06). */
class UserAccountService
{
    public function __construct(private AuditLogger $audit) {}

    /** @return array{0: User, 1: string} usuário e senha temporária (exibida uma única vez) */
    public function create(string $name, string $email, UserRole $role): array
    {
        $password = Str::password(14, symbols: false);

        $user = DB::transaction(function () use ($name, $email, $role, $password) {
            $user = User::create([
                'name' => $name, 'email' => $email, 'password' => $password, 'role' => $role,
                'active' => true, 'must_change_password' => true,
            ]);
            $this->audit->record('user.created', $user, [], ['email' => $email, 'role' => $role->value]);

            return $user;
        });

        return [$user, $password];
    }

    public function update(User $user, string $name, UserRole $role, bool $active): User
    {
        return DB::transaction(function () use ($user, $name, $role, $active) {
            // Trava os administradores ativos para a verificação do último admin ser consistente.
            $admins = User::where('role', UserRole::Admin->value)->where('active', true)->lockForUpdate()->pluck('id');
            $isLastAdmin = $user->isAdmin() && $user->active && $admins->count() <= 1 && $admins->contains($user->id);

            if ($isLastAdmin && ($role !== UserRole::Admin || ! $active)) {
                throw DomainException::of(ErrorCode::UltimoAdmin);
            }

            $before = ['name' => $user->name, 'role' => $user->role->value, 'active' => $user->active];
            $user->update(['name' => $name, 'role' => $role, 'active' => $active]);
            [$old, $new] = AuditLogger::diff($before, ['name' => $name, 'role' => $role->value, 'active' => $active]);

            if ($old || $new) {
                $this->audit->record('user.updated', $user, $old, $new);
            }

            return $user;
        });
    }

    /** @return string nova senha temporária (exibida uma única vez) */
    public function resetPassword(User $user): string
    {
        $password = Str::password(14, symbols: false);

        DB::transaction(function () use ($user, $password) {
            $user->forceFill(['password' => $password, 'must_change_password' => true, 'remember_token' => Str::random(60)])->save();
            $this->audit->record('user.password_reset', $user);
        });

        return $password;
    }
}
