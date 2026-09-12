import { Head } from '@inertiajs/react';
import { Chip, Empty, Metric, PageHead, Pagination, Panel, Sweep, Tag } from '../../components/ui';
import { useFilterNavigation } from '../../hooks/use-filter-navigation';
import type { AuditEntry, Paginated } from '../../types';

interface Props {
  readonly logs: Paginated<AuditEntry>;
  readonly counts: {
    readonly total: number;
    /** Eventos de acesso — o mesmo recurso aberto duas vezes conta duas. */
    readonly resumes: number;
    readonly reports: number;
    /** Quantos recursos distintos foram tocados. */
    readonly resumesDistinct: number;
    readonly reportsDistinct: number;
  };
  readonly operators: readonly { readonly id: number; readonly name: string }[];
  readonly filters: { readonly type: string; readonly userId: number | null };
}

export default function AuditIndex({ logs, counts, operators, filters }: Props) {
  const nav = useFilterNavigation('/auditoria');

  const applyFilters = (next: { type?: string; userId?: string }): void =>
    nav.go({
      tipo: next.type ?? filters.type,
      usuario: next.userId ?? (filters.userId === null ? '' : String(filters.userId)),
    });

  return (
    <>
      <Head title="Auditoria" />

      <PageHead
        eyebrow="Supervisão"
        title="Trilha de auditoria"
        meta="Registro de quem acessou currículo e denúncia. Somente leitura."
      />

      {/*
        Os rótulos nomeiam a AÇÃO, não o recurso: "denúncias abertas" lia como
        quantidade de denúncias pendentes, quando o número é quantas vezes alguém
        abriu alguma. A dica embaixo dá a outra leitura — a quantos registros
        distintos esses acessos corresponderam.
      */}
      <div className="mt-8 grid gap-3 sm:grid-cols-3">
        <Metric label="Acessos registrados" value={counts.total} tone="accent" />
        <Metric
          label="Downloads de currículo"
          value={counts.resumes}
          hint={describeDistinct(counts.resumesDistinct, 'currículo', 'currículos')}
        />
        <Metric
          label="Aberturas de denúncia"
          value={counts.reports}
          hint={describeDistinct(counts.reportsDistinct, 'denúncia', 'denúncias')}
        />
      </div>

      <section className="mt-8 flex flex-wrap items-center gap-3" aria-label="Filtros">
        <div className="flex flex-wrap gap-2">
          <Chip active={filters.type === ''} onClick={() => applyFilters({ type: '' })}>
            Tudo
          </Chip>
          <Chip
            active={filters.type === 'resume'}
            onClick={() => applyFilters({ type: 'resume' })}
            count={counts.resumes}
          >
            Currículos
          </Chip>
          <Chip
            active={filters.type === 'report'}
            onClick={() => applyFilters({ type: 'report' })}
            count={counts.reports}
          >
            Denúncias
          </Chip>
        </div>

        {operators.length > 0 && (
          <div className="ml-auto">
            <label htmlFor="operador" className="sr-only">
              Filtrar por operador
            </label>
            <select
              id="operador"
              value={filters.userId === null ? '' : String(filters.userId)}
              onChange={(event) => applyFilters({ userId: event.target.value })}
              className="rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm text-ink-soft focus:border-accent/50 focus:outline-none focus-visible:outline-none"
            >
              <option value="">Todos os operadores</option>
              {operators.map((operator) => (
                <option key={operator.id} value={operator.id}>
                  {operator.name}
                </option>
              ))}
            </select>
          </div>
        )}
      </section>

      <Panel
        glow
        className={`relative mt-6 overflow-hidden transition-opacity ${nav.isPending ? 'opacity-60' : ''}`}
      >
        {nav.isPending && <Sweep />}

        <div aria-busy={nav.isPending} aria-live="polite">
          {logs.data.length === 0 ? (
            <Empty>Nenhum acesso registrado com esse filtro.</Empty>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm">
                <thead>
                  {/*
                    Cinco colunas não cabem em 375px. No celular ficam Operador
                    e Ação — quem fez o quê, que é a pergunta da trilha — e o
                    resto desce para dentro da célula do operador. Nada some:
                    numa auditoria, informação escondida por CSS e sem
                    substituto é informação perdida.
                  */}
                  <tr className="border-b border-line">
                    <th
                      scope="col"
                      className="eyebrow hidden px-6 py-3.5 font-semibold lg:table-cell"
                    >
                      Quando
                    </th>
                    <th scope="col" className="eyebrow px-4 py-3.5 font-semibold sm:px-6">
                      Operador
                    </th>
                    <th scope="col" className="eyebrow px-4 py-3.5 font-semibold sm:px-6">
                      Ação
                    </th>
                    <th
                      scope="col"
                      className="eyebrow hidden px-6 py-3.5 font-semibold lg:table-cell"
                    >
                      Registro
                    </th>
                    <th
                      scope="col"
                      className="eyebrow hidden px-6 py-3.5 text-right font-semibold lg:table-cell"
                    >
                      Origem
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {logs.data.map((entry, index) => (
                    <tr
                      key={entry.id}
                      style={{ animationDelay: `${Math.min(index, 12) * 14}ms` }}
                      className="animate-rise border-b border-line/60 transition-colors last:border-0 hover:bg-surface-2"
                    >
                      <td className="hidden whitespace-nowrap px-6 py-3.5 font-mono text-xs text-ink-dim lg:table-cell">
                        {entry.at}
                      </td>
                      <td className="px-4 py-3.5 align-top sm:px-6 lg:align-middle">
                        <span className="text-ink">{entry.operator}</span>
                        {entry.operatorEmail !== null && (
                          <span className="mt-0.5 block break-all text-xs text-ink-dim">
                            {entry.operatorEmail}
                          </span>
                        )}
                        {/* O que as colunas escondidas diriam. */}
                        <span className="mt-1.5 block text-xs text-ink-soft lg:hidden">
                          {entry.subject}
                        </span>
                        <span className="mt-0.5 block font-mono text-xs text-ink-dim lg:hidden">
                          {entry.at}
                          {entry.ip !== null && (
                            <>
                              <span className="mx-1.5">·</span>
                              {entry.ip}
                            </>
                          )}
                        </span>
                      </td>
                      <td className="px-4 py-3.5 align-top sm:px-6 lg:align-middle">
                        <Tag tone={entry.resourceType === 'resume' ? 'quiet' : 'accent'}>
                          {entry.action}
                        </Tag>
                      </td>
                      <td className="hidden px-6 py-3.5 text-ink-soft lg:table-cell">
                        {entry.subject}
                      </td>
                      <td className="hidden px-6 py-3.5 text-right font-mono text-xs text-ink-dim lg:table-cell">
                        {entry.ip ?? '—'}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </Panel>

      <Pagination meta={logs.meta} onGo={(url) => nav.go(urlToParams(url))} />

      <p className="mt-5 max-w-2xl font-mono text-[0.6875rem] leading-relaxed text-ink-dim">
        a trilha é somente leitura — não há edição nem exclusão pela interface.
        <br />o acesso do próprio administrador às áreas operacionais também é registrado aqui.
      </p>
    </>
  );
}

function describeDistinct(distinct: number, singular: string, plural: string): string {
  if (distinct === 0) return 'nenhum registro acessado';

  return `em ${distinct} ${distinct === 1 ? singular : plural}`;
}

function urlToParams(url: string): Record<string, string> {
  return Object.fromEntries(new URL(url, window.location.origin).searchParams);
}
