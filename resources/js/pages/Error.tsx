import { Head, Link, usePage } from '@inertiajs/react';
import { Panel, buttonClass, ghostButtonClass } from '../components/ui';
import type { SharedProps } from '../types';

interface Props {
  readonly status: number;
  readonly message: string;
}

const TITLES: Record<number, string> = {
  403: 'Acesso negado',
  404: 'Não encontrado',
  419: 'Sessão expirada',
  500: 'Falha no servidor',
  503: 'Em manutenção',
};

/**
 * Tela de erro dentro do portal.
 *
 * Existe para manter o cabeçalho: a tela de erro padrão não tem menu nem Sair, e
 * quem batia numa área que não é a sua ficava sem caminho de volta. Os atalhos
 * abaixo saem das permissões compartilhadas, então esta tela não revela a
 * existência da área proibida a quem não pode abri-la.
 */
export default function ErrorPage({ status, message }: Props) {
  /*
   * Leitura defensiva: esta é a tela que aparece QUANDO algo já deu errado. Se
   * ela depender de uma prop e a prop faltar, o React quebra e o usuário fica
   * com tela preta — sem mensagem e sem caminho de volta. Foi exatamente o que
   * aconteceu quando o tratador de exceções renderizava sem `auth`.
   */
  const auth = usePage<SharedProps>().props.auth ?? {
    user: null,
    can: { viewResumes: false, viewReports: false, viewAudit: false },
  };

  return (
    <>
      <Head title={TITLES[status] ?? 'Erro'} />

      <Panel glow className="mx-auto mt-10 max-w-lg p-8 text-center">
        {/* O código grande e monoespaçado dá a leitura imediata do que houve. */}
        <p className="font-mono text-5xl leading-none tracking-tight text-accent/40">{status}</p>

        <h1 className="mt-5 font-semibold text-2xl tracking-tight text-ink">
          {TITLES[status] ?? 'Algo deu errado'}
        </h1>

        <p className="mt-4 text-sm leading-relaxed text-ink-soft">{message}</p>

        <div className="mt-8 flex flex-wrap justify-center gap-3">
          {auth.can.viewResumes && (
            <Link href="/rh/candidaturas" className={buttonClass}>
              Candidaturas
            </Link>
          )}

          {auth.can.viewReports && (
            <Link href="/rh/ouvidoria" className={buttonClass}>
              Ouvidoria
            </Link>
          )}

          {auth.can.viewAudit && (
            <Link href="/rh/auditoria" className={buttonClass}>
              Auditoria
            </Link>
          )}

          {auth.user === null ? (
            <Link href="/rh/login" className={ghostButtonClass}>
              Ir para o login
            </Link>
          ) : (
            <Link href="/rh/logout" method="post" as="button" className={ghostButtonClass}>
              Sair
            </Link>
          )}
        </div>
      </Panel>
    </>
  );
}
