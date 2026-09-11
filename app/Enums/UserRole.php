<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Papéis do portal interno. Um usuário pode acumular mais de um.
 *
 * SEPARAÇÃO DE DEVERES entre as áreas operacionais: quem tem só `Ombudsman` não
 * vê currículo; quem tem só `Hr` não vê denúncia. Denúncia é muitas vezes contra
 * alguém da própria empresa, e quem cuida de recrutamento não tem por que ler
 * isso. Precisa das duas? Recebe os dois papéis, explicitamente.
 *
 * `Admin` é o nível de supervisão: abre as duas áreas operacionais E é o ÚNICO
 * que vê a trilha de auditoria. A contrapartida é que o acesso do próprio
 * administrador também fica registrado, e a trilha não pode ser editada nem
 * apagada pela interface — supervisionar não é ficar fora do registro.
 *
 * Ausência de papel é a negação — não existe papel "none". Conta criada sem
 * papel não enxerga nada, e o padrão é não enxergar.
 */
enum UserRole: string
{
    /** Candidaturas e currículos. */
    case Hr = 'hr';

    /** Canal de ouvidoria: denúncias. */
    case Ombudsman = 'ombudsman';

    /** Gestão de contas. Não abre currículo nem denúncia. */
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Hr => 'Recursos Humanos',
            self::Ombudsman => 'Ouvidoria',
            self::Admin => 'Administrador',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $role): string => $role->value, self::cases());
    }
}
