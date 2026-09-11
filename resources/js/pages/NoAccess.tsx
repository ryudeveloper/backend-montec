import { Head, Link, usePage } from '@inertiajs/react';
import { Mono, Panel } from '../components/ui';
import type { SharedProps } from '../types';

/**
 * Conta autenticada sem nenhuma área liberada.
 *
 * Responde 200 com explicação e botão de sair, não 403: um 403 aqui prendia a
 * pessoa — a tela de erro não tinha o cabeçalho, logo não tinha Sair, e
 * /rh/login devolve quem já está autenticado para cá, que negava de novo.
 */
export default function NoAccess() {
  const auth = usePage<SharedProps>().props.auth ?? { user: null };

  return (
    <>
      <Head title="Sem área liberada" />

      <Panel glow className="mx-auto mt-10 max-w-lg p-8 text-center">
        <p className="eyebrow">Acesso pendente</p>
        <h1 className="mt-3 font-semibold text-2xl tracking-tight text-ink">
          Nenhuma área liberada
        </h1>

        <p className="mt-4 text-sm leading-relaxed text-ink-soft">
          Seu usuário está autenticado, mas ainda não tem nenhuma área liberada no portal. Peça ao
          administrador do sistema o papel correspondente à sua função.
        </p>

        <p className="mt-6">
          <Mono>
            {auth.user?.email} · {auth.user?.roleLabels}
          </Mono>
        </p>

        <Link
          href="/rh/logout"
          method="post"
          as="button"
          className="mt-8 rounded-lg border border-line px-4 py-2.5 text-sm font-medium text-ink-soft transition-colors hover:border-edge hover:text-ink"
        >
          Sair
        </Link>
      </Panel>
    </>
  );
}
