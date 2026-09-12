<?php

declare(strict_types=1);

use App\Http\Controllers\Portal\AuditPortalController;
use App\Http\Controllers\Portal\JobApplicationPortalController;
use App\Http\Controllers\Portal\LoginController;
use App\Http\Controllers\Portal\PortalHomeController;
use App\Http\Controllers\Portal\ResumeScreeningController;
use App\Http\Controllers\Portal\WhistleblowerPortalController;
use App\Http\Middleware\EnsureAreaAccess;
use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| Portal interno
|------------------------------------------------------------------------------
| Sessão + CSRF (grupo `web`). Sem rota de registro: contas nascem por
| `php artisan montec:create-user`.
|
| Cada área fica atrás da SUA permissão, nunca de um "acesso ao portal" genérico:
| isso deixaria tudo visível para quem entrasse, e a separação viveria só na
| interface — o mesmo que não existir.
|
| As rotas declaram ÁREA, não papel. A sobreposição do administrador (que abre as
| duas áreas operacionais) vive num lugar só, no model User.
|
| Sem prefixo de área no caminho. O portal nasceu só com currículos e as rotas
| ficaram sob `rh`; com a ouvidoria e a auditoria dentro, o prefixo passou a
| mentir — e quem cuida da ouvidoria entrava por um endereço chamado `/rh`, o que
| se lê como estar no lugar errado.
|
| As telas do portal ficam na raiz e os formulários públicos sob /api, então
| `/ouvidoria` é a tela de quem opera e `/api/ouvidoria` é o endpoint que o site
| chama. Nomes iguais, caminhos distintos, sem colisão.
*/
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'show'])->name('portal.login');
    // Segunda camada; a primeira é por e-mail+origem dentro do LoginRequest.
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('portal.logout');

    /*
     | A raiz é a porta do portal. Ela não decide nada por conta própria: quem
     | chega sem sessão é levado ao login pelo `redirectGuestsTo` do bootstrap, e
     | quem está autenticado é encaminhado para a área que de fato pode abrir.
     */
    Route::get('/', PortalHomeController::class)->name('portal.home');

    Route::middleware(EnsureAreaAccess::class.':resumes')->group(function (): void {
        Route::get('/candidaturas', [JobApplicationPortalController::class, 'index'])
            ->name('portal.applications.index');

        Route::get('/candidaturas/{application}', [JobApplicationPortalController::class, 'show'])
            ->name('portal.applications.show');

        Route::get('/candidaturas/{application}/curriculo', [JobApplicationPortalController::class, 'downloadResume'])
            ->name('portal.applications.resume');

        /*
         | POST porque cria registro e custa dinheiro: não é leitura idempotente
         | e não pode ser disparada por prefetch do navegador.
         */
        Route::post('/candidaturas/{application}/parecer', [ResumeScreeningController::class, 'store'])
            ->name('portal.applications.screen');
    });

    Route::middleware(EnsureAreaAccess::class.':reports')->group(function (): void {
        Route::get('/ouvidoria', [WhistleblowerPortalController::class, 'index'])
            ->name('portal.reports.index');

        Route::get('/ouvidoria/{report}', [WhistleblowerPortalController::class, 'show'])
            ->name('portal.reports.show');
    });

    /*
     | Auditoria: exclusiva da administração. Saber quem abriu o currículo de
     | quem — e quem leu qual denúncia — é informação de supervisão; nas mãos de
     | quem opera a área vira ferramenta para descobrir que um colega está sendo
     | investigado.
     |
     | Só leitura: não há rota de edição nem exclusão. Trilha que a interface
     | pode alterar não serve como trilha.
     */
    Route::middleware(EnsureAreaAccess::class.':audit')->group(function (): void {
        Route::get('/auditoria', [AuditPortalController::class, 'index'])
            ->name('portal.audit.index');
    });
});
