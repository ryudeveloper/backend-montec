/**
 * Logo da Montec.
 *
 * `width`/`height` explícitos reservam o espaço e evitam o salto de layout
 * enquanto a imagem carrega. A proporção real do arquivo é 372×122.
 *
 * `brightness-0 invert` não é usado: a logo já tem versão clara sobre fundo
 * escuro. Se um dia entrar uma versão escura, o ajuste é aqui e em nenhum outro
 * lugar.
 */
export function Logo({ className = 'h-7' }: { className?: string }) {
  return (
    <img
      src="/img/logo-montec.png"
      alt="Montec Mococa Montagens Industriais"
      width={372}
      height={122}
      className={`w-auto ${className}`}
    />
  );
}
