# backend-montec

API dos formulários do site institucional da **Montec Mococa**
([montec-site](../montec-site) — React + Vite).

Duas coisas vivem aqui: a **API dos formulários** do site e o **portal interno**,
com duas áreas separadas por papel — candidaturas (RH) e ouvidoria.

`/` responde 404 — não há página pública neste serviço.

- **Laravel** 13.31 · **PHP** ^8.3 · **Pint** · **PHPUnit** 12.5
- Portal em **React 19 + Inertia 3 + TypeScript strict + Tailwind v4**
- **130 testes**, 545 asserções

---

## Endpoints

Contrato em português, herdado do site atual (CLAUDE.md §6 do frontend).
Sucesso: `201` com `{ message, protocolo }`.
Erro: `422` (validação) ou `429` (rate limit), sempre com `message` — é o campo
que o cliente lê para exibir o aviso (`apiAckSchema`).

### `POST /api/contato`

```json
{
  "nome": "Ana Souza",
  "email": "ana@empresa.com.br",
  "telefone": "19996566767",
  "assunto": "Solicitação de orçamento",
  "mensagem": "Preciso de orçamento para corte a laser de chapa 6mm.",
  "consentimento": true,
  "website": ""
}
```

`consentimento` e `website` são opcionais — ver **Pendências de contrato**.

### `POST /api/trabalhe-conosco`

Aceita duas formas de envio do currículo.

**`multipart/form-data` — preferido.** Campos `nome`, `email`, `telefone`, `vaga`,
`mensagem`, `consentimento`, `website` e o arquivo em `curriculo`.

**Base64 no corpo JSON — contrato herdado**, mantido para clientes antigos:

```json
{
  "nome": "João Pereira",
  "email": "joao@exemplo.com",
  "telefone": "19996566767",
  "vaga": "Soldador",
  "mensagem": "8 anos em solda MIG.",
  "curriculo": { "data": "<base64 puro, sem prefixo data:>", "name": "curriculo.pdf" }
}
```

Multipart é melhor em três frentes: o payload é o tamanho real do arquivo (e não
133% dele), o PHP grava direto em arquivo temporário em vez de carregar tudo em
memória, e o arquivo chega como `UploadedFile`. A **validação de segurança é a
mesma** nos dois caminhos — ambos passam pelo `ResumeDecoder`.

### `POST /api/ouvidoria`

```json
{
  "assunto": "Descarte irregular de resíduo",
  "descricao": "No dia 12/08, no setor de pintura, presenciei..."
}
```

`nome` e `email` ausentes = **denúncia anônima**. A ausência é o que define o
modo — não há flag a ser falsificada. Informar `nome` exige `email`:
identificar-se pela metade deixaria um dado pessoal órfão no banco.

---

## Como rodar

```bash
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve          # http://localhost:8000

php artisan test           # 36 testes
./vendor/bin/pint          # estilo
```

O `.env.example` documenta as variáveis `MONTEC_*`: destinatários, origens de
CORS, limite do currículo, rate limit e prazos de retenção.

---

## Decisões de segurança

Cada uma tem teste correspondente — não são convenções, são invariantes.

### Upload de currículo

A fronteira de risco mais séria do sistema.

| Proteção | Como |
|---|---|
| MIME conferido pelo **conteúdo** | `finfo` sobre os bytes decodificados. O tipo declarado pelo cliente nunca é consultado |
| Extensão **e** MIME têm de concordar | `.exe` renomeado para `.pdf` cai no MIME; PDF renomeado para `.php` cai na extensão |
| Limite de 5 MB | Teto no base64 (barra corpo absurdo antes de decodificar) **e** no tamanho decodificado |
| Fora do webroot | Disco `resumes` → `storage/app/private/resumes`, sem link em `public/` |
| Nome nunca reaproveitado | Caminho é `AAAA/MM/<ULID>.<ext>`. O nome enviado vira rótulo para o e-mail, nunca caminho |
| Sem arquivo órfão | Se o insert falha, o arquivo já gravado é removido — currículo em disco sem registro é retenção sem base legal |

O disco **não** é parâmetro de construtor do `ResumeStorage`: um parâmetro
tipado como `Filesystem` faz o container injetar o disco *default* em vez do
configurado, e o currículo acabava gravado fora do disco privado. Resolver
sempre pela config elimina a ambiguidade.

### Ouvidoria anônima

