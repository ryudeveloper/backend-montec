<?php

declare(strict_types=1);

namespace App\Support\Portal;

use Illuminate\Http\Request;

/**
 * Props que toda página do portal recebe.
 *
 * Vive fora do middleware porque o tratador de exceções também precisa delas: um
 * `Inertia::render()` disparado de lá NÃO passa pelo middleware, e a tela de erro
 * chegava sem `auth`. O componente lê `auth.user` para decidir o que oferecer de
 * volta — sem a prop, quebrava e a tela ficava preta, sem mensagem e sem saída.
 *
 * O que sai daqui chega ao navegador e é legível no DevTools. Então vai o
 * MÍNIMO: identificação de quem está logado e dois booleanos de permissão.
 * Papéis não vão — o cliente não precisa saber que existe `admin` para desenhar
 * um menu.
 */
final class SharedProps
{
    /**
     * @return array<string, mixed>
     */
    public static function for(Request $request): array
    {
        $user = $request->user();

        return [
            'auth' => [
                'user' => $user === null ? null : [
                    'name' => $user->name,
                    'email' => $user->email,
                    'roleLabels' => $user->roleLabels(),
                ],
                /*
                 | Dica de interface, nunca autorização. Quem decide é o
                 | EnsureAreaAccess no servidor — guarda no cliente é cosmético,
                 | porque o cliente é do usuário.
                 */
                'can' => [
                    'viewResumes' => $user?->canAccessResumes() ?? false,
                    'viewReports' => $user?->canAccessReports() ?? false,
                    'viewAudit' => $user?->canAccessAudit() ?? false,
                ],
            ],
        ];
    }
}
