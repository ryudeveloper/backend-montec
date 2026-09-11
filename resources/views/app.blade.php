<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Portal interno: nunca deve ser indexado. --}}
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title inertia>Portal Montec</title>
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
{{--
    Sem classe de cor no body: o fundo e o texto vêm dos tokens do tema em
    app.css. Uma classe utilitária aqui vence a regra de @layer base e foi
    justamente o que manteve o portal claro depois do redesign.
--}}
<body class="min-h-full antialiased">
    @inertia
</body>
</html>
