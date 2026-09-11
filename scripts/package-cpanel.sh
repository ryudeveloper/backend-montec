#!/usr/bin/env bash
#
# Gera o pacote do backend para subir no cPanel.
#
# Saída: backend-cpanel.zip
#
# O pacote NÃO leva .env, banco local, logs, currículos nem pacotes de
# desenvolvimento. Cada um deles, subindo junto, é um problema:
#   .env         credenciais da máquina de quem empacotou
#   storage/app  currículos de outra base
#   logs         com MAIL_MAILER=log eles contêm o corpo dos e-mails
#   dev deps     dezenas de MB de ferramenta de teste rodando em produção
set -euo pipefail

cd "$(dirname "$0")/.."

ARCHIVE="backend-cpanel.zip"
BUILD_DIR=".cpanel-build"

echo "==> Assets do portal (React + Inertia + Tailwind)"
# Feito AQUI, não no servidor: cPanel compartilhado raramente tem Node, e quando
# tem é versão antiga. O public/build/ vai pronto dentro do pacote.
npm ci --silent
npm run build

if [[ ! -f public/build/manifest.json ]]; then
  echo "ERRO: public/build/manifest.json não existe — o portal carregaria em branco." >&2
  exit 1
fi

echo "==> Cópia limpa do projeto"
rm -rf "$BUILD_DIR" "$ARCHIVE"
mkdir -p "$BUILD_DIR"

# Exclusões explícitas no rsync: mais seguro que copiar tudo e apagar depois.
rsync -a \
  --exclude='.git' \
  --exclude='node_modules' \
  --exclude='vendor' \
  --exclude='tests' \
  --exclude='.cpanel-build' \
  --exclude='*.zip' \
  --exclude='.env' \
  --exclude='storage/app/private/***' \
  --exclude='storage/logs/*' \
  --exclude='storage/framework/cache/data/*' \
  --exclude='storage/framework/sessions/*' \
  --exclude='storage/framework/views/*' \
  --exclude='storage/framework/testing' \
  --exclude='database/database.sqlite' \
  --exclude='phpunit.xml' \
  --exclude='.phpunit.result.cache' \
  --exclude='.editorconfig' \
  --exclude='AGENTS.md' \
  --exclude='CLAUDE.md' \
  ./ "$BUILD_DIR/"

# As pastas precisam existir no servidor mesmo vazias — o Laravel escreve nelas.
for dir in storage/app/private/resumes storage/logs \
           storage/framework/cache/data storage/framework/sessions storage/framework/views \
           bootstrap/cache; do
  mkdir -p "$BUILD_DIR/$dir"
  touch "$BUILD_DIR/$dir/.gitkeep"
done

echo "==> Dependências de produção"
# --no-dev instalado numa CÓPIA: o vendor local continua com as ferramentas de
# teste, que a suíte ainda usa.
#
# --no-scripts: os scripts pós-instalação sobem o Laravel, e a cópia ainda não
# tem .env nem APP_KEY — o artisan falharia. A descoberta de pacotes acontece no
# servidor, junto do `php artisan optimize` do roteiro de deploy.
composer install \
  --working-dir="$BUILD_DIR" \
  --no-dev \
  --no-scripts \
  --optimize-autoloader \
  --no-interaction \
  --no-progress \
  --quiet

echo "==> Empacotando"
(cd "$BUILD_DIR" && zip -r -q "../$ARCHIVE" . -x '*.map')

LISTING=$(unzip -l "$ARCHIVE")
SIZE=$(du -h "$ARCHIVE" | cut -f1)

echo
echo "  ✓ $ARCHIVE — $SIZE"

# --- conferências que impedem deploy quebrado ou vazado ---------------------
fail() { echo "  ✗ $1" >&2; exit 1; }

# Comparação nativa do bash, sem pipe: sob `set -o pipefail`, `grep -q` fecha o
# pipe cedo, o processo da esquerda morre com SIGPIPE e o pipeline inteiro
# reporta falha mesmo quando o arquivo está lá.
has() { [[ "$LISTING" == *"$1"* ]]; }
has_re() { [[ "$LISTING" =~ $1 ]]; }

has 'public/index.php'            || fail "falta public/index.php"

# As três barreiras contra exposição de .env e currículo. Sem elas, um Document
# Root apontado para a raiz do projeto entrega tudo.
has '.htaccess'                   || fail "falta o .htaccess da raiz — barreira contra Document Root errado"
has 'storage/.htaccess'           || fail "falta storage/.htaccess — barreira sobre os currículos"
has 'public/.htaccess'            || fail "falta public/.htaccess — sem HTTPS forçado o login não completa"
has 'public/build/manifest.json'  || fail "falta o manifesto do Vite — o portal abriria em branco"
has 'vendor/autoload.php'         || fail "falta o vendor"
has 'artisan'                     || fail "falta o artisan"

has_re ' \.env$'                                  && fail ".env no pacote — credenciais vazando"
has 'vendor/phpunit'                              && fail "pacotes de desenvolvimento no pacote"
has 'database/database.sqlite'                    && fail "banco local no pacote"
has_re 'storage/app/private/.*\.(pdf|doc|docx)$'  && fail "currículos no pacote"
has_re 'storage/logs/.*\.log$'                    && fail "logs no pacote"

echo "  ✓ sem .env, sem banco local, sem currículos, sem logs, sem dev deps"
echo "  ✓ public/index.php, public/build e vendor presentes"

rm -rf "$BUILD_DIR"

cat <<'NEXT'

Roteiro completo em DEPLOY.md. Os dois passos que não podem errar:

  1. Document Root do subdomínio → .../backend-montec/public
     Apontar para a raiz do projeto expõe .env, storage/ e os currículos.

  2. Criar o .env no servidor a partir do .env.example — nunca subir o seu.
NEXT
