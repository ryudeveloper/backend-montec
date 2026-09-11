# Deploy no cPanel

O frontend é estático; este backend não. Ele precisa de PHP 8.3+, de um banco e
de permissão de escrita — e de uma configuração que, se errada, expõe currículos
e denúncias pela web.

Leia o passo 2 antes de qualquer coisa.

---

## 1. Levar o código até o servidor

Há dois caminhos. **Por git é o recomendado**: atualizar depois vira um
`git pull`, e dá para ver exatamente o que mudou entre uma versão e outra.

### Por git (recomendado)

No Terminal do cPanel, ou por SSH:

```bash
mkdir -p ~/apps && cd ~/apps
git clone https://github.com/ryudeveloper/backend-montec.git
cd backend-montec
```

O clone **não traz** `vendor/` (76 MB, instalado pelo composer no servidor) nem
o `.env` (as credenciais são da máquina, não do repositório).

Traz, sim, o `public/build/` — ao contrário do padrão do Laravel, os assets do
portal são versionados de propósito. cPanel compartilhado raramente tem Node, e
sem eles o portal abriria sem CSS e sem JS.

> Ao mudar algo de interface, rode `npm run build` **na sua máquina**, commite o
> `public/build/` junto e dê `git pull` no servidor.

### Por zip

Se o servidor não tiver git:

```bash
npm run package:cpanel     # na sua máquina
```

Sai o `backend-cpanel.zip` (~9,5 MB), com o `vendor` já dentro. O script confere
e **aborta** se o pacote contiver `.env`, banco local, currículos, logs ou
pacotes de desenvolvimento.

---

## 2. O subdomínio — o passo que não pode errar

No cPanel, **Domínios → Criar um domínio**, por exemplo `api.montec.ryudev.net`.

O campo **Document Root** precisa apontar para a pasta `public` do projeto:

```
/home/SEU_USUARIO/apps/backend-montec/public        ← certo
/home/SEU_USUARIO/apps/backend-montec               ← ERRADO
```

Com o Document Root na raiz do projeto, qualquer pessoa acessa
`https://api.montec.ryudev.net/.env` e lê suas credenciais de banco, de SMTP e a
chave da API — e `/storage/app/private/resumes/` entrega os currículos.

Por isso o projeto fica **fora do `public_html`**. Sugestão de estrutura:

```
/home/SEU_USUARIO/
├── public_html/                     domínio principal
├── montec.ryudev.net/               frontend (já no ar)
└── apps/backend-montec/             ← aqui, fora do alcance da web
    ├── .env                         inalcançável por URL
    ├── storage/app/private/         currículos, inalcançáveis
    ├── vendor/                      inalcançável
    └── public/                      ← Document Root aponta AQUI
        ├── index.php
        └── build/
```

### Segunda barreira

A proteção acima depende de **um** acerto no painel. Por isso o pacote traz três
`.htaccess` que negam acesso mesmo se o Document Root for configurado errado:

| Arquivo | O que protege |
|---|---|
| `.htaccess` (raiz) | Nega tudo. É o que segura um Document Root apontado para a raiz do projeto |
| `storage/.htaccess` | Nega tudo. Segunda camada sobre os currículos |
| `public/.htaccess` | Força HTTPS, põe cabeçalhos de segurança e nega `.env`, `.log`, `.sqlite` mesmo dentro de `public/` |

Seis testes garantem que esses arquivos existem e continuam negando — eles são
invisíveis no dia a dia, e ninguém percebe que sumiram até o dia em que fariam
falta.

O empacotador **aborta** se qualquer um dos três estiver ausente.

---

## 3. Enviar e extrair

Gerenciador de Arquivos → crie `/home/SEU_USUARIO/apps/` → envie o
`backend-cpanel.zip` → botão direito → **Extrair** → renomeie a pasta para
`backend-montec` → **apague o zip do servidor**.

---

## 4. PHP

**Select PHP Version** (ou MultiPHP): escolha **8.3 ou superior** para o
subdomínio da API.

Extensões: `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `ctype`,
`json`, `fileinfo`, `zip`, `curl`. Quase todas já vêm ligadas.

Em **Options**, ajuste:

```ini
upload_max_filesize = 6M     ; acima dos 5 MB do currículo
post_max_size       = 10M    ; acima do upload_max_filesize, com folga
memory_limit        = 256M
expose_php          = Off    ; não anunciar a versão do PHP
```

O `upload_max_filesize` padrão costuma ser **2M** — com ele, um currículo de
5 MB é recusado pelo PHP antes de chegar ao Laravel, e o candidato vê um erro
sem explicação.

---

## 5. Banco de dados

**MySQL® Databases**: crie o banco, crie o usuário, associe com *ALL
PRIVILEGES*. Anote os três valores — cPanel prefixa tudo com seu usuário
(`seuuser_montec`).

---

## 6. O `.env`

No Gerenciador de Arquivos, dentro de `apps/backend-montec`, copie
`.env.example` para `.env` e edite:

```ini
APP_NAME="Portal Montec"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.montec.ryudev.net

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=seuuser_montec
DB_USERNAME=seuuser_montec
DB_PASSWORD=...

# SMTP real. `log` e `array` são BLOQUEADOS em produção no boot: eles gravam o
# corpo do e-mail — currículo e denúncia — em texto puro nos logs.
MAIL_MAILER=smtp
MAIL_HOST=mail.seudominio.com.br
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=nao-responda@montecmococa.com.br

