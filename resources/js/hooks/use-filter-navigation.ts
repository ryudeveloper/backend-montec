import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState, useTransition } from 'react';

/**
 * Navegação de filtro com estado de pendência real.
 *
 * `useTransition` é o ponto: sem ele a única pista de que o servidor está
 * respondendo é a barra de progresso no topo, longe do elemento que a pessoa
 * acabou de mexer. Com ele a própria tabela pode esmaecer enquanto atualiza, e o
 * feedback fica onde o olho já está.
 *
 * A espera de digitação existe porque cada tecla seria uma consulta ao banco.
 */
const TYPING_PAUSE_MS = 300;

export interface FilterNavigation {
  readonly isPending: boolean;
  /** Navega já, sem esperar — para clique em pílula ou paginação. */
  readonly go: (params: Record<string, string>) => void;
  /** Navega depois da pausa de digitação — para campo de busca. */
  readonly goDebounced: (params: Record<string, string>) => void;
}

export function useFilterNavigation(basePath: string): FilterNavigation {
  const [isPending, startTransition] = useTransition();
  const [pendingParams, setPendingParams] = useState<Record<string, string> | null>(null);
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

  const visit = useCallback(
    (params: Record<string, string>) => {
      const clean = Object.fromEntries(Object.entries(params).filter(([, value]) => value !== ''));

      startTransition(() => {
        router.get(basePath, clean, {
          preserveState: true,
          preserveScroll: true,
          // replace: não empilha um estado de histórico por tecla digitada.
          replace: true,
        });
      });
    },
    [basePath],
  );

  useEffect(() => {
    if (pendingParams === null) return;

    timer.current = setTimeout(() => visit(pendingParams), TYPING_PAUSE_MS);

    return () => {
      if (timer.current !== null) clearTimeout(timer.current);
    };
  }, [pendingParams, visit]);

  return {
    isPending,
    go: visit,
    goDebounced: setPendingParams,
  };
}
