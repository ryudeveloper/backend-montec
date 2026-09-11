import { Head, Link } from '@inertiajs/react';
import { useRef, useState } from 'react';
import {
  Chip,
  Empty,
  Metric,
  PageHead,
  Pagination,
  Panel,
  Sweep,
} from '../../components/ui';
import { useFilterNavigation } from '../../hooks/use-filter-navigation';
import { useFocusShortcut } from '../../hooks/use-shortcut';
import type { JobApplicationSummary, Paginated } from '../../types';

interface Props {
  readonly applications: Paginated<JobApplicationSummary>;
  readonly totalByOpening: readonly { readonly opening: string; readonly total: number }[];
  readonly filters: { readonly search: string; readonly opening: string };
}

export default function ApplicationsIndex({ applications, totalByOpening, filters }: Props) {
  const nav = useFilterNavigation('/rh/candidaturas');
  const searchRef = useRef<HTMLInputElement>(null);
  const [search, setSearch] = useState(filters.search);

  // "/" foca a busca — atalho de quem usa a tela todo dia.
  useFocusShortcut('/', searchRef);

  const setOpening = (opening: string): void => nav.go({ vaga: opening, busca: search });

  const onSearch = (value: string): void => {
    setSearch(value);
    nav.goDebounced({ vaga: filters.opening, busca: value });
  };

  const leader = totalByOpening[0];

  return (
    <>
      <Head title="Candidaturas" />

      <PageHead
        eyebrow="Recrutamento"
        title="Candidaturas"
        meta={
          <>
            {applications.meta.total}{' '}
            {applications.meta.total === 1 ? 'currículo' : 'currículos'}
            {filters.opening !== '' ? ` em ${filters.opening}` : ' no total'}
            {filters.search !== '' && ` · busca “${filters.search}”`}
          </>
        }
      />

      <div className="mt-8 grid gap-3 sm:grid-cols-3">
        <Metric label="Total recebido" value={applications.meta.total} tone="accent" />
        <Metric label="Vagas com candidatura" value={totalByOpening.length} />
        <Metric
          label="Vaga mais procurada"
          value={leader?.total ?? 0}
          {...(leader !== undefined ? { hint: leader.opening } : {})}
        />
      </div>

      <section className="mt-8" aria-label="Filtros">
        <div className="flex flex-wrap gap-2">
          <Chip active={filters.opening === ''} onClick={() => setOpening('')}>
            Todas
          </Chip>
          {totalByOpening.map(({ opening, total }) => (
            <Chip
              key={opening}
              active={filters.opening === opening}
              onClick={() => setOpening(opening)}
              count={total}
            >
              {opening}
            </Chip>
          ))}
        </div>

        <div className="relative mt-4 max-w-md">
          <label htmlFor="busca" className="sr-only">
            Buscar por nome ou e-mail
          </label>
          <input
            id="busca"
            ref={searchRef}
            type="search"
            value={search}
            onChange={(event) => onSearch(event.target.value)}
            placeholder="Buscar por nome ou e-mail"
            className="w-full rounded-lg border border-line bg-surface-2 px-4 py-2.5 pr-14 text-sm text-ink placeholder:text-ink-dim focus:border-accent/50 focus:outline-none focus-visible:outline-none"
          />
          <kbd
            aria-hidden="true"
            className="absolute right-3 top-1/2 -translate-y-1/2 rounded border border-line bg-surface-3 px-1.5 py-0.5 font-mono text-[0.625rem] text-ink-dim"
          >
            /
          </kbd>
        </div>
      </section>

      {/*
        aria-busy anuncia a atualização a leitor de tela; o esmaecimento e a
        varredura dão o mesmo recado visualmente, junto da tabela — e não numa
        barra no topo da janela, longe de onde o olho está.
      */}
      <Panel
        glow
        className={`relative mt-6 overflow-hidden transition-opacity ${nav.isPending ? 'opacity-60' : ''}`}
      >
        {nav.isPending && <Sweep />}

        <div aria-busy={nav.isPending} aria-live="polite">
          {applications.data.length === 0 ? (
            <Empty>Nenhuma candidatura corresponde a esse filtro.</Empty>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm">
                <thead>
                  <tr className="border-b border-line">
                    <th scope="col" className="eyebrow px-6 py-3.5 font-semibold">Candidato</th>
                    <th scope="col" className="eyebrow px-6 py-3.5 font-semibold">Vaga</th>
                    <th scope="col" className="eyebrow px-6 py-3.5 font-semibold">Recebido</th>
                    <th scope="col" className="eyebrow px-6 py-3.5 text-right font-semibold">
                      Currículo
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {applications.data.map((application, index) => (
                    <tr
                      key={application.id}
                      // Escalonamento curto: a tabela "assenta" em vez de piscar
                      // pronta. O CSS zera isso com prefers-reduced-motion.
                      style={{ animationDelay: `${Math.min(index, 10) * 18}ms` }}
                      className="animate-rise border-b border-line/60 transition-colors last:border-0 hover:bg-surface-2"
                    >
                      <td className="px-6 py-4">
                        <Link
                          href={application.detailUrl}
                          className="font-medium text-ink transition-colors hover:text-accent"
                        >
                          {application.name}
                        </Link>
                        <div className="mt-0.5 text-xs text-ink-dim">{application.email}</div>
                      </td>
                      <td className="px-6 py-4 text-ink-soft">{application.jobOpening}</td>
                      <td className="whitespace-nowrap px-6 py-4 font-mono text-xs text-ink-dim">
                        {application.receivedAt}
                      </td>
                      <td className="px-6 py-4 text-right">
                        {/*
                          <a> e não <Link>: o download é resposta de arquivo, não
                          navegação Inertia. Um Link tentaria interpretar o PDF
                          como página e a navegação travaria.
                        */}
                        <a
                          href={application.resumeUrl}
                          className="inline-flex items-center gap-2 rounded-lg border border-line px-3 py-1.5 text-xs text-ink-soft transition-colors hover:border-accent/50 hover:text-accent"
                        >
                          Baixar
                          <span className="font-mono text-ink-dim">{application.resumeSize}</span>
                        </a>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </Panel>

      <Pagination meta={applications.meta} onGo={(url) => nav.go(urlToParams(url))} />
    </>
  );
}

/** A paginação do Laravel devolve URL pronta; o hook trabalha com parâmetros. */
function urlToParams(url: string): Record<string, string> {
  return Object.fromEntries(new URL(url, window.location.origin).searchParams);
}
