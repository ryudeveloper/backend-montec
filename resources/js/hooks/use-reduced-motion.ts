import { useEffect, useState } from 'react';

/**
 * Respeita a preferência do sistema por menos movimento.
 *
 * O CSS já corta as animações; isto existe para o JavaScript não disparar
 * View Transitions e escalonamentos que a pessoa pediu para não ver.
 */
export function useReducedMotion(): boolean {
  const [reduced, setReduced] = useState(false);

  useEffect(() => {
    const query = window.matchMedia('(prefers-reduced-motion: reduce)');
    setReduced(query.matches);

    const onChange = (event: MediaQueryListEvent): void => setReduced(event.matches);
    query.addEventListener('change', onChange);

    return () => query.removeEventListener('change', onChange);
  }, []);

  return reduced;
}