O anonimato prometido na interface é compromisso legal, então é garantido
estruturalmente, não por convenção:

- A tabela **não tem** coluna de IP, user-agent, sessão ou fingerprint. Há teste
  afirmando essa ausência, para ela não reaparecer por descuido numa migration.
- O serviço **descarta** nome e e-mail quando a denúncia é anônima, em vez de
  apenas não exibi-los. Um cliente adulterado que envie identificação não
  consegue gravá-la.
- O controller não toca em `$request->ip()`, não escreve log e não emite
  telemetria. Há teste verificando que nada é logado.
- O e-mail de aviso não tem `Reply-To` quando anônima — apontar o reply para o
  denunciante seria justamente o vazamento que o canal promete não cometer.

### Rate limit

É a **única** barreira anti-bot que o servidor controla: honeypot e tempo mínimo
de preenchimento moram no cliente, e quem posta direto no endpoint não passa por
nenhum dos dois. Por isso o honeypot também é revalidado aqui.

A chave do limitador é o **hash** do IP, não o IP em claro: o limitador precisa
distinguir origens, não sabê-las. O valor vive no cache, expira em um minuto e
nunca é associado ao conteúdo enviado — é o que permite limitar a ouvidoria sem
violar o anonimato.

### LGPD

- `consent_accepted_at` registra o momento do aceite da Política de Privacidade.
- `montec:purge-expired` apaga registro **e** arquivo além do prazo de retenção
  (365 dias para contato e candidatura, 1825 para ouvidoria). Roda diariamente
  às 03:30 pelo agendador. `--dry-run` relata sem apagar.
- Nenhuma tabela guarda IP: rate limit não precisa de persistência, e retenção
  mínima é exigência da lei.
- `dontFlash` redige nome, e-mail, telefone, currículo e conteúdo de denúncia dos
  relatórios de erro. Sem isso, o primeiro 500 despeja tudo no log e no Sentry.

### CORS

Origem fechada por `MONTEC_CORS_ORIGINS`. Nunca `*`: qualquer site poderia postar
nos formulários usando o navegador do visitante como ponte.

---

## Camadas anti-bot

| Camada | Onde | Observação |
|---|---|---|
| Honeypot (`website`) | client **e** servidor | Revalidado aqui: quem posta direto no endpoint nunca passa pelo formulário |
| Tempo mínimo de preenchimento | client | 3 s. Não substitui as camadas do servidor |
| Rate limit por endpoint | servidor | 5/min por origem |
| Rate limit global | servidor | 8/min por origem, somando os três endpoints |
| Cloudflare Turnstile | client + servidor | Opcional — ligado pela presença de `MONTEC_TURNSTILE_SECRET` |

### Turnstile

Ligado pela simples presença do segredo. Um captcha meio configurado é pior que
nenhum: recusa gente de verdade sem barrar bot.

Duas decisões que importam:

- **Fail closed**: Cloudflare inacessível recusa o envio. Fail open seria mais
  gentil, mas transforma uma queda da Cloudflare em janela aberta — e é
  justamente durante um ataque que a verificação tende a falhar.
- **O IP do visitante nunca é enviado à Cloudflare.** O `remoteip` é opcional na
  API deles, e mandá-lo entregaria a um terceiro exatamente o identificador que o
  canal de ouvidoria promete não coletar. Há teste afirmando isso.

---

## Proteções de requisição

- **Teto de corpo** (`EnforceMaxBodySize`): rejeita com 413 antes de qualquer
  parse. O PHP carrega o corpo inteiro em memória; um punhado de requisições de
  dezenas de MB derruba o processo sem precisar de falha de lógica. Confere o
  `Content-Length` **e** o tamanho real recebido, porque o cliente pode mentir no
  cabeçalho.
- **Cabeçalhos** (`ApiSecurityHeaders`): `nosniff`, `Referrer-Policy: no-referrer`
  (mais restritivo que o do site — estes endpoints recebem dado sensível),
  `X-Frame-Options: DENY`, `Cache-Control: no-store` e CSP `default-src 'none'`.
- **Assinatura do servidor**: `X-Powered-By` é injetado pelo PHP no nível do SAPI,
  *depois* de o Symfony montar a resposta — removê-lo do objeto de resposta não
  basta, e o teste unitário passa enquanto o servidor real vaza a versão exata do
  PHP. Daí o `header_remove`. A defesa definitiva é `expose_php=Off` no php.ini.

