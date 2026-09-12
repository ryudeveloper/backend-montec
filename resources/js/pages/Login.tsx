import { Head, useForm } from '@inertiajs/react';
import { type ReactNode, useId, useState } from 'react';
import { EyeIcon, EyeOffIcon } from '../components/icons';
import { Logo } from '../components/logo';

const FIELD =
  'w-full border-b border-line bg-transparent py-2.5 pr-10 text-[0.9375rem] text-ink transition-colors placeholder:text-ink-dim/70 focus:border-accent focus:outline-none focus-visible:outline-none';

/** Ficha da empresa — CLAUDE.md. Números publicados, nada inventado. */
const READOUT = [
  ['ISO', '9001:2015'],
  ['PLANTA', '80.000 m²'],
  ['DESDE', '1993'],
] as const;

export default function Login() {
  // useForm cuida do token CSRF e do estado de envio. O servidor continua sendo
  // quem valida — o cliente só evita o duplo clique e exibe o erro.
  const form = useForm({ email: '', password: '', remember: false as boolean });
  const [revealed, setRevealed] = useState(false);
  const emailId = useId();
  const passwordId = useId();

  return (
    <div className="relative flex min-h-dvh items-center overflow-hidden">
      <Head title="Entrar" />

      {/*
        Chão de fábrica da própria Montec ao fundo, bem escurecido. A imagem é
        decorativa: o conteúdo não depende dela, e leitor de tela a ignora.
        As camadas por cima garantem o contraste do texto — medido, não estimado.
      */}
      <div
        aria-hidden="true"
        className="pointer-events-none absolute inset-0 bg-[url('/img/chao-de-fabrica-bg.jpg')] bg-cover bg-center opacity-25"
      />
      <div
        aria-hidden="true"
        className="pointer-events-none absolute inset-0 bg-gradient-to-r from-void via-void/92 to-void/60"
      />
      <div aria-hidden="true" className="pointer-events-none absolute inset-0 grid-field opacity-40" />

      {/* Régua de medição na borda: escala de instrumento, não enfeite. */}
      <div
        aria-hidden="true"
        className="tick-rail pointer-events-none absolute inset-y-0 left-14 hidden w-px opacity-50 lg:block"
      />

      <div className="relative w-full px-6 py-10 sm:px-12 lg:pl-28">
        <div className="max-w-md">
          <Logo className="h-9" />

          <div className="mt-10 flex items-center gap-3">
            <span className="font-mono text-[0.6875rem] tracking-[0.2em] text-accent">
              ACESSO RESTRITO
            </span>
            <span aria-hidden="true" className="h-px flex-1 bg-line" />
          </div>

          <h1 className="mt-5 font-semibold text-4xl leading-[1.08] tracking-tight text-ink">
            Intranet
          </h1>
          {/*
            A frase anterior enumerava as áreas e não dizia nada. Esta nomeia as
            duas regras que de fato definem o portal: separação por área e
            registro de todo acesso.
          */}
          <p className="mt-3 text-[0.9375rem] leading-relaxed text-ink-soft">
            Cada área com seu acesso.{' '}
            <span className="text-ink-dim">Cada acesso registrado.</span>
          </p>

          {/* Bezel do instrumento: sem raio grande, com cantoneiras. */}
          <div className="bracket-frame mt-9 border border-edge bg-surface/70 p-7 backdrop-blur-md">
            {form.errors.email !== undefined && (
              <div
                role="alert"
                className="mb-7 border-l-2 border-flag bg-flag/10 px-4 py-2.5 text-sm text-flag"
              >
                {form.errors.email}
              </div>
            )}

            <form
              onSubmit={(event) => {
                event.preventDefault();
                form.post('/login');
              }}
              className="flex flex-col gap-7"
            >
              <div>
                <label htmlFor={emailId} className="eyebrow">
                  E-mail
                </label>
                <input
                  id={emailId}
                  type="email"
                  required
                  autoFocus
                  autoComplete="username"
                  placeholder="voce@montecmococa.com.br"
                  value={form.data.email}
                  onChange={(event) => form.setData('email', event.target.value)}
                  aria-invalid={form.errors.email !== undefined}
                  className={`${FIELD} mt-1`}
                />
              </div>

              <div>
                <label htmlFor={passwordId} className="eyebrow">
                  Senha
                </label>
                <div className="relative">
                  <input
                    id={passwordId}
                    type={revealed ? 'text' : 'password'}
                    required
                    autoComplete="current-password"
                    placeholder="••••••••••••"
                    value={form.data.password}
                    onChange={(event) => form.setData('password', event.target.value)}
                    className={`${FIELD} mt-1`}
                  />
                  {/*
                    Revelar a senha reduz erro de digitação — e aqui o mínimo é 12
                    caracteres. O rótulo muda junto do estado, senão o leitor de
                    tela anuncia sempre a mesma coisa.
                  */}
                  <button
                    type="button"
                    onClick={() => setRevealed((previous) => !previous)}
                    aria-label={revealed ? 'Ocultar senha' : 'Mostrar senha'}
                    aria-pressed={revealed}
                    className="absolute bottom-2 right-0 p-1 text-ink-dim transition-colors hover:text-accent"
                  >
                    {revealed ? <EyeOffIcon /> : <EyeIcon />}
                  </button>
                </div>
              </div>

              <div className="flex flex-wrap items-center justify-between gap-4 pt-1">
                <label className="flex items-center gap-2.5 text-sm text-ink-soft">
                  <input
                    type="checkbox"
                    checked={form.data.remember}
                    onChange={(event) => form.setData('remember', event.target.checked)}
                    className="size-4 rounded-none border-line bg-surface-2 accent-accent"
                  />
                  Manter conectado
                </label>

                <button
                  type="submit"
                  disabled={form.processing}
                  className="group inline-flex items-center gap-3 bg-accent px-6 py-2.5 text-sm font-semibold text-void transition-colors hover:bg-accent/90 disabled:opacity-50"
                >
                  {form.processing ? 'Autenticando' : 'Entrar'}
                  <span
                    aria-hidden="true"
                    className="transition-transform group-hover:translate-x-0.5"
                  >
                    →
                  </span>
                </button>
              </div>
            </form>
          </div>

          {/* Leitura técnica: dados reais da ficha da empresa. */}
          <dl className="mt-9 flex flex-wrap gap-x-8 gap-y-3 border-t border-line pt-5">
            {READOUT.map(([label, value]) => (
              <div key={label} className="flex items-baseline gap-2">
                <dt className="font-mono text-[0.625rem] tracking-[0.18em] text-ink-dim">
                  {label}
                </dt>
                <dd className="font-mono text-xs text-ink-soft">{value}</dd>
              </div>
            ))}
          </dl>

          <p className="mt-5 font-mono text-[0.6875rem] leading-relaxed text-ink-dim">
            solicite as credenciais ao administrador
          </p>
        </div>
      </div>
    </div>
  );
}

/** Sem a casca do portal: não há usuário autenticado para o cabeçalho mostrar. */
Login.layout = (page: ReactNode): ReactNode => page;
