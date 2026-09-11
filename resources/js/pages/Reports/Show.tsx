import { Head } from '@inertiajs/react';
import { BackLink, DataRow, Mono, PageHead, Panel, Tag } from '../../components/ui';
import type { ReportDetail } from '../../types';

export default function ReportsShow({ report }: { report: ReportDetail }) {
  return (
    <>
      <Head title="Denúncia" />

      <BackLink href="/rh/ouvidoria">Ouvidoria</BackLink>

      <div className="mt-4">
        <PageHead
          eyebrow={report.isAnonymous ? 'Denúncia anônima' : 'Denúncia identificada'}
          title={report.subject}
          meta={<>Recebida em {report.receivedAt}</>}
          actions={<Tag tone={report.isAnonymous ? 'quiet' : 'accent'}>{report.id.slice(0, 8)}</Tag>}
        />
      </div>

      {report.isAnonymous ? (
        /*
          Não há nome nem e-mail para exibir porque eles nunca foram gravados: o
          serviço descarta a identificação quando a denúncia é anônima. Não é a
          tela que esconde — o dado não existe. Dizer isso explicitamente evita
          que o operador procure o dado ou suponha que ele está em outro lugar.
        */
        <div className="mt-8 rounded-panel border border-line bg-surface-2 px-5 py-4">
          <p className="eyebrow">Identificação</p>
          <p className="mt-2 max-w-2xl text-sm leading-relaxed text-ink-soft">
            O denunciante optou pelo anonimato. Nenhum dado de identificação foi coletado — nem
            nome, nem e-mail, nem endereço de IP. Não é possível responder diretamente.
          </p>
        </div>
      ) : (
        <Panel className="mt-8 p-7">
          <dl className="grid gap-6 sm:grid-cols-2">
            <DataRow label="Nome">{report.name}</DataRow>
            <DataRow label="E-mail">
              <a
                href={`mailto:${report.email}`}
                className="text-accent transition-colors hover:text-accent/80"
              >
                {report.email}
              </a>
            </DataRow>
          </dl>
        </Panel>
      )}

      <Panel glow className="mt-4 p-7">
        <p className="eyebrow">Relato</p>
        {/* React escapa por padrão: é texto enviado por terceiro. */}
        <p className="mt-4 max-w-3xl whitespace-pre-line text-[0.9375rem] leading-relaxed text-ink">
          {report.description}
        </p>

        <p className="mt-8 border-t border-line pt-5">
          <Mono>protocolo {report.id}</Mono>
        </p>
      </Panel>

      <p className="mt-5 font-mono text-[0.6875rem] leading-relaxed text-ink-dim">
        A abertura desta denúncia foi registrada em trilha de auditoria com seu usuário, data e
        endereço de IP.
      </p>
    </>
  );
}