---

## Guarda de driver de e-mail

Produção **não sobe** com `MAIL_MAILER` em `log`, `array` ou `null`.

O driver `log` grava o corpo inteiro da mensagem em `storage/logs`: nome,
e-mail, telefone, o currículo anexado e o conteúdo das denúncias, em texto puro,
num arquivo que fica no servidor, entra em backup e frequentemente é enviado a
ferramenta de observabilidade. Para a ouvidoria isso anula a confidencialidade
prometida na interface.

Não é hipótese remota — basta alguém copiar um `.env` de desenvolvimento. Melhor
falhar no boot, alto e claro, do que descobrir depois que meses de denúncia
estavam legíveis em disco.

---

## Integração com o frontend

O frontend ([montec-site](../montec-site)) já envia o contrato completo:

- `website` (honeypot) e `consentimento` vão para o servidor nos três formulários
- o currículo sobe em **multipart**
- o token do Turnstile acompanha o envio quando a verificação está configurada

Sobre a origem da API: o frontend usa **caminho relativo por padrão** (mesma
origem), que é a configuração mais segura — sem origem cruzada não há CORS, não
há preflight e não existe uma terceira origem autorizada a postar nos
formulários. Duas topologias funcionam:

- **Proxy reverso** (recomendado): `https://www.montecmococa.com.br/api/*`
  encaminha para este serviço. O `.htaccess` do frontend traz o bloco pronto,
  comentado. Nada a configurar no build.
- **Domínio próprio** para a API: build do frontend com
  `VITE_API_URL=https://api.montecmococa.com.br` e `MONTEC_CORS_ORIGINS` listando
  a origem do site.

---

## Portal interno

Construído com **Inertia**: as páginas são componentes React, mas autenticação,
CSRF, autorização e roteamento continuam no Laravel.

A escolha foi de segurança, não de gosto. Uma SPA separada consumindo a API
exigiria CORS com credenciais, um ciclo de cookie CSRF e guardas de rota
duplicados no cliente. Com Inertia não há origem cruzada, o cookie de sessão
segue `httpOnly` e `SameSite=strict`, e **a autorização nunca sai do servidor** —
as flags `auth.can` compartilhadas com o React desenham o menu e nada mais.

```
resources/js/
├── app.tsx                      cliente Inertia + layout persistente
├── layouts/portal-layout.tsx    cabeçalho, menu condicional, Sair
├── components/ui.tsx
├── types/index.ts               espelha o que HandleInertiaRequests compartilha
└── pages/
    ├── Login.tsx                (sem layout: não há usuário no cabeçalho)
    ├── NoAccess.tsx
    ├── Error.tsx                403/404/419/500 dentro do portal
    ├── Applications/{Index,Show}.tsx
    └── Reports/{Index,Show}.tsx
```

O que o React trouxe de fato: busca por nome/e-mail com espera de digitação e
sem recarregar a página, filtros e paginação sem salto de tela, e troca entre
áreas sem remontar o cabeçalho.

Duas coisas ficaram **fora** do Inertia de propósito:

- **O download do currículo** é `<a href>` comum, não `<Link>`: é resposta de
  arquivo, não navegação de página. Um `Link` tentaria interpretar o PDF como
  página e a navegação travaria.
- **As rotas de API** (`/api/*`) não passam pelo middleware do Inertia: devolvem
  JSON puro, sem props de página nem cabeçalho de versão de assets.

```
GET  /rh/login                              Tela de acesso
POST /rh/login
POST /rh/logout
GET  /rh                                    Encaminha para a área do usuário

--- exige papel `hr` ---------------------------------------------------------
GET  /rh/candidaturas                       Lista, filtro por vaga, busca
GET  /rh/candidaturas/{id}                  Detalhe do candidato
GET  /rh/candidaturas/{id}/curriculo        Download auditado

--- exige papel `ombudsman` --------------------------------------------------
GET  /rh/ouvidoria                          Lista, filtro anônima/identificada
GET  /rh/ouvidoria/{id}                      Detalhe da denúncia (auditado)

--- exige área `audit` (somente admin) ---------------------------------------
GET  /rh/auditoria                          Trilha de acessos, somente leitura
```

### Separação de deveres

É o requisito central, não uma conveniência: denúncia é muitas vezes contra
alguém da própria empresa, e quem cuida de recrutamento não tem por que ler isso.

