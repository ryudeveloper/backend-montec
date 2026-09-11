<x-mail::message>
# Nova candidatura

**Nome:** {{ $application->name }}
**E-mail:** {{ $application->email }}
**Telefone:** {{ $application->phone }}
**Vaga de interesse:** {{ $application->job_opening }}

@if ($application->message)
**Mensagem:**

{{ $application->message }}
@endif

O currículo segue em anexo ({{ $application->resume_original_name }},
{{ number_format($application->resume_bytes / 1024, 0, ',', '.') }} KB).

<x-mail::subcopy>
Recebido em {{ $application->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}.
Protocolo: {{ $application->id }}
@if ($application->consent_accepted_at)
Consentimento LGPD registrado em {{ $application->consent_accepted_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}.
@endif
</x-mail::subcopy>
</x-mail::message>
