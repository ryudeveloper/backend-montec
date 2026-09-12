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
*/

/*
| A raiz é o que as pessoas digitam e o que colam no chat interno. Sem rota ela
| devolvia 404, e quem chegava pelo endereço do portal concluía que o sistema
| estava fora do ar — o caminho certo só era conhecido por quem já tinha o link
| completo.
|
| Ela apenas encaminha: quem decide são as regras que já existem. Visitante cai
| no login pelo `redirectGuestsTo` do bootstrap; quem está autenticado segue pelo
| PortalHomeController para a área que de fato pode abrir.
|
| Deixa de esconder que há uma aplicação aqui, é verdade. Mas o subdomínio se
| chama `portal`, e /rh/login é público de qualquer forma — o 404 na raiz
| custava mais em gente perdida do que rendia em discrição.
*/
Route::redirect('/', '/rh')->name('portal.root');

Route::prefix('rh')->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('/login', [LoginController::class, 'show'])->name('portal.login');
        // Segunda camada; a primeira é por e-mail+origem dentro do LoginRequest.
        Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1');
    });

    Route::middleware('auth')->group(function (): void {
        Route::post('/logout', [LoginController::class, 'destroy'])->name('portal.logout');

        // Encaminha cada pessoa para a área que ela de fato pode abrir.
        Route::get('/', PortalHomeController::class)->name('portal.home');

        Route::middleware(EnsureAreaAccess::class.':resumes')->group(function (): void {
            Route::get('/candidaturas', [JobApplicationPortalController::class, 'index'])
                ->name('portal.applications.index');

            Route::get('/candidaturas/{application}', [JobApplicationPortalController::class, 'show'])
                ->name('portal.applications.show');

            Route::get('/candidaturas/{application}/curriculo', [JobApplicationPortalController::class, 'downloadResume'])
                ->name('portal.applications.resume');

            /*
             | POST porque cria registro e custa dinheiro: não é leitura
             | idempotente e não pode ser disparada por prefetch do navegador.
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
         | quem — e quem leu qual denúncia — é informação de supervisão; nas mãos
         | de quem opera a área vira ferramenta para descobrir que um colega está
         | sendo investigado.
         |
         | Só leitura: não há rota de edição nem exclusão. Trilha que a interface
         | pode alterar não serve como trilha.
         */
        Route::middleware(EnsureAreaAccess::class.':audit')->group(function (): void {
            Route::get('/auditoria', [AuditPortalController::class, 'index'])
                ->name('portal.audit.index');
        });
    });
});
