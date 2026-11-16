<?php

namespace App\Enums;

/** Perfis do sistema e suas permissões (matriz em docs/arquitetura/08-erros-permissoes.md). */
enum UserRole: string
{
    case Admin = 'admin';
    case Librarian = 'librarian';
    case Assistant = 'assistant';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Librarian => 'Bibliotecário',
            self::Assistant => 'Auxiliar de circulação',
            self::Viewer => 'Consulta',
        };
    }

    /** @return list<string> */
    public function abilities(): array
    {
        $viewer = ['catalog.view'];

        $assistant = [...$viewer, 'dashboard.view', 'copy.view', 'borrower.view',
            'loan.view', 'loan.create', 'loan.return', 'loan.renew',
            'reservation.view', 'reservation.create', 'reservation.cancel'];

        $librarian = [...$assistant, 'catalog.create', 'catalog.update', 'catalog.archive',
            'category.manage', 'location.manage', 'copy.create', 'copy.update', 'copy.transition',
            'borrower.contacts', 'borrower.create', 'borrower.update', 'borrower.status',
            'report.view', 'report.export', 'audit.view'];

        $admin = [...$librarian, 'catalog.delete', 'copy.restore', 'loan.void',
            'borrower.anonymize', 'user.manage', 'settings.manage', 'audit.view-all'];

        return match ($this) {
            self::Admin => $admin,
            self::Librarian => $librarian,
            self::Assistant => $assistant,
            self::Viewer => $viewer,
        };
    }

    public function can(string $ability): bool
    {
        return in_array($ability, $this->abilities(), true);
    }

    /** @return list<string> todas as permissões existentes */
    public static function allAbilities(): array
    {
        return self::Admin->abilities();
    }
}
