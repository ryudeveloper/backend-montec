<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\JobApplicationController;
use App\Http\Controllers\Api\WhistleblowerController;
use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| Formulários públicos
|------------------------------------------------------------------------------
| Contrato em português, herdado do site atual (CLAUDE.md §6 do frontend).
|
| Rate limit por IP é a única barreira anti-bot que o servidor controla: o
| honeypot e o tempo mínimo de preenchimento moram no cliente e um bot que poste
| direto no endpoint não passa por nenhum dos dois. A chave do limitador é
| efêmera, fica no cache e nunca é associada ao conteúdo enviado — inclusive na
| ouvidoria, onde vincular IP à denúncia quebraria o anonimato.
*/
Route::middleware('throttle:public-forms')->group(function (): void {
    Route::post('/contato', [ContactController::class, 'store'])->name('api.contact.store');

    Route::post('/trabalhe-conosco', [JobApplicationController::class, 'store'])
        ->name('api.job-application.store');

    Route::post('/ouvidoria', [WhistleblowerController::class, 'store'])
        ->name('api.whistleblower.store');
});
