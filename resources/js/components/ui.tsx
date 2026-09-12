import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import type { PageMeta } from '../types';

/* ─── superfícies ──────────────────────────────────────────────────────── */

export function Panel({
  children,
  className = '',
  glow = false,
}: {
  children: ReactNode;
  className?: string;
  glow?: boolean;
}) {
  return (
    <div
      className={`rounded-panel border border-edge bg-surface ${glow ? 'aura filament' : ''} ${className}`}
    >
      {children}
    </div>
  );
}

/* ─── cabeçalho de página ──────────────────────────────────────────────── */

export function PageHead({
  eyebrow,
  title,
  meta,
  actions,
}: {
  eyebrow: string;
  title: string;
  meta?: ReactNode;
  actions?: ReactNode;
}) {
  return (
    <header className="flex flex-wrap items-end justify-between gap-4 border-b border-line pb-6 sm:gap-6">
      {/* min-w-0 e break-words porque o título pode ser o nome do candidato, que
          vem de fora: um nome longo sem espaço esticaria o cabeçalho além da
          largura da tela e criaria rolagem horizontal na página inteira. */}
      <div className="min-w-0 break-words">
        <p className="eyebrow flex items-center gap-2.5">
          <span aria-hidden="true" className="h-px w-6 bg-accent/60" />
          {eyebrow}
        </p>
        {/* Título grande e apertado contra rótulo pequeno e espaçado: o contraste
            tipográfico é o que dá hierarquia sem precisar de mais cor. */}
        <h1 className="mt-2.5 font-semibold text-2xl leading-none tracking-tight text-ink sm:text-3xl">
          {title}
        </h1>
        {meta !== undefined && <div className="mt-3 text-sm text-ink-dim">{meta}</div>}
      </div>
      {actions}
    </header>
  );
}

/* ─── métricas ─────────────────────────────────────────────────────────── */

/**
 * Bloco de número. É o que ancora a tela visualmente e responde de imediato a
 * "quantos currículos tenho, e em qual vaga".
 */
export function Metric({
  label,
  value,
  hint,
  tone = 'neutral',
}: {
  label: string;
  value: number | string;
  hint?: string;
  tone?: 'neutral' | 'accent';
}) {
  return (
    <div className="rounded-panel border border-edge bg-surface-2 px-5 py-4">
      <p className="eyebrow">{label}</p>
      <p
        className={`mt-1.5 font-mono text-2xl leading-none tabular-nums sm:text-3xl ${
          tone === 'accent' ? 'text-accent' : 'text-ink'
        }`}
      >
        {value}
      </p>
      {hint !== undefined && <p className="mt-1.5 text-xs text-ink-dim">{hint}</p>}
    </div>
  );
}

/* ─── filtros ──────────────────────────────────────────────────────────── */

export function Chip({
  active,
  onClick,
  children,
  count,
}: {
  active: boolean;
  onClick: () => void;
  children: ReactNode;
  count?: number;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={active}
      className={`group inline-flex items-center gap-2 rounded-full border px-3.5 py-1.5 text-sm transition-colors ${
        active
          ? 'border-accent/50 bg-accent-soft text-accent'
          : 'border-line bg-surface-2 text-ink-soft hover:border-edge hover:text-ink'
      }`}
    >
      {children}
      {count !== undefined && (
        <span
          className={`font-mono text-xs tabular-nums ${active ? 'text-accent' : 'text-ink-dim'}`}
        >
          {count}
        </span>
      )}
    </button>
  );
}

export function Tag({ tone, children }: { tone: 'quiet' | 'accent' | 'warn'; children: ReactNode }) {
  const tones = {
    quiet: 'border-line bg-surface-3 text-ink-soft',
    accent: 'border-accent/40 bg-accent-soft text-accent',
    warn: 'border-warn/40 bg-warn/10 text-warn',
  } as const;

  return (
    <span
      className={`inline-flex items-center rounded-md border px-2 py-0.5 text-[0.6875rem] font-medium uppercase tracking-wider ${tones[tone]}`}
    >
      {children}
    </span>
  );
}