| Papéis do usuário | Candidaturas | Ouvidoria | Auditoria |
|---|---|---|---|
| `hr` | ✅ | 403 | 403 |
| `ombudsman` | 403 | ✅ | 403 |
| `hr,ombudsman` | ✅ | ✅ | 403 |
| `admin` | ✅ | ✅ | ✅ |
| *(nenhum)* | 403 | 403 | 403 |

A separação que o portal garante é entre os papéis **operacionais**: quem cuida de
recrutamento não lê denúncia, e vice-versa.

`admin` é o nível de **supervisão** — abre as duas áreas operacionais e é o único
que vê a trilha. A contrapartida: o acesso do próprio administrador também é
registrado, e a trilha é somente leitura mesmo para ele.

Três decisões que sustentam isso:

**Papéis são um conjunto acumulável**, não um valor único. Com coluna única, ou
se inventa um valor combinado (`hr_ombudsman`, que multiplica com cada área nova)
ou se dá a algum papel poder sobre tudo por consequência.

**As rotas declaram ÁREA, não papel** (`EnsureAreaAccess:resumes`). Com papel na
rota, a sobreposição do administrador teria de ser repetida em cada grupo — e é aí
que alguém esquece de incluí-la, ou a inclui onde não devia. O mapa papel → área
vive num lugar só, no model `User`.

**Nenhuma rota de conteúdo sem middleware de área.** Um middleware genérico de
"acesso ao portal" deixaria tudo aberto a quem entrasse, e a separação viveria só
no menu — o mesmo que não existir. Há teste percorrendo as rotas e exigindo a área
correta em cada uma.

Separação também **estrutural**: o controller de candidaturas não menciona
`WhistleblowerReport` e o da ouvidoria não menciona `JobApplication`. Fica difícil
vazar de um lado para o outro por descuido quando o código de cada área ignora a
existência da outra — e há teste lendo os dois arquivos para garantir.

O menu mostra só o que a pessoa pode abrir: link que devolve 403 parece defeito, e
ainda revela a quem não deve que a outra área existe.

### Contas

Sem cadastro público. Contas nascem por comando:

```bash
php artisan montec:create-user rh@montecmococa.com.br --name="Maria" --roles=hr
php artisan montec:create-user ouvidoria@montecmococa.com.br --name="Paulo" --roles=ombudsman
php artisan montec:create-user ambos@montecmococa.com.br --name="Ana" --roles=hr,ombudsman
php artisan montec:create-user admin@montecmococa.com.br --name="Carlos" --roles=admin
```

A senha é pedida de forma oculta — nunca como argumento, que ficaria no histórico
do shell e visível em `ps`. Mínimo de 12 caracteres: estas contas abrem currículo e
denúncia. Um papel inválido invalida o comando todo; nada é criado pela metade.

**Ausência de papel é a negação** — não existe papel "none". Conta criada sem papel
não enxerga nada, e papel desconhecido na coluna é descartado em vez de estourar,
porque descartar significa negar, que é o lado seguro para errar.

### Download de currículo: a fronteira crítica

O arquivo mora fora do webroot justamente para não ser alcançável pela web. Esta
rota é a única exceção, e concentra as garantias:

| Garantia | Por quê |
|---|---|
| `auth` + papel `hr` | Estar autenticado não é estar autorizado |
| **`Content-Disposition: attachment`** | Inline, um PDF ou HTML executa no contexto da própria origem: XSS com arquivo que um estranho enviou |
| `X-Content-Type-Options: nosniff` | O navegador não adivinha o tipo pelo conteúdo |
| `Cache-Control: no-store` | Não fica em cache de proxy nem de navegador compartilhado |
| Auditoria antes dos bytes | Ver abaixo |
| 404 se o arquivo sumiu | Pode ter sido expurgado por retenção enquanto a lista ainda o mostrava |

Verificado em navegador real: conta `ombudsman` recebe **403**; depois do logout o
mesmo link responde **302** para o login.

### Auditoria — exclusiva da supervisão

Saber quem abriu o currículo de quem, e quem leu qual denúncia, é informação de
supervisão. Nas mãos de quem opera a área, vira ferramenta para descobrir que um
colega está sendo investigado. Daí a área ser só do `admin`.

A tela filtra por tipo de recurso e por operador, e mostra quando, quem, o quê e
de qual origem.

