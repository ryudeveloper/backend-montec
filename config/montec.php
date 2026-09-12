<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | Destinatários
    |---------------------------------------------------------------------------
    | SEM valor padrão, de propósito. Um padrão apontando para as caixas reais
    | da empresa fazia qualquer ambiente — teste, homologação, demonstração —
    | despachar currículo e conteúdo de denúncia para lá ao primeiro envio, sem
    | nada indicando que aconteceu. Em produção o RecipientGuard falha no boot
    | enquanto os três não estiverem declarados.
    */
    'recipients' => [
        'sales' => env('MONTEC_MAIL_SALES', ''),
        'careers' => env('MONTEC_MAIL_CAREERS', ''),
        // Ouvidoria em caixa própria: o conteúdo é sensível e o acesso é restrito.
        'whistleblower' => env('MONTEC_MAIL_WHISTLEBLOWER', ''),
    ],

    /*
    |---------------------------------------------------------------------------
    | Currículo
    |---------------------------------------------------------------------------
    | O limite e a whitelist repetem o que o client valida — client é UX,
    | servidor é segurança. O MIME é conferido pelo CONTEÚDO do arquivo, nunca
    | pelo que o cliente declarou.
    */
    'resume' => [
        'max_bytes' => (int) env('MONTEC_RESUME_MAX_BYTES', 5 * 1024 * 1024),
        'mime_types' => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            // .docx é um zip: alguns finfo o reportam genericamente.
            'application/zip',
        ],
        'extensions' => ['pdf', 'doc', 'docx'],
        /*
         | Disco privado — fora do webroot. Servir currículo pelo domínio
         | principal é vazamento de dado pessoal (CLAUDE.md §8).
         */
        'disk' => env('MONTEC_RESUME_DISK', 'resumes'),
    ],

    /*
    |---------------------------------------------------------------------------
    | Retenção (LGPD)
    |---------------------------------------------------------------------------
    | Retenção mínima: o expurgo roda pelo agendador e apaga registro e arquivo.
    */
    'retention_days' => [
        'contact' => (int) env('MONTEC_RETENTION_CONTACT_DAYS', 365),
        'job_application' => (int) env('MONTEC_RETENTION_JOB_DAYS', 365),
        'whistleblower' => (int) env('MONTEC_RETENTION_WHISTLEBLOWER_DAYS', 1825),
    ],

    /*
    |---------------------------------------------------------------------------
    | Triagem por IA (opcional)
    |---------------------------------------------------------------------------
    | Ligada pela presença da chave. Sem ela o recurso não aparece na interface.
    |
    | O parecer é CONSULTIVO: informa o RH e nunca reprova. Art. 20 da LGPD dá ao
    | candidato direito de revisão de decisão tomada unicamente por processamento
    | automatizado — então a decisão continua sendo de uma pessoa.
    |
    | Enviar currículo a terceiro é tratamento de dado pessoal e precisa estar
    | declarado na política de privacidade do site.
    */
    'ai' => [
        /*
         | 'anthropic' (padrão) ou 'demo'. O modo de demonstração produz um
         | parecer sem chamar API nenhuma, para exercitar a tela e a auditoria
         | antes de contratar o serviço — e é recusado em produção no boot.
         */
        'driver' => env('MONTEC_AI_DRIVER', 'anthropic'),
        'key' => env('MONTEC_AI_KEY', ''),
        /*
         | Sonnet 5 equilibra custo e capacidade para leitura de currículo em
         | volume. Opus 5 vale quando a vaga é muito técnica e o texto, ambíguo.
         */
        'model' => env('MONTEC_AI_MODEL', 'claude-sonnet-5'),
        'endpoint' => 'https://api.anthropic.com/v1/messages',
        'version' => '2023-06-01',
        /** Teto de caracteres enviados — currículo longo não melhora o parecer. */
        'max_input_chars' => (int) env('MONTEC_AI_MAX_CHARS', 18000),
    ],

    /*
    |---------------------------------------------------------------------------
    | Cloudflare Turnstile
    |---------------------------------------------------------------------------
    | Ligado pela simples presença do segredo. Um captcha meio configurado é pior
    | que nenhum: recusa gente de verdade sem barrar bot.
    |
    | A chave pública (site key) é do frontend — aqui só entra o segredo.
    */
    'turnstile' => [
        'secret' => env('MONTEC_TURNSTILE_SECRET', ''),
    ],

    /*
    |---------------------------------------------------------------------------
    | Anti-bot
    |---------------------------------------------------------------------------
    */
    'anti_bot' => [
        /*
         | Nome do honeypot no corpo da requisição. O frontend valida no client;
         | aqui é revalidado porque um bot posta direto no endpoint e nunca
         | passa pelo formulário.
         */
        'honeypot_field' => 'website',
        /** Por endpoint, por origem. */
        'rate_limit_per_minute' => (int) env('MONTEC_RATE_LIMIT_PER_MINUTE', 5),
        /** Somando os três endpoints, por origem. Menor que 3x o de cima. */
        'rate_limit_global_per_minute' => (int) env('MONTEC_RATE_LIMIT_GLOBAL_PER_MINUTE', 8),
    ],

];
