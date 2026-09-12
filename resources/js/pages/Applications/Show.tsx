import { Head } from '@inertiajs/react';
import { BackLink, DataRow, Mono, PageHead, Panel, Tag, buttonClass } from '../../components/ui';
import { AssessmentPanel } from '../../components/assessment-panel';
import type { Assessment, JobApplicationDetail } from '../../types';

interface Props {
  readonly application: JobApplicationDetail;
  readonly assessment: Assessment | null;
  readonly screeningEnabled: boolean;
}

export default function ApplicationsShow({ application, assessment, screeningEnabled }: Props) {
  return (
    <>
      <Head title={application.name} />

      <BackLink href="/candidaturas">Candidaturas</BackLink>

      <div className="mt-4">
        <PageHead
          eyebrow={application.jobOpening}
          title={application.name}
          meta={<>Recebido em {application.receivedAt}</>}
          actions={
            <a href={application.resumeUrl} className={buttonClass}>
              Baixar currículo
              <span className="font-mono text-xs opacity-70">{application.resumeSize}</span>
            </a>
          }
        />
      </div>

      <Panel glow className="mt-8 p-7">
        <dl className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          <DataRow label="E-mail">
            <a
              href={`mailto:${application.email}`}
              className="text-accent transition-colors hover:text-accent/80"
            >
              {application.email}
            </a>
          </DataRow>

          <DataRow label="Telefone">
            <a
              href={`tel:+55${application.phone}`}
              className="font-mono text-accent transition-colors hover:text-accent/80"
            >
              {application.phone}
            </a>
          </DataRow>

          <DataRow label="Consentimento LGPD">
            {application.consentAcceptedAt === null ? (
              <Tag tone="warn">não registrado</Tag>
            ) : (
              <span className="font-mono text-sm">{application.consentAcceptedAt}</span>
            )}
          </DataRow>

          <DataRow label="Arquivo">
            {/* React escapa por padrão: o nome vem do candidato, é não confiável. */}
            <span className="break-all text-sm">{application.resumeOriginalName}</span>
          </DataRow>

          <DataRow label="Protocolo">
            <Mono>{application.id}</Mono>
          </DataRow>
        </dl>

        {application.message !== null && (
          <div className="mt-8 border-t border-line pt-7">
            <p className="eyebrow">Mensagem do candidato</p>
            <p className="mt-3 max-w-3xl whitespace-pre-line leading-relaxed text-ink-soft">
              {application.message}
            </p>
          </div>
        )}
      </Panel>

      <AssessmentPanel
        applicationId={application.id}
        assessment={assessment}
        enabled={screeningEnabled}
      />
    </>
  );
}
