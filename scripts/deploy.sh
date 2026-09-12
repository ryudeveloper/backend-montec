#!/usr/bin/env bash
#
# Roda NO SERVIDOR, depois de `git pull`.
#
#     cd ~/apps/backend-montec
#     git pull
#     bash scripts/deploy.sh
#
# Não toca no .env nem em storage/app/private — as credenciais e os currículos
# já recebidos ficam onde estão.
set -euo pipefail

cd "$(dirname "$0")/.."

PHP="${PHP_BIN:-php}"
COMPOSER="${COMPOSER_BIN:-composer}"

echo "==> Conferindo o ambiente"

if [[ ! -f .env ]]; then
  echo "ERRO: .env não existe. Copie de .env.example e preencha antes do primeiro deploy." >&2
  exit 1
fi

PHP_VERSION=$("$PHP" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
echo "    PHP $PHP_VERSION"

if ! "$PHP" -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);'; then
  echo "ERRO: este projeto exige PHP 8.3 ou superior." >&2
  echo "      No cPanel: Select PHP Version. Se o Terminal usar outra versão que o site," >&2
  echo "      rode com o binário certo: PHP_BIN=/opt/cpanel/ea-php83/root/usr/bin/php bash scripts/deploy.sh" >&2
  exit 1
fi

# public/build é versionado justamente porque cPanel compartilhado não tem Node.
# Se sumiu, o portal abre sem estilo nenhum — e isso confunde muito.
if [[ ! -f public/build/manifest.json ]]; then
  echo "ERRO: public/build/manifest.json não existe — o portal abriria em branco." >&2
  echo "      Rode 'npm run build' na sua máquina, commite e dê pull aqui." >&2
  exit 1
fi

echo "==> Dependências"
if ! command -v "$COMPOSER" >/dev/null 2>&1; then
  echo "ERRO: composer não encontrado." >&2
  echo "      Instale na sua pasta pessoal, sem precisar de root:" >&2
  echo "        curl -sS https://getcomposer.org/installer | php -- --install-dir=\$HOME/bin --filename=composer" >&2
  echo "      Depois: COMPOSER_BIN=\$HOME/bin/composer bash scripts/deploy.sh" >&2
  exit 1
fi

# --no-dev: PHPUnit e Pint não têm o que fazer em produção.
"$COMPOSER" install --no-dev --optimize-autoloader --no-interaction --no-progress

echo "==> Aplicação"

# Gera a chave só no primeiro deploy. Regerar invalidaria toda sessão ativa e
# tornaria ilegível qualquer dado já criptografado com a anterior.
if ! grep -qE '^APP_KEY=base64:' .env; then
  echo "    gerando APP_KEY (primeiro deploy)"
  "$PHP" artisan key:generate --force
fi

"$PHP" artisan migrate --force

# Limpa antes de recachear: config em cache da versão anterior é a causa
# clássica de "mudei o .env e não surtiu efeito".
"$PHP" artisan optimize:clear
"$PHP" artisan optimize

echo "==> Diretórios de escrita"

# O git não versiona pasta vazia. Os diretórios de storage/framework existem no
# repositório porque cada um carrega um .gitignore — mas o de currículos não
# tem nenhum arquivo, então o `git clone` não o cria.
#
# O empacotador zip cria essas pastas ao montar o pacote; o caminho por git
# ficava dependendo de o Flysystem criá-las sozinho na primeira escrita. Com
# `'throw' => true` no disco, qualquer tropeço nisso vira 500 na hora em que um
# candidato envia o currículo — e a mensagem de erro não diz o que faltou.
for dir in storage/app/private/resumes \
           storage/logs \
           storage/framework/cache/data \
           storage/framework/sessions \
           storage/framework/views \
           bootstrap/cache; do
  mkdir -p "$dir"
done

echo "==> Permissões"
chmod -R ug+rwX storage bootstrap/cache

echo
echo "  ✓ deploy concluído"
"$PHP" artisan about --only=environment 2>/dev/null | sed 's/^/    /' || true

cat <<'NEXT'

  Primeiro deploy? Crie a conta de acesso:

    php artisan montec:create-user voce@suaempresa.com.br --name="Seu Nome" --roles=admin

  E confirme que o .env NÃO é alcançável pela web:

    curl -sI https://SEU-SUBDOMINIO/.env | head -1     # precisa dar 403 ou 404
NEXT
