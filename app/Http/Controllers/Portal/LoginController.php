<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Requests\Portal\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class LoginController
{
    public function show(): InertiaResponse
    {
        return Inertia::render('Login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // Sessão nova depois de autenticar: sem isto, um id de sessão plantado
        // antes do login continua válido depois dele (session fixation).
        $request->session()->regenerate();

        $user = $request->user();
        $user?->forceFill(['last_login_at' => now()])->save();

        /*
         | Encaminha para o dispatcher, não direto para candidaturas: quem é só
         | da ouvidoria caía numa tela de currículo e levava 403 logo após
         | entrar — parecia defeito, não regra.
         */
        return redirect()->intended(route('portal.home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }
}
