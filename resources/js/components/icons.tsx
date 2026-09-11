/**
 * Ícones em SVG inline.
 *
 * São seis — não justificam uma biblioteca inteira no bundle de um portal
 * interno. `currentColor` deixa cada um herdar a cor do contexto, e
 * `aria-hidden` some com eles para leitor de tela: todos acompanham texto.
 */
interface IconProps {
  readonly className?: string;
}

function base(className: string | undefined): Record<string, string | number | boolean> {
  return {
    viewBox: '0 0 24 24',
    fill: 'none',
    stroke: 'currentColor',
    strokeWidth: 1.6,
    strokeLinecap: 'round',
    strokeLinejoin: 'round',
    'aria-hidden': true,
    className: className ?? 'size-4',
  };
}

export function MailIcon({ className }: IconProps) {
  return (
    <svg {...base(className)}>
      <rect x="2.5" y="4.5" width="19" height="15" rx="2.5" />
      <path d="m3 7 8.2 5.6a1.5 1.5 0 0 0 1.6 0L21 7" />
    </svg>
  );
}

export function LockIcon({ className }: IconProps) {
  return (
    <svg {...base(className)}>
      <rect x="4" y="10.5" width="16" height="10" rx="2.5" />
      <path d="M8 10.5V7.5a4 4 0 0 1 8 0v3" />
    </svg>
  );
}

export function EyeIcon({ className }: IconProps) {
  return (
    <svg {...base(className)}>
      <path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z" />
      <circle cx="12" cy="12" r="3" />
    </svg>
  );
}

export function EyeOffIcon({ className }: IconProps) {
  return (
    <svg {...base(className)}>
      <path d="M4 4.5 20 20.5" />
      <path d="M9.9 6.1A9.6 9.6 0 0 1 12 5.5c6 0 9.5 6.5 9.5 6.5a17 17 0 0 1-3.3 4" />
      <path d="M6.5 8.2A17 17 0 0 0 2.5 12S6 18.5 12 18.5a9.4 9.4 0 0 0 3.5-.7" />
      <path d="M10 10.2a3 3 0 0 0 4 4.2" />
    </svg>
  );
}

export function UsersIcon({ className }: IconProps) {
  return (
    <svg {...base(className)}>
      <circle cx="9" cy="8" r="3.2" />
      <path d="M3.5 19.5a5.5 5.5 0 0 1 11 0" />
      <path d="M16 5.6a3.2 3.2 0 0 1 0 4.8M17.5 14.6a5.5 5.5 0 0 1 3 4.9" />
    </svg>
  );
}

export function ShieldIcon({ className }: IconProps) {
  return (
    <svg {...base(className)}>
      <path d="M12 3 5 5.8v5.4c0 4.3 2.9 8.2 7 9.3 4.1-1.1 7-5 7-9.3V5.8Z" />
      <path d="m9.2 12 2 2 3.6-3.8" />
    </svg>
  );
}

export function TrailIcon({ className }: IconProps) {
  return (
    <svg {...base(className)}>
      <path d="M4 6.5h9M4 12h16M4 17.5h12" />
      <circle cx="17.5" cy="6.5" r="2" />
      <circle cx="18.5" cy="17.5" r="2" />
    </svg>
  );
}
