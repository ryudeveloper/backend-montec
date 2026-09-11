import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Panel, Tag } from './ui';
import type { Assessment, SharedProps } from '../types';

interface Props {
  readonly applicationId: string;
  readonly assessment: Assessment | null;
  readonly enabled: boolean;
}

/** Barra de aderência. Cor por faixa, mas o número é o que informa. */
function ScoreBar({ score }: { score: number }) {
  const tone = score >= 70 ? 'bg-accent' : score >= 45 ? 'bg-warn' : 'bg-flag';

  return (
    <div className="flex items-center gap-4">
      <span className="font-mono text-4xl leading-none tabular-nums text-ink">{score}</span>
      <div className="flex-1">
        <div
          role="meter"
          aria-valuenow={score}
          aria-valuemin={0}
          aria-valuemax={100}
          aria-label="Aderência à vaga"
          className="h-1.5 overflow-hidden rounded-full bg-surface-3"
        >
          <div className={`h-full rounded-full ${tone}`} style={{ width: `${score}%` }} />
        </div>
        <p className="eyebrow mt-2">aderência à vaga · 0 a 100</p>
      </div>
    </div>
  );
}

function Bullets({ title, items, tone }: { title: string; items: readonly string[]; tone: string }) {
  if (items.length === 0) return null;

  return (
    <div>
      <p className="eyebrow">{title}</p>
      <ul className="mt-2.5 flex flex-col gap-1.5">
        {items.map((item) => (
          <li key={item} className="flex gap-2.5 text-sm leading-relaxed text-ink-soft">
            <span aria-hidden="true" className={tone}>
              —
            </span>
            {item}
          </li>
        ))}
      </ul>
    </div>
  );
}

/**
 * Parecer de aderência gerado por IA.
 *
 * O aviso de que é consultivo fica NO painel, não num rodapé: quem lê a nota
 * precisa ver, no mesmo golpe de vista, que ela não decide nada. O Art. 20 da
 * LGPD dá ao candidato direito de revisão de decisão tomada unicamente por
 * processamento automatizado — a decisão é da pessoa que está lendo isto.
 */
export function AssessmentPanel({ applicationId, assessment, enabled }: Props) {
  const { errors } = usePage<SharedProps>().props;
  const [generating, setGenerating] = useState(false);

  if (!enabled) return null;

  const generate = (): void => {
    setGenerating(true);
    router.post(
      `/rh/candidaturas/${applicationId}/parecer`,
      {},
      { preserveScroll: true, onFinish: () => setGenerating(false) },
    );
  };

  const screeningError = errors?.screening;

  return (
    <Panel className="mt-4 p-7">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <p className="eyebrow">Triagem automática</p>
          <p className="mt-1 text-sm text-ink-dim">
            Leitura do currículo comparada aos requisitos da vaga.
          </p>
        </div>

        <button
          type="button"
          onClick={generate}
          disabled={generating}
          className="rounded-lg border border-line px-4 py-2 text-sm text-ink-soft transition-colors hover:border-accent/50 hover:text-accent disabled:opacity-50"
        >
          {generating ? 'Analisando...' : assessment === null ? 'Gerar parecer' : 'Gerar de novo'}
        </button>
      </div>

      {screeningError !== undefined && (
        <div
          role="alert"
          className="mt-5 border-l-2 border-flag bg-flag/10 px-4 py-2.5 text-sm text-flag"
        >
          {screeningError}
        </div>
      )}

      {assessment === null ? (
        <p className="mt-6 text-sm text-ink-dim">
          Nenhum parecer gerado para esta candidatura.
        </p>
      ) : (
        <>
          <div className="mt-7">
            <ScoreBar score={assessment.score} />
          </div>

          <div className="mt-7 grid gap-7 sm:grid-cols-2">
            <Bullets title="A favor" items={assessment.strengths} tone="text-accent" />
            <Bullets title="Atenção" items={assessment.gaps} tone="text-warn" />
          </div>

          {assessment.skills.length > 0 && (
            <div className="mt-7">
              <p className="eyebrow">Habilidades encontradas</p>
              <div className="mt-2.5 flex flex-wrap gap-2">
                {assessment.skills.map((skill) => (
                  <Tag key={skill} tone="quiet">
                    {skill}
                  </Tag>
                ))}
              </div>
            </div>
          )}

          {/*
            O aviso fica junto do resultado, não num rodapé de página: quem lê a
            nota precisa ver no mesmo golpe de vista que ela não decide nada.
          */}
          <p className="mt-8 border-t border-line pt-5 font-mono text-[0.6875rem] leading-relaxed text-ink-dim">
            parecer consultivo gerado por IA ({assessment.model}) em {assessment.generatedAt}
            <br />a avaliação do candidato é sempre da equipe de recrutamento — este texto informa a
            decisão, não a substitui
          </p>
        </>
      )}
    </Panel>
  );
}
