import { createInertiaApp } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { createRoot } from 'react-dom/client';
import { PortalLayout } from './layouts/portal-layout';

/**
 * Componente de página, com o layout opcional que o Inertia usa para manter o
 * cabeçalho montado entre navegações.
 */
type PageComponent = {
  (props: Record<string, unknown>): ReactNode;
  layout?: (page: ReactNode) => ReactNode;
};

const pages = import.meta.glob<{ default: PageComponent }>('./pages/**/*.tsx');

void createInertiaApp({
  title: (title) => (title ? `${title} — Portal Montec` : 'Portal Montec'),

  resolve: async (name) => {
    const loader = pages[`./pages/${name}.tsx`];

    if (loader === undefined) {
      throw new Error(`Página Inertia não encontrada: ${name}`);
    }

    const module = await loader();

    /*
     * Layout persistente: o cabeçalho não é remontado a cada navegação, então o
     * menu não pisca ao trocar de área. Páginas que definem o próprio `layout`
     * (Login, por exemplo, que não tem usuário para o cabeçalho mostrar) são
     * respeitadas — daí o ??=.
     */
    module.default.layout ??= (page) => <PortalLayout>{page}</PortalLayout>;

    // Devolve o componente, não o módulo: o ComponentResolver do Inertia aceita
    // `Promise<ReactComponent>`, e não `Promise<{ default: ... }>`.
    return module.default;
  },

  setup({ el, App, props }) {
    createRoot(el).render(<App {...props} />);
  },

  progress: { color: '#1D6FA5' },
});