/* ─── estado de carregamento ───────────────────────────────────────────── */

/**
 * Varredura sobre o painel que está atualizando.
 *
 * Fica junto do conteúdo, não no topo da janela: é onde o olho está depois de
 * mexer no filtro. `aria-hidden` porque o anúncio para leitor de tela vem do
 * `aria-busy` no container.
 */
export function Sweep() {
  return (
    <div aria-hidden="true" className="absolute inset-x-0 top-0 h-px overflow-hidden">
      <div className="h-px w-1/3 animate-sweep bg-gradient-to-r from-transparent via-accent to-transparent" />
    </div>
  );
}

/* ─── vazio ────────────────────────────────────────────────────────────── */

export function Empty({ children }: { children: ReactNode }) {
  return (
    <div className="px-4 py-16 text-center sm:px-6">
      <p className="font-mono text-xs uppercase tracking-widest text-ink-dim">sem registros</p>
      <p className="mt-2 text-sm text-ink-soft">{children}</p>
    </div>
  );
}

/* ─── paginação ────────────────────────────────────────────────────────── */

/**
 * Recebe estrutura explícita em vez do array `links` do Laravel, cujos rótulos
 * trazem entidades HTML e exigiriam injetar HTML de string. Seguro enquanto a
 * string é do framework — e deixa de ser no dia em que alguém interpolar dado de
 * usuário ali.
 */
export function Pagination({ meta, onGo }: { meta: PageMeta; onGo: (url: string) => void }) {
  if (meta.lastPage <= 1) return null;

  const button =
    'rounded-lg border border-line bg-surface-2 px-3.5 py-1.5 text-sm text-ink-soft transition-colors hover:border-edge hover:text-ink disabled:opacity-40 disabled:hover:border-line disabled:hover:text-ink-soft';

  return (
    <nav className="mt-5 flex items-center gap-4" aria-label="Paginação">
      <button
        type="button"
        className={button}
        disabled={meta.prevUrl === null}
        onClick={() => meta.prevUrl !== null && onGo(meta.prevUrl)}
      >
        Anterior
      </button>

      <span className="font-mono text-xs tabular-nums text-ink-dim">
        {String(meta.currentPage).padStart(2, '0')} / {String(meta.lastPage).padStart(2, '0')}
      </span>

      <button
        type="button"
        className={button}
        disabled={meta.nextUrl === null}
        onClick={() => meta.nextUrl !== null && onGo(meta.nextUrl)}
      >
        Próxima
      </button>
    </nav>
  );
}

/* ─── botões e links ───────────────────────────────────────────────────── */

export const buttonClass =
  'inline-flex items-center gap-2 rounded-lg bg-accent px-4 py-2.5 text-sm font-semibold text-void transition-colors hover:bg-accent/85';

export const ghostButtonClass =
  'inline-flex items-center gap-2 rounded-lg border border-line bg-surface-2 px-4 py-2.5 text-sm font-medium text-ink-soft transition-colors hover:border-edge hover:text-ink';

export function BackLink({ href, children }: { href: string; children: ReactNode }) {
  return (
    <Link
      href={href}
      className="inline-flex items-center gap-1.5 text-sm text-ink-dim transition-colors hover:text-ink"
    >
      <span aria-hidden="true">←</span>
      {children}
    </Link>
  );
}

/* ─── dados ────────────────────────────────────────────────────────────── */

export function DataRow({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="border-l border-line pl-4">
      <dt className="eyebrow">{label}</dt>
      <dd className="mt-1.5 text-ink">{children}</dd>
    </div>
  );
}

export function Mono({ children }: { children: ReactNode }) {
  return <span className="font-mono text-xs text-ink-dim">{children}</span>;
}
