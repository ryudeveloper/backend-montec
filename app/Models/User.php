<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PortalArea;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'roles'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'roles' => 'array',
        ];
    }

    /**
     * Papéis válidos do usuário.
     *
     * Valor desconhecido na coluna (seed antigo, edição manual) é descartado em
     * vez de estourar — e descartar significa negar acesso, que é o lado seguro
     * para errar.
     *
     * @return list<UserRole>
     */
    public function roles(): array
    {
        $raw = $this->roles;

        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $value): ?UserRole => is_string($value) ? UserRole::tryFrom($value) : null,
            $raw,
        )));
    }

    public function hasRole(UserRole $role): bool
    {
        return in_array($role, $this->roles(), true);
    }

    /**
     * Mapa papel → área. Único lugar onde a sobreposição do administrador existe.
     */
    public function canAccess(PortalArea $area): bool
    {
        return match ($area) {
            PortalArea::Resumes => $this->canAccessResumes(),
            PortalArea::Reports => $this->canAccessReports(),
            PortalArea::Audit => $this->canAccessAudit(),
        };
    }

    /** RH ou administração. A separação que importa é entre RH e ouvidoria. */
    public function canAccessResumes(): bool
    {
        return $this->hasRole(UserRole::Hr) || $this->hasRole(UserRole::Admin);
    }

    /** Ouvidoria ou administração. */
    public function canAccessReports(): bool
    {
        return $this->hasRole(UserRole::Ombudsman) || $this->hasRole(UserRole::Admin);
    }

    /**
     * Trilha de auditoria: SOMENTE administração.
     *
     * Saber quem abriu o currículo de quem, e quem leu qual denúncia, é
     * informação de supervisão. Nas mãos de quem opera a área, vira ferramenta
     * para descobrir que um colega está sendo investigado.
     */
    public function canAccessAudit(): bool
    {
        return $this->hasRole(UserRole::Admin);
    }

    public function canAccessPortal(): bool
    {
        return $this->roles() !== [];
    }

    public function roleLabels(): string
    {
        $labels = array_map(fn (UserRole $role): string => $role->label(), $this->roles());

        return $labels === [] ? 'Sem acesso' : implode(' · ', $labels);
    }
}
