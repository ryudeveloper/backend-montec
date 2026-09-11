<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Portal\SharedProps;
use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Dados compartilhados com todas as páginas do portal.
 *
 * A montagem em si vive em SharedProps, porque o tratador de exceções precisa
 * das MESMAS props: um Inertia::render() disparado de lá não passa por este
 * middleware, e a tela de erro chegava sem `auth`.
 */
final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            ...SharedProps::for($request),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
            ],
        ];
    }
}