# Origem do SITE, não da API. Sem isto o formulário do site é recusado.
MONTEC_CORS_ORIGINS=https://montec.ryudev.net

MONTEC_MAIL_SALES=contato@montecmococa.com.br
MONTEC_MAIL_CAREERS=vagas@montecmococa.com.br
MONTEC_MAIL_WHISTLEBLOWER=ouvidoria@montecmococa.com.br

# Deixe vazio até acertar a política de privacidade (ver seção 11).
MONTEC_AI_KEY=
```

`APP_DEBUG=false` não é detalhe: com `true`, qualquer erro devolve stack trace,
trecho de código e valores de variável para quem estiver na tela.

E **nunca** suba o seu `.env` local — ele tem `MONTEC_AI_DRIVER=demo`, que o
sistema recusa em produção justamente para isso não passar.

---

## 7. Rodar o deploy

Um comando só, no Terminal do cPanel ou por SSH:

```bash
cd ~/apps/backend-montec
bash scripts/deploy.sh
```

Ele instala as dependências de produção, gera o `APP_KEY` **só no primeiro
deploy**, aplica as migrations, refaz os caches e ajusta as permissões. Antes de
tudo isso confere o ambiente e **aborta com mensagem clara** se faltar `.env`,
se o PHP for menor que 8.3 ou se o `public/build/` não tiver vindo.

O script **não toca** no `.env` nem em `storage/app/private/` — credenciais e
currículos já recebidos ficam onde estão.

### Quando o Terminal usa outra versão de PHP

Acontece com frequência: o site roda em 8.3 e o Terminal abre em 7.4. Aponte o
binário certo:

```bash
PHP_BIN=/opt/cpanel/ea-php83/root/usr/bin/php bash scripts/deploy.sh
```

### Se não houver composer

Instale na sua pasta pessoal, sem precisar de root:

```bash
mkdir -p ~/bin
curl -sS https://getcomposer.org/installer | php -- --install-dir=$HOME/bin --filename=composer
COMPOSER_BIN=$HOME/bin/composer bash scripts/deploy.sh
```

### A conta de acesso

Só no primeiro deploy:

```bash
php artisan montec:create-user voce@montecmococa.com.br --name="Seu Nome" --roles=admin
```

A senha é pedida oculta — nunca como argumento, que ficaria no histórico do
shell e visível em `ps` para qualquer processo da máquina.

Papéis: `hr`, `ombudsman`, `admin` (acumuláveis por vírgula). Comece por um
`admin` — é o único que vê a trilha de auditoria.

> **Sem Terminal nem SSH?** Peça o acesso ao suporte da hospedagem. Não exponha
> uma rota web que roda `artisan`: seria um executor de comandos aberto na
> internet.

---

## 8. Permissões

```bash
chmod -R 775 storage bootstrap/cache
```

Se o Laravel reclamar de escrita, confira o dono — em cPanel costuma ser o seu
usuário, e `755` basta.

---

## 9. Agendador

**Cron Jobs**, uma vez por minuto:

```
* * * * * cd /home/SEU_USUARIO/apps/backend-montec && php artisan schedule:run >> /dev/null 2>&1
```

É ele que roda o expurgo diário por retenção (LGPD), às 03:30.

---

## 10. Ligar o frontend na API

O site usa caminho relativo por padrão. Como a API está em subdomínio, o build do
frontend precisa apontar para ela:

```bash
cd ../montec-site
VITE_SITE_URL=https://montec.ryudev.net \
VITE_API_URL=https://api.montec.ryudev.net \
npm run package:cpanel
```

`VITE_API_URL` exige **https** (exceto localhost) — em http, currículo e denúncia
trafegariam em claro.

Do outro lado, o `MONTEC_CORS_ORIGINS` do backend precisa listar a origem do
site. As duas pontas têm de casar, senão o navegador bloqueia o envio.

---

## 11. Antes de ligar a triagem por IA

`MONTEC_AI_KEY` manda o texto do currículo para a Anthropic. Isso é tratamento de
dado pessoal por terceiro e **precisa estar declarado na política de privacidade
do site** — é texto jurídico, não configuração.

O texto vai redigido (nome, e-mail, telefone, CPF, RG e idade são removidos antes
do envio), mas a declaração continua sendo necessária.

---

## 12. Conferir depois de subir

| Verificação | Esperado |
|---|---|
| `https://api.montec.ryudev.net/rh/login` | tela de login, com estilo |
| `https://api.montec.ryudev.net/.env` | **404 ou 403** — se baixar o arquivo, o Document Root está errado: pare tudo e corrija |
| `https://api.montec.ryudev.net/` | 404 (não há página pública) |
| `https://api.montec.ryudev.net/up` | 200 |
| Login com a conta criada | entra no portal |
| Formulário do site em `/contato` | chega no e-mail e aparece no portal |
| Baixar um currículo | baixa como anexo, e o download aparece na Auditoria |

Se o portal abrir **sem estilo nenhum**, falta o `public/build/` — reenvie o
pacote inteiro.

---

## Atualizações

Na sua máquina, se mexeu em interface:

```bash
npm run build
git add public/build && git commit -m "..." && git push
```

No servidor:

```bash
cd ~/apps/backend-montec
git pull
bash scripts/deploy.sh
```

O `.env` e o `storage/app/private/` estão no `.gitignore`, então **não são
tocados** — os currículos já recebidos continuam onde estão.

Se o `git pull` reclamar de mudança local em `public/build/`, é porque alguém
buildou no servidor. Resolva com `git checkout -- public/build && git pull`.
