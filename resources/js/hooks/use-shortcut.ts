import { useEffect, type RefObject } from 'react';

/**
 * Foca um elemento por atalho de teclado.
 *
 * Ignora o atalho quando o foco já está num campo de texto — senão digitar a
 * própria tecla dentro de um input roubaria o foco para outro lugar.
 */
export function useFocusShortcut(key: string, target: RefObject<HTMLElement | null>): void {
  useEffect(() => {
    function onKeyDown(event: KeyboardEvent): void {
      if (event.key !== key || event.metaKey || event.ctrlKey || event.altKey) return;

      const active = document.activeElement;
      const isTyping =
        active instanceof HTMLInputElement ||
        active instanceof HTMLTextAreaElement ||
        active instanceof HTMLSelectElement ||
        (active instanceof HTMLElement && active.isContentEditable);

      if (isTyping) return;

      event.preventDefault();
      target.current?.focus();
    }

    window.addEventListener('keydown', onKeyDown);

    return () => window.removeEventListener('keydown', onKeyDown);
  }, [key, target]);
}
