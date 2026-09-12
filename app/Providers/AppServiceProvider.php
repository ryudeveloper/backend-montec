<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\JobApplication\ResumeDecoder;
use App\Domain\Screening\AnthropicScreener;
use App\Domain\Screening\DemoScreener;
use App\Domain\Screening\ResumeRedactor;
use App\Domain\Screening\Screener;
use App\Support\Mail\MailDriverGuard;
use App\Support\Mail\RecipientGuard;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /*
         | O decoder recebe limites primitivos (bytes, whitelists), que o
         | container não sabe inferir. Resolvido pela config em vez de valores
         | fixos no construtor, para o ambiente poder ajustar o teto.
         */
        $this->app->bind(ResumeDecoder::class, fn (): ResumeDecoder => ResumeDecoder::fromConfig());

        // O redator recebe o teto de caracteres, que é configuração — não tem
        // por que ele alcançar o container para descobri-lo.
        $this->app->bind(ResumeRedactor::class, fn (): ResumeRedactor => ResumeRedactor::fromConfig());

        /*
         | Driver da triagem. `demo` existe só para exercitar a tela sem chave de
         | API e é barrado em produção no boot — ver DemoScreener.
         */
        $this->app->bind(Screener::class, fn (): Screener => config('montec.ai.driver') === 'demo'
            ? new DemoScreener
            : new AnthropicScreener);
    }

    public function boot(): void
    {
        /*
         | Falha no boot se produção estiver com driver de e-mail que não entrega:
         | o driver `log` grava currículo e conteúdo de denúncia em texto puro em
         | storage/logs. Ver MailDriverGuard.
         */
        // Parecer inventado num sistema real vira decisão sobre a vida de um
        // candidato tomada com base em nada.
        if (config('montec.ai.driver') === 'demo') {
            DemoScreener::assertSafeEnvironment((string) $this->app->environment());
        }

        (new MailDriverGuard)->assertSafe(
            (string) $this->app->environment(),
            (string) config('mail.default'),
        );

        /*
         | Falha no boot enquanto produção não disser para ONDE vão as mensagens.
         | Os destinatários já tiveram como padrão as caixas reais da empresa —
         | e um ambiente de teste mandava currículo e denúncia para lá sem que
         | nada registrasse. Ver RecipientGuard.
         */
        (new RecipientGuard)->assertConfigured(
            (string) $this->app->environment(),
            (array) config('montec.recipients'),
        );
    }
}
