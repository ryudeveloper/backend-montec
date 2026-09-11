<?php

use App\Http\Middleware\ApiSecurityHeaders;
use App\Http\Middleware\EnforceMaxBodySize;
use App\Http\Middleware\HandleInertiaRequests;
use App\Support\Portal\SharedProps;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // Rotas de formulário públicas. Sem Sanctum: não há sessão nem token —
        // a proteção é validação, rate limit e CORS de origem fechada.
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Ordem importa: CORS responde o preflight, o teto de corpo mata a
        // requisição gigante antes de qualquer parse, e os cabeçalhos de
        // segurança envolvem tudo o que sair daqui.
        $middleware->api(prepend: [
            HandleCors::class,
            ApiSecurityHeaders::class,
            EnforceMaxBodySize::class,
        ]);

        // Inertia só no grupo web: as rotas de API devolvem JSON puro e não
        // devem carregar props de página nem o cabeçalho de versão de assets.
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        // Visitante sem sessão vai para o login do portal, não para a rota
        // `login` padrão do Laravel — que não existe aqui.
        $middleware->redirectGuestsTo('/rh/login');
        $middleware->redirectUsersTo('/rh');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         | Nada de dado pessoal em relatório de erro. Os formulários carregam
         | nome, e-mail, telefone, currículo inteiro em base64 e conteúdo de
         | denúncia: sem isto, o primeiro 500 despeja tudo no log e no Sentry.
         */
        $exceptions->dontFlash([
            'nome', 'email', 'telefone', 'mensagem', 'assunto',
            'descricao', 'curriculo', 'curriculo.data', 'curriculo.name',
            'password', 'password_confirmation',
        ]);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        /*
         | Erros do portal renderizam a página Inertia, para manter o cabeçalho
         | com o menu e o Sair. A tela de erro padrão não tem nada disso, e quem
         | batia numa área que não é a sua ficava sem caminho de volta.
         */
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return $response;
            }

            $status = $response->getStatusCode();

            if (! in_array($status, [403, 404, 419, 500, 503], true)) {
                return $response;
            }

            /*
             | A mensagem da exceção só é exibida em 403 — ali ela é sempre um
             | abort() escrito por nós, com texto pensado para o usuário.
             |
             | 404 usa mensagem fixa porque as do framework vazam implementação:
             | roteamento devolve "The route / could not be found", e falha de
             | model binding devolve "No query results for model [App\Models\X]",
             | que entrega o nome da classe. Em 5xx é pior ainda — caminho de
             | arquivo, consulta SQL, stack.
             */
            $safeMessages = [
                403 => 'Seu usuário não tem acesso a esta área do portal.',
                404 => 'O endereço acessado não existe ou o registro não está mais disponível.',
                419 => 'Sua sessão expirou. Entre novamente.',
                500 => 'Algo deu errado do nosso lado. Tente novamente em instantes.',
                503 => 'O sistema está em manutenção. Tente novamente em instantes.',
            ];

            $message = $status === 403 && $exception->getMessage() !== ''
                ? $exception->getMessage()
                : ($safeMessages[$status] ?? 'Algo deu errado.');

            /*
             | As props compartilhadas entram à mão: este render não passa pelo
             | middleware do Inertia, e sem `auth` o componente de erro quebra em
             | "Cannot read properties of undefined" — tela preta, sem mensagem e
             | sem caminho de volta. Ver ErrorPagePropsTest.
             */
            return Inertia::render('Error', [
                'status' => $status,
                'message' => $message,
                ...SharedProps::for($request),
            ])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
