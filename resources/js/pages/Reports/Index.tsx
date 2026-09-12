import { Head, Link } from '@inertiajs/react';
import { Chip, Empty, Metric, PageHead, Pagination, Panel, Sweep, Tag } from '../../components/ui';
import { useFilterNavigation } from '../../hooks/use-filter-navigation';
import type { Paginated, ReportSummary } from '../../types';

interface Props {
  readonly reports: Paginated<ReportSummary>;
  readonly counts: { readonly anonymous: number; readonly identified: number };
  readonly filters: { readonly mode: string };
}

const MODES = [
  { value: '', label: 'Todas' },
  { value: 'anonima', label: 'Anônimas' },
  { value: 'identificada', label: 'Identificadas' },
] as const;

export default function ReportsIndex({ reports, counts, filters }: Props) {
  const nav = useFilterNavigation('/ouvidoria');

  /*
   * Devolve um objeto de props em vez de `number | undefined`: com
   * `exactOptionalPropertyTypes`, passar `undefined` explicitamente a uma prop
   * opcional é erro — e é justamente o que o spread condicional fazia.
   */
  const countProp = (mode: string): { count?: number } => {
    if (mode === 'anonima') return { count: counts.anonymous };
    if (mode === 'identificada') return { count: counts.identified };

    return {};
  };

  return (
    <>
      <Head title="Ouvidoria" />

      <PageHead
        eyebrow="Canal de integridade"
        title="Ouvidoria"
        meta={
          <>
            {reports.meta.total}{' '}
            {reports.meta.total === 1 ? 'denúncia registrada' : 'denúncias registradas'}
          </>
        }
      />

      <div className="mt-8 grid gap-3 sm:grid-cols-3">
        <Metric label="Total" value={reports.meta.total} tone="accent" />
        <Metric label="Anônimas" value={counts.anonymous} hint="sem qualquer identificador" />
        <Metric label="Identificadas" value={counts.identified} hint="permitem resposta direta" />
      </div>

      <section className="mt-8 flex flex-wrap gap-2" aria-label="Filtros">
        {MODES.map((mode) => (
          <Chip
            key={mode.value}
            active={filters.mode === mode.value}
            onClick={() => nav.go({ identificacao: mode.value })}
            {...countProp(mode.value)}
          >
            {mode.label}
          </Chip>
        ))}
      </section>

      <Panel
        glow
        className={`relative mt-6 overflow-hidden transition-opacity ${nav.isPending ? 'opacity-60' : ''}`}
      >
        {nav.isPending && <Sweep />}

        <div aria-busy={nav.isPending} aria-live="polite">
          {reports.data.length === 0 ? (
            <Empty>Nenhuma denúncia corresponde a esse filtro.</Empty>
          ) : (
            <ul>
              {reports.data.map((report, index) => (
                <li
                  key={report.id}
                  style={{ animationDelay: `${Math.min(index, 10) * 18}ms` }}
                  className="animate-rise border-b border-line/60 last:border-0"
                >
                  <Link
                    href={report.detailUrl}
                    className="group block px-6 py-5 transition-colors hover:bg-surface-2"
                  >
                    <div className="flex flex-wrap items-center gap-3">
                      <Tag tone={report.isAnonymous ? 'quiet' : 'accent'}>
                        {report.isAnonymous ? 'anônima' : 'identificada'}
                      </Tag>

                      <span className="font-medium text-ink transition-colors group-hover:text-accent">
                        {report.subject}
                      </span>

                      <span className="ml-auto font-mono text-xs text-ink-dim">
                        {report.receivedAt}
                      </span>
                    </div>

                    {/*
                      Sem prévia do relato: ler o conteúdo exige abrir o detalhe,
                      que deixa registro de auditoria. Uma prévia entregaria o
                      texto de denúncias curtas sem passar pelo registro.
                    */}
                    <p className="mt-2 font-mono text-[0.6875rem] uppercase tracking-wider text-ink-dim">
                      abrir para ler o relato · o acesso será registrado
                    </p>
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </div>
      </Panel>

      <Pagination meta={reports.meta} onGo={(url) => nav.go(urlToParams(url))} />
    </>
  );
}

function urlToParams(url: string): Record<string, string> {
  return Object.fromEntries(new URL(url, window.location.origin).searchParams);
}
