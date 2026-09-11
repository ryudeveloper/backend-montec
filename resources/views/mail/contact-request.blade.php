<x-mail::message>
# Nova solicitação pelo site

**Nome:** {{ $contactRequest->name }}
**E-mail:** {{ $contactRequest->email }}
**Telefone:** {{ $contactRequest->phone }}
**Assunto:** {{ $contactRequest->subject }}

**Mensagem:**

{{ $contactRequest->message }}

<x-mail::subcopy>
Recebido em {{ $contactRequest->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}.
Protocolo: {{ $contactRequest->id }}
@if ($contactRequest->consent_accepted_at)
Consentimento LGPD registrado em {{ $contactRequest->consent_accepted_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}.
@endif
</x-mail::subcopy>
</x-mail::message>
