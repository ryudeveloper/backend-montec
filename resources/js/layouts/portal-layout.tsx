import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Logo } from '../components/logo';
import type { SharedProps } from '../types';

function NavLink({ href, active, children }: { href: string; active: boolean; children: ReactNode }) {
  return (
    <Link
      href={href}
      aria-current={active ? 'page' : undefined}
      className={`relative px-1 py-4 text-sm transition-colors ${
        active ? 'text-ink' : 'text-ink-dim hover:text-ink-soft'
      }`}
    >
      {children}
      {/* Sublinhado luminoso em vez de pílula: lê como indicador de instrumento. */}
      {active && (
        <span
          aria-hidden="true"
          className="absolute inset-x-0 -bottom-px h-0.5 bg-accent shadow-[0_0_12px_var(--color-accent)]"
        />
      )}
    </Link>
  );
}

/**
 * Casca do portal.
 *
 * Layout persistente do Inertia: o cabeçalho não é remontado a cada navegação,
 * então o menu não pisca ao trocar de área.
 *
 * O menu mostra só as áreas permitidas — link que devolve 403 parece defeito e
 * ainda revela a quem não deve que a outra área existe. Isso é interface; a
 * autorização mora no EnsureRole, no servidor.
 */
export function PortalLayout({ children }: { children: ReactNode }) {
  const page = usePage<SharedProps>();
  /*
   * Mesmo motivo do Error.tsx: a casca envolve a tela de erro, e uma prop
   * faltando aqui apagaria a página inteira em vez de degradar.
   */
  const auth = page.props.auth ?? {
    user: null,
    can: { viewResumes: false, viewReports: false, viewAudit: false },
  };
  const path = page.url;

  return (
    /*
      overflow-x-clip (e não hidden): contém o halo decorativo sem virar
      container de rolagem, o que quebraria `position: fixed` dos filhos.
    */
    <div className="relative flex min-h-dvh flex-col overflow-x-clip">
      {/* Malha técnica e halo: profundidade sem sombra pesada. */}
      <div aria-hidden="true" className="pointer-events-none fixed inset-0 grid-field opacity-60" />
      <div
        aria-hidden="true"
        className="pointer-events-none fixed -top-40 left-1/2 h-96 w-[42rem] -translate-x-1/2 rounded-full bg-accent/10 blur-3xl"
      />

      {auth.user !== null && (
        <header className="relative z-10 border-b border-line bg-void/80 backdrop-blur-md">
          <div className="mx-auto flex max-w-7xl flex-wrap items-center gap-x-10 gap-y-2 px-6">
            <Link href="/rh" className="flex items-center gap-3 py-3.5">
              <Logo className="h-7" />
              <span aria-hidden="true" className="h-6 w-px bg-line" />
              <span className="eyebrow">Portal</span>
            </Link>

            <nav className="flex items-center gap-7" aria-label="Áreas do portal">
              {auth.can.viewResumes && (
                <NavLink href="/rh/candidaturas" active={path.startsWith('/rh/candidaturas')}>
                  Candidaturas
                </NavLink>
              )}
              {auth.can.viewReports && (
                <NavLink href="/rh/ouvidoria" active={path.startsWith('/rh/ouvidoria')}>
                  Ouvidoria
                </NavLink>
              )}
              {auth.can.viewAudit && (
                <NavLink href="/rh/auditoria" active={path.startsWith('/rh/auditoria')}>
                  Auditoria
                </NavLink>
              )}
            </nav>

            <div className="ml-auto flex items-center gap-5 py-4">
              <div className="hidden text-right leading-tight sm:block">
                <p className="text-sm text-ink">{auth.user.name}</p>
                <p className="eyebrow">{auth.user.roleLabels}</p>
              </div>

              {/* method="post": o Inertia envia o token CSRF da sessão. */}
              <Link
                href="/rh/logout"
                method="post"
                as="button"
                className="rounded-lg border border-line px-3 py-1.5 text-sm text-ink-dim transition-colors hover:border-edge hover:text-ink"
              >
                Sair
              </Link>
            </div>
          </div>
        </header>
      )}

      <main className="relative z-10 mx-auto w-full max-w-7xl flex-1 px-6 py-10">{children}</main>

      <footer className="relative z-10 border-t border-line">
        <div className="mx-auto max-w-7xl px-6 py-5">
          <p className="font-mono text-[0.6875rem] leading-relaxed text-ink-dim">
            Todo acesso a currículo e a denúncia é registrado em trilha de auditoria (LGPD).
            <br className="hidden sm:inline" /> Trate estes dados com a confidencialidade que as
            pessoas esperaram ao enviá-los.
          </p>
        </div>
      </footer>
    </div>
  );
}