**Somente leitura.** Não existe rota de edição nem de exclusão — há teste
percorrendo as rotas de auditoria e exigindo que só aceitem `GET`. `created_at`
também não é `fillable` no model: o carimbo de tempo não pode ser forjado por
atribuição em massa.

Quando a retenção já expurgou o registro acessado, a trilha mostra "registro
expurgado" e permanece: ela continua provando QUE houve acesso, mesmo sem poder
dizer a quê.

### A listagem de denúncias não mostra o relato

Havia uma prévia de 120 caracteres ali, e ela **furava a auditoria**: numa
denúncia curta a prévia era o relato inteiro, então bastava abrir a lista para
ler tudo sem deixar registro. Se o motivo de auditar é saber quem leu o quê, não
pode existir caminho que entregue o conteúdo sem passar pelo registro.

A listagem manda assunto, modo e data — e o assunto é um resumo escrito pelo
próprio denunciante. Verificado no navegador: nenhum trecho exclusivo da
descrição aparece no HTML nem nas props da página.

### Trilha de auditoria

Tabela única `portal_access_logs` para as duas áreas — currículo baixado e
denúncia aberta:

```
11/09 21:13 · Paulo Ouvidoria · viewed  (report) · Descarte irregular de solvente · IP 127.0.0.1
11/09 20:56 · Maria RH        · downloaded (resume) · Elisa Rocha · IP 127.0.0.1
```

Duas tabelas paralelas acabariam divergindo: uma ganharia campo que a outra não
tem, e a auditoria ficaria desigual justamente onde precisa ser uniforme.

A denúncia é auditada **na abertura**, não num download: ali o conteúdo sensível é
lido na própria tela.

Atenção ao que este registro significa na ouvidoria: ele identifica quem **leu** a
denúncia — funcionário identificado operando sistema interno — e nunca quem a
**escreveu**. O anonimato do denunciante não é tocado, porque não há dado dele a
registrar. Há teste afirmando que nome e e-mail do denunciante não aparecem na
trilha nem em denúncia identificada.

`UPDATED_AT = null` de propósito: trilha alterável depois não serve como trilha.
E `resource_id` não tem foreign key — o recurso pode ser expurgado por retenção, e
o registro do acesso precisa sobreviver ao dado acessado.

### Sessão

- `http_only` — cookie ilegível por JavaScript, fecha roubo de sessão via XSS
- `SameSite=strict` — o portal não recebe navegação legítima de fora; strict
  impede o cookie de acompanhar requisição originada em outro site, proteção
  contra CSRF que não depende só do token
- `secure` automático em produção
- Sessão regenerada no login (session fixation) e invalidada no logout
- Login limitado a 5 tentativas por e-mail+origem, mais `throttle:10,1` na rota.
  Só por IP, um escritório atrás de NAT se bloqueia sozinho; só por e-mail, um
  atacante distribui e passa
- Mensagem única para e-mail inexistente e senha errada — distinguir os dois
  entrega ao atacante a lista de quem tem conta

---

## Notas de deploy

Diferente do frontend, que é estático: este serviço precisa de PHP 8.3+ e de
`document_root` apontando para **`public/`**, nunca para a raiz do projeto —
apontar para a raiz expõe `.env`, `storage/` e os currículos.

- `npm ci && npm run build` — o portal usa React + Inertia + Tailwind via Vite.
  O `public/build/` precisa existir no servidor; sem ele o portal carrega em branco
- `php artisan config:cache route:cache view:cache` em produção
- `storage/` e `bootstrap/cache/` graváveis
- Agendador: `* * * * * cd /caminho && php artisan schedule:run >> /dev/null 2>&1`
- SMTP e demais credenciais **apenas** no `.env` do servidor

### Limites do PHP

Verificados neste ambiente: `post_max_size=8M`, `upload_max_filesize=2M`.

O segundo **rejeita um currículo de 5 MB em multipart** antes de a requisição
chegar ao Laravel. Ajuste no `php.ini` (ou no seletor de versão de PHP do cPanel):

```ini
upload_max_filesize = 6M   ; acima do limite de 5 MB do currículo
post_max_size       = 10M  ; acima de upload_max_filesize, com folga p/ os campos
expose_php          = Off  ; não anunciar a versão do PHP
```

`post_max_size` de 8M ainda atende o caminho base64 (5 MB viram ~6,7 MB), mas com
pouca folga — outro motivo para o frontend usar multipart.

---
