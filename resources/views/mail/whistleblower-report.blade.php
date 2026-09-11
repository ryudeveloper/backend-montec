{{--
  Nenhum dado técnico do denunciante aparece aqui — nem IP, nem navegador, nem
  horário de sessão. Quando a denúncia é anônima o modelo não tem nome nem
  e-mail para vazar, porque eles nunca foram gravados.
--}}
<x-mail::message>
# Nova denúncia na ouvidoria

@if ($report->is_anonymous)
**Identificação:** anônima — o denunciante optou por não se identificar.
@else
**Identificação:** {{ $report->name }} ({{ $report->email }})
@endif

**Assunto:** {{ $report->subject }}

**Descrição:**

{{ $report->description }}

<x-mail::subcopy>
Recebida em {{ $report->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}.
Protocolo: {{ $report->id }}
</x-mail::subcopy>
</x-mail::message>
