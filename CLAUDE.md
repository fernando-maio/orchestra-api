# Orchestra - Documentação do Projeto

## Visão do Produto

**Orchestra** é um sistema SaaS para **Gestão Completa de Eventos Corporativos com foco em Fornecedores**.

### Missão
Centralizar a gestão de eventos corporativos, desde o planejamento até a execução, com foco especial na gestão inteligente de fornecedores, compliance e uso de IA para otimizar processos.

### Diferenciais Competitivos
1. **Compliance Vault** - Validação automática de documentos (CNPJ, Alvarás, Seguros)
2. **Price Lock** - Trava orçamentos aceitos, evitando "taxas surpresa"
3. **ESG & Local Sourcing** - Rastreamento de impacto social/ambiental
4. **Modo Emergência** - "Botão de Pânico" para substituição imediata de fornecedores
5. **IA Integrada** - Assistente inteligente em todas as etapas

### Escalabilidade Futura
> **IMPORTANTE**: O sistema está sendo construído para escalar para uma plataforma SaaS completa de gestão de eventos (sem funcionalidades de ERP/fiscal). A arquitetura atual deve suportar:
> - Gestão completa do ciclo de vida do evento
> - Timeline e cronograma de entregas
> - Checklist de tarefas por fase
> - Comunicação centralizada
> - Relatórios executivos e dashboards
> - Templates de eventos reutilizáveis

---

## Modelo de Negócio - Marketplace

### Visão Geral

O Orchestra opera como um **marketplace B2B** conectando empresas de eventos (demanda) a fornecedores (oferta):

```
┌─────────────────────────────────────────────────────────────────┐
│                      MARKETPLACE ORCHESTRA                       │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  FORNECEDORES (Oferta)          CLIENTES (Demanda)              │
│  ├─ Cadastro GRATUITO           ├─ Assinatura PAGA              │
│  ├─ Visíveis para TODOS         ├─ Acesso a todos fornecedores  │
│  ├─ Recebem cotações            ├─ Criam eventos/cotações       │
│  ├─ Podem patrocinar ($$)       └─ Avaliam após contrato        │
│  └─ Não precisam de login                                       │
│     para responder cotações                                     │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Fluxos de Entrada de Fornecedores

| Fluxo | Descrição | Status Inicial |
|-------|-----------|----------------|
| **Self-Service** | Fornecedor acessa `/fornecedor/cadastro` e preenche formulário | `pending_approval` |
| **Importação em Lote** | Admin faz upload de CSV/Excel com lista de fornecedores | `approved` |
| **Convite por Cliente** | Empresa de eventos convida fornecedor por email | `approved` |
| **Cadastro por Admin** | Admin cadastra manualmente | `approved` |

### Status de Aprovação do Fornecedor

| Status | Descrição | Visível para clientes? |
|--------|-----------|------------------------|
| `pending` | Aguardando revisão do admin | Não |
| `approved` | Aprovado, pode receber cotações | Sim |
| `rejected` | Não aprovado (com motivo) | Não |
| `suspended` | Temporariamente suspenso | Não |

### Modelo de Patrocínio (Fornecedores)

| Nível | Benefícios | Preço Sugerido |
|-------|------------|----------------|
| **Free** | Listagem básica, recebe cotações | R$ 0 |
| **Destaque** | Badge especial, prioridade em buscas, perfil expandido | R$ 99/mês |
| **Premium** | Tudo acima + aparecer no topo das categorias | R$ 299/mês |

---

## Sistema de Avaliações

> **Status: especificado, NÃO implementado.** A tabela `vendor_ratings` existe
> e está bem modelada (4 dimensões, `contract_value` para a ponderação, fluxo
> completo de contestação), o Model e a Factory existem — mas **não há nenhum
> endpoint e nenhum registro no banco**.
>
> As estrelas que aparecem na listagem e no detalhe do fornecedor vêm de
> `vendors.average_rating` e `vendors.total_ratings`, colunas desnormalizadas
> **preenchidas com dados fictícios pelo seeder**. Nenhuma avaliação real
> existe no sistema.
>
> As regras abaixo são o desenho a implementar, não o comportamento atual.


### Regras Anti-Fraude

| Regra | Descrição |
|-------|-----------|
| **Vinculada a contrato** | Só avalia quem contratou (proposta `contracted` ou `completed`) |
| **Uma por contrato** | Máximo uma avaliação por proposta/contrato |
| **Período limitado** | 30 dias após conclusão do evento |
| **Peso por valor** | Contratos maiores têm mais peso no rating |
| **Detecção de anomalias** | Alerta se cliente der muitas notas baixas consecutivas |
| **Contestação** | Fornecedor pode contestar (admin revisa) |

### Cálculo do Rating

```
Rating Final = Σ (nota × peso_contrato) / Σ peso_contrato

Onde: peso_contrato = log10(valor_contrato + 1)
```

### Dimensões da Avaliação

| Dimensão | Descrição |
|----------|-----------|
| **Qualidade** | Qualidade do serviço/produto entregue |
| **Pontualidade** | Cumprimento de prazos |
| **Comunicação** | Facilidade de comunicação e resposta |
| **Custo-Benefício** | Relação entre preço e qualidade |

### Status da Avaliação

| Status | Descrição |
|--------|-----------|
| `active` | Avaliação válida e visível |
| `disputed` | Sob contestação do fornecedor |
| `removed` | Removida por admin após análise |

---

## APIs de Fornecedores (Marketplace)

> **Estado real, conferido no `route:list`.** Esta seção já listou endpoints
> que nunca existiram; o que está marcado como planejado ainda não tem rota.

### Implementado

```
POST /api/public/vendors/register        → Cadastro self-service (sem auth)
GET  /api/public/vendors/categories      → Categorias para o formulário público
POST /api/public/vendors/check-cnpj      → Verifica CNPJ duplicado
POST /api/public/vendors/check-email     → Verifica e-mail duplicado

GET    /api/vendors                      → Lista (filtros: approval_status, categoria, cidade…)
POST   /api/vendors                      → Cria
GET    /api/vendors/{id}                 → Exibe
PUT    /api/vendors/{id}                 → Atualiza
DELETE /api/vendors/{id}                 → Remove
POST   /api/vendors/{id}/approve         → Aprova
POST   /api/vendors/{id}/reject          → Rejeita (com motivo)
POST   /api/vendors/{id}/toggle-active   → Ativa/desativa  ⚠️ ver ressalva abaixo
POST   /api/vendors/{id}/verify          → Marca como verificado
POST   /api/vendors/{id}/subscription-tier → Altera o plano de patrocínio
GET    /api/vendors/{id}/compliance      → Situação dos documentos
GET    /api/vendors/nearby               → Busca por geolocalização
GET    /api/vendors/by-category/{id}     → Filtra por categoria
```

> ⚠️ **`toggle-active` hoje é uma desativação GLOBAL.** Ver a seção
> "Vínculo Organização ↔ Fornecedor" abaixo.

### Planejado (sem rota ainda)

```
GET  /api/vendors/invite/{token}     → Verificar convite
POST /api/vendors/invite/{token}     → Completar cadastro via convite
GET  /api/admin/vendors/pending      → Fila de aprovação
POST /api/admin/vendors/import       → Importar CSV/Excel
GET  /api/admin/vendors/export       → Exportar lista
POST /api/vendors/{id}/ratings       → Criar avaliação
GET  /api/vendors/{id}/ratings       → Listar avaliações
POST /api/ratings/{id}/dispute       → Contestar avaliação
POST /api/admin/ratings/{id}/resolve → Resolver contestação
```

---

## Vínculo Organização ↔ Fornecedor

> **Esta é uma inconsistência conhecida entre o modelo de negócio e o código.**
> Está documentada aqui para não ser reintroduzida por engano, e a correção
> está na Fase 3 do roadmap.

O modelo de marketplace diz que fornecedores são **globais**: cadastram-se uma
vez e ficam visíveis para todas as organizações. O código não reflete isso.

**Como está hoje:**

| Item | Situação |
|---|---|
| `vendors.organization_id` | FK única e anulável. Todos os fornecedores semeados apontam para a Empresa Demo |
| `Vendor` + `BelongsToOrganization` | O model **não aplica** o trait — só o `Event` aplica. Fornecedores não são filtrados por organização |
| `vendors.is_active` | Coluna **global**. `toggle-active` desliga o fornecedor para **todo o marketplace** |
| Quem pode desativar | Qualquer usuário com `vendors.update`, de **qualquer** organização |

O efeito prático: um admin da Organização A pode desativar um fornecedor e
tirá-lo do ar para as Organizações B, C e D. Isso não é o comportamento
desejado num marketplace.

**Modelo alvo** (Fase 3):

```
vendors                      → catálogo GLOBAL, sem organization_id
  ├─ is_active               → status na PLATAFORMA (só super-admin altera)
  └─ approval_status         → moderação da plataforma (pending/approved/…)

organization_vendor (pivot N-N)   → a relação de CADA organização com o fornecedor
  ├─ organization_id
  ├─ vendor_id
  ├─ relationship_status     → linked | preferred | blocked
  ├─ internal_alias          → apelido interno do cliente
  ├─ notes                   → observações privadas da organização
  └─ linked_at / unlinked_at
```

Com isso:
- **A organização desvincula** o fornecedor da própria carteira
  (`relationship_status = 'blocked'` ou remove a linha do pivot). Efeito local.
- **Só a plataforma desativa** globalmente (`vendors.is_active`), restrito a
  super-admin. Efeito global, e é uma decisão de moderação.

A avaliação também se escopa por esse pivot: `vendor_ratings` já tem
`organization_id`, então "a nota que a organização X deu" e "a nota agregada
do marketplace" passam a ser coisas distintas e coerentes.

---

## Documentação de Requisitos

Os documentos de requisitos estão em `/home/fernando/Projects/orchestra/docs/`:
- `1. Orchestra - Documento 1.pdf` - Visão geral do sistema
- `2. Dores do Mercado e Uso de IA.pdf` - Análise de mercado e uso de IA
- `3. Jornada do Usuário.pdf` - UX e fluxos de usuário
- `4. Entidades Principais e Atributos - Iniciais.pdf` - Modelo de dados
- `5. Fluxos de sistema.pdf` - Fluxos e regras de negócio
- `Documento de Visão_ Projeto Orchestra.pdf` - Visão executiva

---

## Uso de Inteligência Artificial

### IA como Assistente de Produtividade

| Funcionalidade | Descrição | Status |
|----------------|-----------|--------|
| **Briefing AI** | Analisa descrição do evento e sugere categorias de fornecedores necessárias | Planejado |
| **OCR de Propostas** | Extrai dados de PDFs de orçamentos para tabulação automática | Planejado |
| **Análise de Risco** | Score de confiabilidade baseado em histórico de entregas | Planejado |
| **Sugestão de Fornecedores** | Recomenda fornecedores baseado em eventos similares anteriores | Planejado |
| **IA Preditiva** | Alertas proativos (ex: "40% chance de falta de geradores nesta região") | Futuro |
| **Comparação Inteligente** | Destaca automaticamente melhor preço, prazo e compliance | Planejado |
| **Geração de Briefing** | Gera briefing técnico para RFP baseado no tipo de evento | Planejado |

### Implementação Técnica de IA

```
backend/app/
├── Services/
│   └── AI/
│       ├── BriefingAnalyzerService.php    # Analisa briefing e sugere categorias
│       ├── ProposalExtractorService.php   # OCR de PDFs de propostas
│       ├── VendorRecommenderService.php   # Recomendação de fornecedores
│       └── RiskAnalyzerService.php        # Análise de risco/confiabilidade
├── Contracts/Services/AI/
│   └── AIServiceInterface.php             # Interface para trocar providers
```

**Provider de IA recomendado**: Claude API (Anthropic) ou OpenAI GPT-4
**Fallback**: Regras heurísticas quando IA não disponível

---

## Stack Tecnológico

### Backend
- **Laravel 13** (API-only mode)
- **PHP 8.5** com PHP-FPM
- **MySQL 8.4 LTS** para banco de dados
- **Redis** para cache, sessão e filas
- **Laravel Sanctum** para autenticação API
- **Spatie Permission** para RBAC (roles e permissions)

### Frontend
- **Vue 3** com Composition API
- **TypeScript 6** (ver nota sobre TS 7 abaixo)
- **Vite 8** como bundler (Rolldown)
- **Tailwind CSS 4** (com @tailwindcss/vite plugin)
- **Pinia 4** para state management
- **Vue Router 5** para rotas
- **Axios** para requisições HTTP
- **Node 24 LTS** no container

> **TypeScript 7**: o `vue-tsc` ainda não funciona com o TS 7 (a reescrita em Go
> removeu `./lib/tsc` dos exports do pacote, caminho que o `vue-tsc` usa para
> instrumentar o compilador — erro `ERR_PACKAGE_PATH_NOT_EXPORTED`). O projeto
> está no **TS 6.0.3**, a linha-ponte oficial: mesma base JS do 5.9, porém com as
> deprecations do 7 já ativas. Reavaliar o TS 7 quando o `vue-tsc` publicar suporte.

### Infraestrutura
- **Docker** com docker-compose
- **Nginx** como reverse proxy
- **Mailpit** para testes de email em desenvolvimento (sucessor do Mailhog)

---

## Padrões Obrigatórios de Desenvolvimento

> **Leia antes de escrever código.** Estes padrões foram acordados com o
> cliente e valem para tudo — passo do roadmap ou ajuste avulso. Ao terminar
> qualquer trabalho, rode **`/validar`** antes de commitar (o comando está em
> `backend/.claude/commands/validar.md`).

### Camadas — nada de lógica no Controller

```
Request → Controller → Service → Repository → Database
              ↓            ↓
        FormRequest    Interface (Contract)
```

| Onde | O que pode | O que NÃO pode |
|---|---|---|
| Controller | Receber, delegar, responder | Regra de negócio, `Model::where()`, `save()` |
| Service | Regra de negócio, orquestração, transação | Montar resposta HTTP |
| Repository | Acesso a dados, queries | Regra de negócio |
| FormRequest | Validação e autorização de entrada | Efeito colateral |

Todo Service e Repository tem **interface em `app/Contracts/`** e binding no
`RepositoryServiceProvider`. O Controller injeta a **interface**.

### Transações

Toda operação que escreve em **mais de um lugar** vai dentro de
`DB::transaction()`. Exemplo real: trocar a senha e revogar as sessões — sem a
transação, uma falha no meio deixaria senha nova com sessão antiga válida,
pior que não ter trocado. Escrita única não precisa.

### Tratamento de erro nos Controllers

`try/catch` para o que pode falhar por causa externa (I/O, serviço terceiro,
integridade referencial), devolvendo resposta adequada.

**Não** adicione try/catch onde o framework já trata:
`ModelNotFoundException` → 404, `ValidationException` → 422,
`AuthorizationException` → 403. E nunca engula exceção para o teste passar.

### Defesa em profundidade

Regra de negócio crítica vale **também no Service**, não só no FormRequest. O
Service é a última barreira antes da escrita e não pode depender de quem o
chama ter validado direito. Exemplo: o e-mail não é alterável — o
`UpdateProfileRequest` não aceita o campo **e** o `UserService` faz `unset()`.

### Testes — na camada onde a regra mora

| O que mudou | Teste obrigatório |
|---|---|
| Service / Repository | Unitário em `tests/Unit/` |
| Endpoint | Feature em `tests/Feature/Api/` (feliz + validação + **permissão**) |
| Componente / lógica de view | Vitest em `__tests__/` |
| Fluxo de usuário novo | Spec E2E |

> **Se você validou algo com script de browser descartável, isso precisa virar
> teste.** Script confirma uma vez; teste protege todo dia. Essa lacuna já
> aconteceu neste projeto e custou caro.

### Frontend

- Sem `any` — use `unknown` + helpers de `@/types/api-error`
- Ação protegida usa `auth.hasPermission()`, e a rota tem `meta.permission`
- Texto de UI em **pt-BR com acentuação correta**
- Lógica de transformação em `computed`/função pura, para testar sem montar a view

### Ferramentas de qualidade

```bash
docker exec orchestra-php ./vendor/bin/pint --test        # estilo PHP
docker exec orchestra-php ./vendor/bin/phpstan analyse    # análise estática (Larastan, nível 5)
docker exec orchestra-frontend npm run lint               # ESLint
docker exec orchestra-frontend npx vue-tsc --noEmit -p tsconfig.app.json
```

> `phpstan-baseline.neon` congela os **193 erros** que já existiam quando a
> análise foi introduzida, para que a ferramenta cobre apenas código novo.
> **Nunca regenere o baseline para fazer um erro novo desaparecer** — isso
> esconde o problema. Reduzi-lo é item próprio do roadmap.

---

## Arquitetura do Sistema

### Padrão SOLID + Clean Architecture

```
Request → Controller → Service → Repository → Database
              ↓
         Interface (Contract)
```

- **Controller**: Apenas recebe requisição e delega ao Service
- **Interface**: Define contratos para inversão de dependência
- **Service**: Lógica de negócio (cálculos, regras, validações)
- **Repository**: Acesso a dados (Eloquent, queries complexas)
- **Resource**: Transformação de dados para API responses
- **Request**: Validação de entrada

### Estrutura de Diretórios

```
/home/fernando/Projects/orchestra/
├── backend/                    # Laravel API (+ infra do projeto)
│   ├── CLAUDE.md               # Esta documentação (symlink na raiz)
│   ├── docker-compose.yml      # Orquestra TODOS os serviços
│   ├── docker/
│   │   ├── nginx/
│   │   └── php/                # Dockerfile PHP 8.5
│   ├── app/
│   │   ├── Contracts/          # Interfaces (centralizadas)
│   │   │   ├── Repositories/
│   │   │   └── Services/
│   │   ├── Http/
│   │   │   ├── Controllers/Api/
│   │   │   ├── Requests/
│   │   │   └── Resources/
│   │   ├── Models/
│   │   ├── Repositories/
│   │   ├── Services/
│   │   │   └── AI/             # Serviços de IA (futuro)
│   │   ├── Providers/
│   │   └── Traits/
│   ├── config/
│   ├── database/
│   │   ├── migrations/
│   │   └── seeders/
│   └── routes/
├── frontend/                   # Vue 3 SPA
│   ├── src/
│   │   ├── assets/
│   │   ├── components/
│   │   ├── composables/        # Hooks reutilizáveis
│   │   ├── router/
│   │   ├── services/
│   │   ├── stores/
│   │   ├── types/
│   │   └── views/
│   └── vite.config.ts
└── docs/                       # Documentação de requisitos (PDFs)
```

> **Onde fica a infra**: `docker-compose.yml` e `docker/` vivem dentro de
> `backend/` porque a raiz do projeto não é um repositório git — deixá-los lá
> fora significava que o Dockerfile e o compose não eram versionados em lugar
> nenhum. O compose fixa `name: orchestra` no topo, então os volumes continuam
> sendo `orchestra_*` independentemente do diretório de onde é chamado.
> O `CLAUDE.md` tem um symlink na raiz para continuar sendo carregado de lá.

---

## Modelo de Dados

### Entidades Atuais

| Entidade | Responsabilidade | Status |
|----------|------------------|--------|
| **Organization** | Multi-tenancy (empresa/consultoria) | Implementado (Model + Factory + CRUD) |
| **User** | Usuários do sistema | Implementado (Model + Factory + Auth) |
| **Category** | Categorias de fornecedores | Implementado (Model + Factory + CRUD + SoftDeletes) |
| **Vendor** | Fornecedores cadastrados | Implementado (Model + Factory + CRUD + Approval) |
| **Event** | Eventos sendo organizados | Implementado (Model + Factory + CRUD + Status) |
| **QuoteRequest** | Solicitações de cotação (RFP) | Model + Migration + Factory (API em Fase 2) |
| **Proposal** | Propostas dos fornecedores | Model + Migration + Factory (API em Fase 2) |
| **ProposalHistory** | Histórico de mudanças de proposta | Model + Migration + Factory |
| **VendorDocument** | Documentos de compliance | Model + Migration + Factory |
| **VendorRating** | Avaliações de fornecedores | Model + Migration + Factory |

### Entidades Futuras (Gestão Completa de Eventos)

| Entidade | Responsabilidade | Prioridade |
|----------|------------------|------------|
| **EventPhase** | Fases do evento (Pré, Montagem, Evento, Desmontagem, Pós) | Alta |
| **EventTask** | Checklist de tarefas por fase | Alta |
| **EventTimeline** | Cronograma de entregas (Gantt simplificado) | Alta |
| **EventTeamMember** | Membros da equipe do evento | Média |
| **EventNote** | Anotações e comunicação interna | Média |
| **EventAttachment** | Documentos do evento | Média |
| **EventBudgetItem** | Detalhamento do orçamento por categoria | Alta |
| **EventTemplate** | Templates reutilizáveis | Baixa |
| **Message** | Chat contextual por cotação/proposta | Média |

### UUIDs

Todas as tabelas usam UUID como primary key:
- Trait `HasUuid` nos Models
- Migration `personal_access_tokens` usa `uuidMorphs('tokenable')`
- Migration `permission_tables` usa `uuid()` para `model_morph_key`

---

## APIs Implementadas

### CategoryController
- `GET /api/categories` - Lista categorias
- `POST /api/categories` - Cria categoria
- `GET /api/categories/{id}` - Exibe categoria
- `PUT /api/categories/{id}` - Atualiza categoria
- `DELETE /api/categories/{id}` - Remove categoria
- `POST /api/categories/{id}/toggle-active` - Ativa/desativa
- `POST /api/categories/reorder` - Reordena

### VendorController
- `GET /api/vendors` - Lista com filtros e paginação
- `POST /api/vendors` - Cria fornecedor
- `GET /api/vendors/{id}` - Exibe fornecedor
- `PUT /api/vendors/{id}` - Atualiza fornecedor
- `DELETE /api/vendors/{id}` - Remove fornecedor
- `POST /api/vendors/{id}/toggle-active` - Ativa/desativa
- `POST /api/vendors/{id}/verify` - Marca como verificado
- `GET /api/vendors/{id}/compliance` - Status de compliance
- `GET /api/vendors/nearby` - Busca por geolocalização
- `GET /api/vendors/by-category/{id}` - Filtra por categoria

### EventController
- `GET /api/events` - Lista com filtros e paginação
- `POST /api/events` - Cria evento
- `GET /api/events/{id}` - Exibe evento com quote requests
- `PUT /api/events/{id}` - Atualiza evento
- `DELETE /api/events/{id}` - Remove evento
- `POST /api/events/{id}/status` - Altera status
- `POST /api/events/{id}/duplicate` - Duplica evento
- `GET /api/events/{id}/statistics` - Estatísticas do evento
- `GET /api/events/upcoming` - Próximos eventos

### DashboardController (Cliente)
- `GET /api/dashboard` - Dashboard completo do cliente
- `GET /api/dashboard/stats` - Estatísticas do cliente
- `GET /api/dashboard/budget-overview` - Visão geral de orçamentos
- `GET /api/dashboard/proposals-by-status` - Propostas por status

### AdminDashboardController (Super Admin)
- `GET /api/admin/dashboard` - Dashboard completo da plataforma
- `GET /api/admin/dashboard/stats` - Métricas da plataforma (MRR, GMV, etc.)
- `GET /api/admin/dashboard/organizations` - Top organizações
- `GET /api/admin/dashboard/vendors` - Top fornecedores
- `GET /api/admin/dashboard/geographic` - Distribuição geográfica
- `GET /api/admin/dashboard/categories` - Categorias mais demandadas

---

## Configuração do Docker

### Portas (evitar conflito com outros projetos)

| Serviço | Porta Externa | Porta Interna |
|---------|---------------|---------------|
| Nginx (API) | 8001 | 80 |
| Frontend | 3001 | 3000 |
| MySQL | 3308 | 3306 |
| Redis | 6380 | 6379 |
| Mailpit SMTP | 1026 | 1025 |
| Mailpit UI | 8026 | 8025 |

### Comandos Docker Essenciais

```bash
# Subir ambiente (compose vive em backend/)
cd /home/fernando/Projects/orchestra/backend
docker compose up -d

# Ver logs
docker-compose logs -f [service]

# Reiniciar serviço
docker-compose restart [service]

# Executar comandos no container PHP
docker exec orchestra-php php artisan [command]

# Acessar shell do container
docker exec -it orchestra-php bash

# Rebuild completo
docker-compose down && docker-compose up -d --build
```

### Comandos NPM (SEMPRE via Docker!)

> **IMPORTANTE**: Nunca executar comandos npm diretamente no diretório local!
> Os pacotes devem ser instalados dentro do container Docker para funcionar corretamente.

> O container roda `npm ci` no boot (e não `npm install`): ele instala exatamente
> o que está no `package-lock.json`, o que torna o ambiente reprodutível. Como
> efeito colateral, `npm ci` **falha** se o `package.json` e o lock estiverem
> fora de sincronia — nesse caso rode `npm install` uma vez para reconciliar o
> lock e commite o resultado.

```bash
# Reinstalar exatamente o que está no lock (o que o container faz no boot)
docker exec orchestra-frontend npm ci

# Instalar novo pacote (atualiza o package.json e o lock)
docker exec orchestra-frontend npm install <package-name>

# Instalar pacote de desenvolvimento
docker exec orchestra-frontend npm install -D <package-name>

# Rodar build
docker exec orchestra-frontend npm run build

# Rodar lint (ESLint)
docker exec orchestra-frontend npm run lint
docker exec orchestra-frontend npm run lint:fix

# Verificar pacotes desatualizados
docker exec orchestra-frontend npm outdated

# Após instalar pacotes, reiniciar o frontend
docker-compose restart frontend
```

**Por que não rodar npm localmente?**
- O volume do Docker mapeia `./frontend:/app` com `/app/node_modules` anônimo
- Pacotes instalados localmente não são visíveis dentro do container
- Isso causa erros como "Failed to resolve import" em tempo de execução

### Comandos Artisan Frequentes

```bash
# Limpar caches
docker exec orchestra-php php artisan config:clear
docker exec orchestra-php php artisan route:clear
docker exec orchestra-php php artisan cache:clear

# Migrations
docker exec orchestra-php php artisan migrate:fresh --seed

# Tinker (REPL)
docker exec orchestra-php php artisan tinker

# Listar rotas
docker exec orchestra-php php artisan route:list
```

---

## Configurações Importantes

### Backend .env

```env
# Portas internas Docker (NÃO usar portas externas!)
DB_HOST=mysql
DB_PORT=3306        # NÃO 3308!
REDIS_HOST=redis
REDIS_PORT=6379     # NÃO 6380!

# IMPORTANTE: NÃO usar SANCTUM_STATEFUL_DOMAINS com auth baseada em token!
# Isso causa erro de CSRF token mismatch

# IA (futuro)
# OPENAI_API_KEY=sk-...
# ANTHROPIC_API_KEY=sk-ant-...
```

### CORS (config/cors.php)

```php
'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:3001')],
'supports_credentials' => false,
```

### Autenticação

O sistema usa **autenticação baseada em tokens** (Bearer Token):
- Login retorna um token que é armazenado no localStorage
- Todas as requisições autenticadas enviam `Authorization: Bearer {token}`
- **NÃO** usar `withCredentials: true` no Axios (evita CSRF issues)
- Registro público desabilitado - apenas admins criam usuários

### Tailwind CSS v4

No Tailwind v4:
- Usar `@tailwindcss/vite` plugin no vite.config.ts
- Importar com `@import "tailwindcss"` no CSS
- Configurar tema com `@theme { }` usando CSS variables
- **NÃO usar** `@apply` com classes customizadas do mesmo arquivo

---

## Acesso de Fornecedor

> **Fornecedores não têm acesso ao sistema.** Verificado: a tabela `users` não
> tem `vendor_id`, não existe role `vendor`, e não há magic link implementado.
>
> O único ponto de contato do fornecedor hoje é o formulário público de
> auto-cadastro — ele preenche, o registro entra como `pending`, e acabou.
> Ele não acompanha o status, não recebe cotação e não responde proposta.
>
> O desenho previsto (Fase 2) é acesso por **magic link**, sem senha: o
> fornecedor recebe um link por e-mail para responder a uma cotação
> específica. Nada disso foi construído.

---

## Autenticação e Autorização

### Roles

| Role | Descrição |
|------|-----------|
| super-admin | Acesso total, gerencia organizations |
| admin | Administrador da organization |
| organizer | Organiza eventos, gerencia cotações |
| viewer | Apenas visualização |

### Usuários de Teste

| Email | Senha | Role |
|-------|-------|------|
| admin@orchestra.local | password | super-admin |
| demo@orchestra.local | password | admin |
| organizer@orchestra.local | password | organizer |
| viewer@orchestra.local | password | viewer |

Os três últimos pertencem à mesma organização (Empresa Demo), para que a
diferença observada entre eles seja de **permissão** e não de multi-tenancy.
O super-admin não pertence a organização nenhuma.

### Permissões

O sistema usa Spatie Permission com as seguintes permissões:
- organizations.* (super-admin only)
- users.*, events.*, vendors.*, categories.*
- quote-requests.*, proposals.*
- reports.*, settings.*

---

## URLs de Acesso (Desenvolvimento)

| Serviço | URL |
|---------|-----|
| Frontend | http://localhost:3001 |
| Backend API | http://localhost:8001/api |
| API Health | http://localhost:8001/api/health |
| Mailpit | http://localhost:8026 |

---

## Ambiente de Produção

O arquivo `.env.production` (na raiz de `backend/`) carrega a configuração de
produção. O Laravel só o encontra se `APP_ENV` estiver no **ambiente do
processo** — não adianta declarar dentro de um `.env`, porque ele é lido antes
de qualquer arquivo ser aberto (`LoadEnvironmentVariables::checkForSpecificEnvironmentFile`).

O `docker-compose.yml` repassa a variável para o container do PHP:

```bash
# Sobe usando .env.production
APP_ENV=production docker compose up -d

# Sem a variável, cai no default 'local' e usa o .env de sempre
docker compose up -d
```

Comprovação:

```
sem APP_ENV        -> env=local      | url=http://localhost:8001            | debug=true
APP_ENV=production -> env=production | url=https://api.orchestra.example... | debug=false
```

> **Por que `production` e não `prod`**: o Laravel monta o nome do arquivo como
> `.env.` + o valor de `APP_ENV`, então `APP_ENV=prod` procuraria `.env.prod`.
> Só que `App::isProduction()` compara com a string `'production'` — com `prod`
> ele retorna `false` e as salvaguardas de produção (confirmação em `migrate`,
> comportamento das páginas de erro) ficam desligadas em silêncio.

> **`.env.production` está no `.gitignore`** (linha 5, padrão do Laravel). Isso é
> proposital: segredos reais não vão para o repositório. Os campos marcados como
> `# ALTERAR` devem vir do cofre de segredos do deploy.

> **`docker-compose.yml` é de desenvolvimento**, mesmo com `APP_ENV=production`:
> ele monta o código como volume. Para o deploy de verdade use o
> `docker-compose.prod.yml`, descrito abaixo.

### Stack de produção

```bash
cp .env.deploy.example .env.deploy     # variaveis do Compose (segredos do banco, portas)
# .env.production carrega a config da aplicacao
docker compose -f docker-compose.prod.yml --env-file .env.deploy up -d --build
docker exec orchestra-prod-php php artisan migrate --force
```

> **O `--env-file` não é opcional.** Sem ele o Compose usaria o `.env` de
> desenvolvimento deste diretório para interpolar as variáveis, injetando
> credenciais de dev na stack de produção.

Há **dois** arquivos de ambiente, com papéis distintos:

| Arquivo | Quem lê | Para quê |
|---|---|---|
| `.env.production` | Laravel, em runtime | Config da aplicação (APP_KEY, DB, mail, CORS) |
| `.env.deploy` | Docker Compose, antes de subir | Senha root do MySQL, portas, `VITE_API_URL` |

Precisam ser separados porque o Compose interpola as variáveis dele **antes**
de qualquer container existir — inclusive para criar o banco.

Diferenças em relação ao ambiente de desenvolvimento:

| | Desenvolvimento | Produção |
|---|---|---|
| Código | bind mount (`.:/var/www/html`) | copiado para a imagem |
| Frontend | Vite dev server (Node) | estáticos servidos por nginx (~104MB) |
| Dependências | inclui dev (phpunit, sail) | `--no-dev`, sem `tests/` |
| Autoloader | padrão | `--optimize --classmap-authoritative` |
| OPcache | `validate_timestamps=On` | `Off` + JIT tracing |
| Caches | nenhum | config, rotas e eventos, gerados no entrypoint |
| MySQL/Redis | portas expostas | sem porta no host |
| E-mail | Mailpit | SMTP real |
| `expose_php` | On | Off |

> O `docker/php/Dockerfile` tem **um `base` compartilhado** e dois alvos, `dev`
> e `prod`. As extensões PHP ficam só no `base`, de propósito: com dois
> Dockerfiles separados, uma correção como o retry do `pecl` teria que ser
> mantida em dois lugares e as versões acabariam divergindo.

> Os caches (`config:cache`, `route:cache`, `event:cache`) são gerados no
> **entrypoint**, não no build. `config:cache` congela os valores do `.env`, e o
> `.env` só existe em runtime — gerar no build assaria a configuração de quem
> buildou dentro da imagem.

> **Ainda não existe um ambiente de produção real.** A stack acima foi validada
> apenas localmente (portas 8080/8081), e o `.env.production` está preenchido
> com valores dessa simulação.

---

## Dashboards Diferenciados

### Visão Geral

O sistema possui **dois dashboards distintos** baseados no tipo de usuário:

| Dashboard | Usuário | Foco |
|-----------|---------|------|
| **Super Admin** | Orchestra (plataforma) | Gestão de clientes, receita, saúde do marketplace |
| **Cliente** | Organizações | Sucesso dos eventos, controle de budget, propostas |

### Biblioteca de Gráficos

**ApexCharts** (`vue3-apexcharts`) - escolhida por:
- 16+ tipos de gráficos (Area, Bar, Line, Donut, Heatmap, Radar, etc.)
- Zoom, pan e export embutidos
- Animações e tooltips interativos
- Excelente compatibilidade com Vue 3

### Dashboard Super Admin (Orchestra)

**Rota**: `/admin/dashboard`
**Controller**: `AdminDashboardController`

#### Métricas Principais (Cards)
| Métrica | Descrição |
|---------|-----------|
| MRR | Receita mensal recorrente de assinaturas |
| Total Organizações | Clientes ativos na plataforma |
| GMV | Volume total de transações cliente↔fornecedor |
| Fornecedores Ativos | Total de vendors cadastrados e verificados |
| Taxa de Conversão | % de cotações que viram contratos |
| Churn Rate | % de clientes que cancelaram |

#### Gráficos
- **MRR Evolution** (Area Chart) - crescimento mensal da receita
- **Receita por Plano** (Donut Chart) - Free vs Pro vs Enterprise
- **Novos Clientes vs Churn** (Combo Chart) - aquisição vs perda
- **GMV Mensal** (Area Chart) - volume de transações
- **Distribuição Geográfica** (Heatmap) - concentração por estado
- **Categorias Mais Demandadas** (Horizontal Bar) - Buffet, Som, etc.
- **Vendor Response Rate** (Gauge) - tempo médio de resposta

#### Insights Acionáveis
- Alerta de Churn: clientes sem atividade há X dias
- Vendors com problemas: baixa taxa de resposta ou avaliações ruins
- Oportunidades: regiões com alta demanda e poucos fornecedores

### Dashboard Cliente (Organização)

**Rota**: `/dashboard`
**Controller**: `DashboardController`

#### Métricas Principais (Cards)
| Métrica | Descrição |
|---------|-----------|
| Eventos Ativos | Eventos em andamento |
| Orçamento Total | Soma dos orçamentos dos eventos |
| Gasto Realizado | Total já comprometido com fornecedores |
| Economia Estimada | Diferença entre orçamento e gasto real |
| Propostas Pendentes | Aguardando análise |
| Próximo Evento | Countdown para o evento mais próximo |

#### Gráficos
- **Budget vs Realizado** (Progress Bar) - por evento
- **Gastos por Categoria** (Donut Chart) - Buffet 40%, Som 20%, etc.
- **Timeline de Eventos** (Gantt/Timeline) - visualização temporal
- **Propostas por Status** (Stacked Bar) - Pendente/Aceita/Rejeitada
- **Histórico de Gastos** (Line Chart) - últimos 12 meses
- **Comparativo de Propostas** (Radar Chart) - Preço vs Qualidade vs Prazo

#### Insights Acionáveis
- "Você está 15% acima do orçamento no evento X"
- "3 propostas aguardam sua análise há mais de 48h"
- "Baseado em eventos similares, você pode economizar R$ 5.000 em catering"

### Princípios de Visualização

1. **Area Charts** → Tendências ao longo do tempo
2. **Donut Charts** → Proporções claras (onde está o dinheiro)
3. **Progress Bars** → Status imediato (estou dentro do orçamento?)
4. **Heatmaps** → Padrões geográficos
5. **Combo Charts** → Correlações (aquisição vs churn)
6. **Radar Charts** → Comparações multidimensionais

---

## Repositórios GitHub

| Repositório | URL |
|-------------|-----|
| **Backend (API)** | `git@github.com:fernando-maio/orchestra-api.git` |
| **Frontend (SPA)** | `git@github.com:fernando-maio/orchestra-frontend.git` |

### Branches
- `main` - Branch de produção (releases estáveis)
- `develop` - Branch de trabalho (desenvolvimento ativo)

### Tags
- `v1.0.0` - MVP Core completo

---

## Roadmap de Desenvolvimento

### Fase 1 - MVP Core (COMPLETO - v1.0.0)

#### Infraestrutura
- [x] Setup Docker (Nginx, PHP-FPM, MySQL 8, Redis, Mailhog, Frontend)
- [x] Laravel 12 API-only com PHP 8.2
- [x] Vue 3 + TypeScript + Vite 7 + Tailwind CSS 4
- [x] Autenticação Sanctum com tokens (expiração 8h)
- [x] RBAC com Spatie Permission (4 roles, 20+ permissions)
- [x] Multi-tenancy via BelongsToOrganization global scope
- [x] UUIDs como primary keys em todas as tabelas

#### Backend - APIs
- [x] AuthController (login, logout, me, profile, password)  <!-- profile/password implementados só em 2026-09-04 -->
- [x] CategoryController + Service + Repository (CRUD, toggle, reorder, SoftDeletes)
- [x] VendorController + Service + Repository (CRUD, filtros, geolocation, approval, compliance)
- [x] EventController + Service + Repository (CRUD, status transitions, duplicate, statistics)
- [x] DashboardController (stats, budget, proposals-by-status - scoped por organização)
- [x] AdminDashboardController (MRR, GMV, top orgs/vendors, geographic, categories - N+1 otimizado)
- [x] Public vendor registration (self-service, CNPJ/email check)
- [x] Rate limiting no login (5 req/min)

#### Frontend - Views
- [x] LoginView (credenciais demo apenas em DEV)
- [x] DashboardView com gráficos ApexCharts
- [x] AdminDashboardView com métricas da plataforma
- [x] EventsListView (listagem, filtros, paginação)
- [x] EventFormView (create/edit, categorias, estados BR, CEP)
- [x] EventDetailView (detalhes, estatísticas, status actions, duplicate)
- [x] VendorsListView (listagem, filtros, paginação)
- [x] VendorFormView (create/edit, CNPJ mask, categorias)
- [x] VendorDetailView (perfil completo, compliance, approve/reject)
- [x] VendorRegisterView (cadastro público self-service)
- [x] CategoriesListView (listagem, toggle)
- [x] SettingsView (perfil, troca de senha)
- [x] Navbar, Sidebar, StatsCard, ConfirmModal (componentes reutilizáveis)
- [x] Placeholder views para Fase 2 (Quotes, Proposals - informativos)

#### Testes
- [x] 483 testes backend (PHPUnit - Unit + Feature, SQLite in-memory)
- [x] 50 testes frontend (Vitest - stores + services)
- [x] 20 specs E2E (Playwright - auth, dashboard, events, vendors, navigation)
- [x] 10 factories (Organization, User, Category, Vendor, Event, QuoteRequest, Proposal, ProposalHistory, VendorRating, VendorDocument)

#### Segurança e Hardening
- [x] CORS restrito à URL do frontend
- [x] Token expiration (Sanctum 8h)
- [x] SQL injection prevention (sort_by whitelist)
- [x] BelongsToOrganization safety (unauthenticated = zero results)
- [x] DB::transaction em state changes (Proposal approve/reject/contract)
- [x] Rate limiting no login
- [x] SoftDeletes em categories

#### Seeds de dados
- [x] Categorias de eventos (Buffet, Som, Decoração, etc.)
- [x] Vendors de exemplo com dados brasileiros
- [x] Events com status variados
- [x] Usuários de teste (super-admin + admin)

### Fase 1.5 - Dívida técnica (PRÓXIMO PASSO)

> Acordado em 2026-09-04. Vem antes da Fase 2 porque é dívida acumulada que
> encarece tudo o que vier depois.

#### 1.5.1 - Cobertura de testes do frontend ✅ CONCLUÍDO (2026-09-05)
**61,9% de linhas / 52,2% de branches**, partindo de 16,8% / 2,8%.
246 testes Vitest em 15 arquivos. Meta de 60% atingida.

- [ ] Testes de componente para as views principais: `EventsListView`,
      `VendorsListView`, `EventFormView`, `VendorFormView`, `VendorDetailView`,
      `EventDetailView`, `SettingsView`
- [ ] Testes dos gates de permissão **nos botões** (o guard de rota já tem);
      usar `setupAuthWithPermissions()` de `src/test/helpers.ts`
- [ ] Testes de componente para `CategoriesListView` e `OrganizationsListView`
- [ ] Specs E2E para os fluxos ainda descobertos: troca de plano do
      fornecedor, edição de perfil, troca de senha, filtro por organização
- [ ] Testes dos demais services (`publicVendors`) e da store de auth que
      faltarem

#### 1.5.2 - Auditoria de transações e tratamento de erro ✅ CONCLUÍDO (2026-09-05)
Auditoria feita: **não há operação multi-escrita fora de transação**.
`EventService::duplicate`, `VendorService::createWithCategories` e
`updateWithCategories`, `CategoryService::reorder` e
`UserService::changePassword` já estavam transacionados — a suspeita
registrada aqui antes estava errada.

O único caso encontrado foi `AuthController::register`, que criava
Organization + User + role sem transação. Corrigido ao virar
`UserService::registerWithOrganization`.

- [ ] Pendente: revisar `try/catch` nos controllers caso a caso — hoje só o
      `EventController` tem um. Não é violação por si só: o padrão manda **não**
      adicionar onde o framework já trata

#### 1.5.3 - Reduzir o baseline do PHPStan — parcial (2026-09-05)
**197 → 58 erros** (redução de 70%).

O grosso não eram relações sem generic, como eu supunha: **68% estavam em
`Http/Resources`**, que delegam ao model por `__get`. Sete anotações `@mixin`
zeraram 119 entradas. `BaseRepository` virou genérico (`@template TModel`),
zerando mais 12.

- [ ] Os 58 restantes são cauda longa, sem padrão único: tipo perdido dentro
      de closures em `EventService` (16) e `VendorService` (11), 5 `nullsafe`
      que exigem análise caso a caso (remover um deles **quebraria em
      runtime**), e 3 de método de locale do Faker
- [ ] Quando o baseline zerar, avaliar subir do nível 5 para o 6

#### 1.5.4 - Padronizar o que ficou fora do padrão ✅ CONCLUÍDO (2026-09-05)
- Três controllers sem Service nem Repository extraídos: `AdminDashboard`
  (464→69), `Dashboard` (412→76) e `PublicVendor` (161→78). **1.037 → 223
  linhas**
- **Zero `validate()` inline** nos controllers: 7 FormRequests novos
- `CategoryService::reorder` deixou de acessar `Category::where()` direto
- `login` e `register` movidos para `UserService`

### Fase 2 - Fundação do Marketplace

Vem antes das cotações de propósito. A Fase 3 envia cotações **para
fornecedores**, e "quais fornecedores esta organização pode cotar" depende do
vínculo. Construir a cotação primeiro, sobre o `vendors.organization_id`
atual, obrigaria a reescrevê-la depois.

#### 2.1 - Pivot N-N Organização ↔ Fornecedor
- [ ] Migration `organization_vendor` (relationship_status, internal_alias,
      notes, linked_at/unlinked_at)
- [ ] Migration removendo `vendors.organization_id`, migrando os vínculos
      existentes para o pivot
- [ ] `Organization::vendors()` e `Vendor::organizations()` (belongsToMany)
- [ ] **Separar desvinculação de desativação**: a organização desvincula da
      própria carteira; só o super-admin altera `vendors.is_active` (global).
      Ver a seção "Vínculo Organização ↔ Fornecedor"
- [ ] Restringir `toggle-active` a super-admin; criar
      `POST /api/vendors/{id}/link` e `/unlink`
- [ ] UI: "Desativar" vira "Desvincular" para o admin; "Desativar" só aparece
      para super-admin

#### 2.2 - Fila de aprovação e compliance
Agrupados porque a regra "bloquear aprovação se o compliance estiver
irregular" liga os dois — separá-los faria a fila nascer sem o critério que
deveria usar.

- [ ] `GET /api/admin/vendors/pending` — hoje o fornecedor se auto-cadastra e
      fica `pending`, mas não há tela nem endpoint para ver a fila
- [ ] Tela de moderação com aprovar/rejeitar
- [ ] **Upload de documentos de compliance** — hoje existe só o
      `GET /compliance`; `vendor_documents` tem 0 linhas e não há como enviar
      nada. A tela de detalhe já mostra "3 documentos não enviados" sem
      oferecer meio de enviá-los
- [ ] Alertas de documento vencido
- [ ] Bloqueio de aprovação quando o compliance está irregular
- [ ] Notificação por e-mail ao fornecedor em cada transição

### Fase 3 - Cotações e Propostas
- [ ] QuoteRequestController completo
- [ ] ProposalController completo
- [ ] Magic Links para fornecedores (acesso sem login) — `quote_request_vendor`
      **já tem** `token` e `token_expires_at` modelados; falta implementar
- [ ] Matriz de comparação de propostas
- [ ] Fluxo de aprovação (Em Análise → Aprovado → Contratado)
- [ ] Price Lock (trava de valor)

### Fase 4 - Avaliações e cadastro de organizações

Depende da Fase 3: a regra anti-fraude é "só avalia quem contratou uma
proposta". Sem o fluxo de propostas não há o que avaliar.

#### 4.1 - API de avaliações
- [ ] `POST/GET /api/vendors/{id}/ratings`, `POST /api/ratings/{id}/dispute`,
      `POST /api/admin/ratings/{id}/resolve`
- [ ] Regras anti-fraude já especificadas (vínculo a contrato, uma por
      contrato, janela de 30 dias, peso logarítmico por valor)
- [ ] Recalcular `vendors.average_rating`/`total_ratings` a partir de dados
      reais — **hoje são valores fictícios do seeder**
- [ ] Escopo da nota: agregada do marketplace vs. a que aquela organização
      deu (o pivot da 2.1 é o que torna isso possível)

#### 4.2 - Cadastro de organizações
- [ ] `OrganizationController` (CRUD) — a tela existe mas é somente leitura,
      e as permissões `organizations.create/update/delete` já estão no seeder
      sem nada implementando
- [ ] **Decisão de produto pendente**: auto-cadastro com trial? convite/venda
      assistida? aprovação manual?

#### 4.3 - Avaliação bidirecional (a decidir)
- [ ] **Decisão de produto pendente**: o fornecedor avalia a organização?
      Faz sentido num marketplace, mas é modelagem nova — `vendor_ratings` só
      vai de organização → fornecedor

### Fase 5 - Gestão Completa de Eventos

> Independente das demais: não bloqueia nem é bloqueada por nenhuma outra
> fase, e pode ser tocada em paralelo.

- [ ] EventPhase - Fases do evento
- [ ] EventTask - Checklist de tarefas
- [ ] EventTimeline - Cronograma de entregas
- [ ] EventBudgetItem - Detalhamento de orçamento
- [ ] Duplicação/Templates de eventos
- [ ] Dashboard do evento (cockpit)

### Fase 6 - Inteligência e IA
- [ ] Briefing AI - Sugestão de categorias
- [ ] OCR de Propostas - Extração de dados de PDFs
- [ ] Análise de Risco - Score de fornecedores
- [ ] Sugestão de Fornecedores - Baseado em histórico
- [ ] Comparação Inteligente - Highlights automáticos

### Fase 7 - Compliance avançado

> O básico (upload, alerta de vencimento, bloqueio de aprovação) foi movido
> para a **2.2**, porque a fila de aprovação precisa desse critério para
> existir. O que fica aqui é o que vai além do mínimo.

- [ ] Histórico de compliance por fornecedor (linha do tempo dos documentos)
- [ ] Validação automática de CNPJ contra base externa (Receita)
- [ ] Renovação assistida: notificar o fornecedor antes do vencimento
- [ ] Relatório de compliance da carteira, por organização

### Fase 8 - Internacionalização (i18n)

Hoje a UI está **somente em pt_BR**, com as strings escritas direto nos
templates. A ordem de idiomas planejada é **Inglês, Francês e Espanhol**.

O que precisa acontecer quando essa fase começar:

- [ ] Instalar `vue-i18n` e configurar o locale padrão como `pt-BR`
- [ ] Extrair as strings dos templates para `src/locales/pt-BR.json`
- [ ] Criar `en.json`, `fr.json` e `es.json`
- [ ] Seletor de idioma, com a escolha persistida por usuário
- [ ] Backend: usar `lang/{locale}` para as mensagens de validação e de erro
      da API (`APP_LOCALE` já está em `pt_BR`)
- [ ] Formatação de data, número e moeda por locale (`Intl`), hoje assumindo
      pt_BR implicitamente
- [ ] Traduzir os dados semeados (nomes de categoria) ou separar rótulo de
      chave — hoje `categories.name` guarda o texto exibido em português

> **Por que não foi feito junto com a correção de acentuação (2026-09-04)**:
> extrair todas as strings para arquivos de locale toca praticamente todas as
> views e não muda nada visível enquanto só existe um idioma. A decisão foi
> corrigir o pt_BR agora e deixar a extração para quando o segundo idioma
> entrar de fato.

### Fase 9 - Integrações e Escala
- [ ] Webhooks para ERPs (SAP, Microsiga)
- [ ] Exportação CSV/JSON formatada
- [ ] API pública para integrações
- [ ] Dashboard ESG e Saving
- [ ] Modo Emergência (Botão de Pânico)

---

## Histórico de Modificações

### 2026-09-05 - Auditoria completa e Fase 1.5

Auditoria de todo o código a pedido do cliente, antes de qualquer deploy.
A Etapa 1 (arquitetura) apontou três controllers sem Service nem Repository,
concentrando 149 dos 193 erros do PHPStan em `Http/`.

**Camadas** — `AdminDashboard` (464→69), `Dashboard` (412→76) e
`PublicVendor` (161→78): de 1.037 para 223 linhas. Criados 2 Repositories
(38 consultas), 3 Services e 10 FormRequests. Zero `validate()` inline e zero
acesso direto a Model nos controllers.

**Cobertura de frontend** — 16,8% → **61,9%**. De 112 para 246 testes.

**PHPStan** — 197 → 58 erros congelados (70%). O grosso estava em
`Http/Resources`, não em relações sem generic como eu supunha.

**Preço dos planos saiu do SQL** — estava num `CASE` duplicado em duas
queries; mudar um valor exigia editar SQL em dois lugares. Agora em
`config/billing.php`, com o `CASE` montado por bindings.

Bugs encontrados no caminho:

- **`over_budget` era código morto** nos dois lados. O percentual era limitado
  com `min(..., 100)` e só depois comparado com `> 100`. Um evento 200%
  estourado aparecia como "warning", em azul, com "restante R$ 0". O alerta
  *"N eventos acima do orçamento"* já existia no dashboard e nunca disparava.
  Corrigido, e a magnitude do estouro passou a ser exposta (`over_amount`,
  `percentage_real`)
- **`VendorDetailView` imprimia o nome do ícone** ("bolt Geradores e Energia").
  Só a listagem de categorias tinha sido corrigida
- **`AuthController::register`** não tem rota nem teste, e criava
  Organization + User + role **sem transação**. Corrigido e mantido; será
  implementado de verdade quando necessário (Fase 4.2)
- **+22 acentuações** que a varredura anterior não pegou (`"e obrigatório"`,
  `Razao`, `excluido`)

### 2026-09-04 - Padrões, ferramentas de qualidade e comando de validação

- **Larastan (PHPStan) instalado**, nível 5, com `phpstan-baseline.neon`
  congelando os 193 erros pré-existentes — a ferramenta passa a cobrar só
  código novo
- **ESLint instalado** (`eslint-plugin-vue` + `typescript-eslint`). Desligadas
  as regras de pura formatação de template, que geravam ~1.500 avisos sem
  relação com qualidade e afogavam o que importa: de 1.584 para 20 problemas
- Corrigidos os 6 erros reais que o lint encontrou: **4 usos de `any`** em
  `catch` (LoginView, RegisterView, SettingsView) e 2 variáveis mortas nos
  E2E. Criado `src/types/api-error.ts` com `getValidationErrors()` e
  `getApiErrorMessage()`, substituindo `catch (error: any)` — `any` desligava
  a checagem justamente onde o dado vem de fora
- Adicionado o script `lint`/`lint:fix`, que o CLAUDE.md documentava mas **não
  existia**
- Nova seção **"Padrões Obrigatórios de Desenvolvimento"** — camadas,
  transações, try/catch, defesa em profundidade, testes por camada
- Novo comando **`/validar`** (`backend/.claude/commands/validar.md`), a ser
  rodado ao fim de cada passo do roadmap ou ajuste avulso, antes de commitar.
  Escopo é só o que foi alterado; se falhar, corrige e repete
- **Fase 1.5 (dívida técnica)** criada como próximo passo

### 2026-09-04 - Roadmap reordenado por dependência

Revisão da sequência, motivada por uma inversão real:

- **Pivot N-N passou para a Fase 2**, antes das cotações. A cotação é enviada
  *para fornecedores*, e "quais fornecedores esta organização pode cotar"
  depende do vínculo. Construí-la primeiro sobre o `vendors.organization_id`
  atual obrigaria a reescrevê-la depois
- **Compliance básico agrupado com a fila de aprovação (2.2)**. A regra
  "bloquear aprovação se o compliance estiver irregular" liga os dois; estavam
  em fases distantes (3.2 e 6). A Fase 7 ficou só com o que vai além do mínimo
- Avaliações e cadastro de organizações viraram Fase 4, **depois** das
  propostas — a regra anti-fraude é "só avalia quem contratou"
- Gestão de eventos (Fase 5) marcada como **independente**: não bloqueia nem é
  bloqueada, pode ser tocada em paralelo

Ordem final: 2 (fundação do marketplace) → 3 (cotações) → 4 (avaliações e
organizações) → 5 (eventos, paralelizável) → 6 (IA) → 7 (compliance avançado)
→ 8 (i18n) → 9 (integrações).

### 2026-09-04 - Refactor: camada de User (SOLID)

`updateProfile` e `updatePassword` tinham sido escritos com a lógica direto no
controller (`$user->update()`, `Hash::check()`, revogação de tokens) e
validação inline — fora do padrão Controller → Service → Repository que o
resto do projeto segue. A área de auth já estava fora do padrão antes, mas
isso não justificava crescer nela.

- `UserRepositoryInterface` + `UserRepository` (`findByEmail`,
  `revokeTokensExcept`)
- `UserServiceInterface` + `UserService` (`updateProfile`, `changePassword`)
- `UpdateProfileRequest` e `UpdatePasswordRequest` em `Http/Requests/Auth/`
- `AuthController` passou a receber `UserServiceInterface` por injeção
- Bindings registrados no `RepositoryServiceProvider`

A troca de senha e a revogação de sessões agora estão numa transação: senha
nova com sessão antiga ainda válida seria pior que não trocar.

O e-mail é blindado em **duas camadas**: o FormRequest não aceita o campo, e o
`UserService` faz `unset()` — o service é a última barreira antes da escrita e
não pode depender de quem o chama ter validado direito.

**+12 testes** (7 de service, 5 de repository), agora possíveis porque a
lógica saiu do controller. Os 8 testes de feature existentes passaram sem
alteração, o que confirma que o refactor preservou o comportamento. 509 no
total.

### 2026-09-04 - Compliance, filtros e RBAC na UI
- **Compliance do fornecedor renderizava JSON cru.** A API devolve
  `documents` como **objeto** com chave por tipo, mas o template testava
  `Array.isArray()` e caía num dump genérico, imprimindo o JSON e rótulos em
  inglês ("IS COMPLIANT", "TOTAL DOCUMENTS"). Reescrito para a forma real da
  API, com badge de situação e lista de documentos exigidos
- Filtros de eventos em **uma linha só** (50/30/20). Sem o filtro de
  organização, a busca absorve o espaço em vez de deixar vão (70/30)
- **UI passou a respeitar as permissões** (ver commit próprio): guard de rota
  com `meta.permission` e `v-if` nos botões. Antes, `hasPermission()` existia
  na store e **nenhuma view o usava**
- Filtro por organização nos eventos, só para super-admin
- Usuários de teste para `organizer` e `viewer`
- **`CLAUDE.md` corrigido**: a seção de APIs de fornecedores listava 8
  endpoints que nunca existiram; o sistema de avaliações estava descrito como
  se funcionasse (não há endpoint nem registro; as estrelas são dados
  fictícios do seeder); e não havia menção de que fornecedores não têm acesso
  ao sistema
- Documentada a inconsistência **Vínculo Organização ↔ Fornecedor** e o
  modelo alvo com pivot N-N, agora na Fase 3 do roadmap

### 2026-09-04 - Correções de UI e acentuação

Seis problemas reportados a partir das telas, mais dois achados no caminho:

- **Ícones das categorias apareciam como texto** (`utensils`, `broom`). Os
  seeders gravam nomes no estilo Font Awesome, mas não havia biblioteca de
  ícones instalada e o template imprimia a string. Instalado `lucide-vue-next`
  e criado `CategoryIcon.vue`, que mapeia os nomes dos seeders para os
  componentes do Lucide (alguns diferem: `couch` → `Sofa`, `broom` →
  `BrushCleaning`) e cai em `Package` no desconhecido
- **Inputs sem estilo** nos formulários de fornecedor. 37 usos de
  `form-input`/`form-select`/`form-checkbox`, classes do plugin
  `@tailwindcss/forms`, que não está instalado — não existiam no CSS. Trocadas
  por `.input` (a convenção que o formulário de eventos já usava) e criada a
  classe `.checkbox`, que não tinha equivalente
- **E-mail editável nas configurações**: agora é somente leitura, e o backend
  ignora o campo mesmo se vier no payload
- **Plano do fornecedor não era editável**: `subscription_tier` estava no
  `fillable` e no Resource, mas **não era validado em nenhum Request**, então o
  formulário nunca conseguiria salvá-lo. Adicionada a regra nos dois Requests,
  campo no formulário e seletor direto na listagem, via
  `POST /api/vendors/{id}/subscription-tier`
- **77 correções de acentuação** em 14 views. Banco e API sempre estiveram
  corretos — o problema era só nas strings fixas dos templates
- **Organizações**: a tela dizia "Gerencie" mas é somente leitura, porque não
  existe API de CRUD de organizations. Texto ajustado; o CRUD entrou no roadmap

Achados durante a investigação:

- **A tela de Configurações era inteiramente não-funcional.** Chamava
  `PUT /auth/profile` e `PUT /auth/password`, **e nenhuma das duas rotas
  existia** (404). Implementadas no `AuthController`, com 8 testes. A troca de
  senha revoga as outras sessões e preserva a atual
- `currentAccessToken()` nem sempre é um `PersonalAccessToken` (em auth por
  sessão vem `TransientToken`, e pode vir `null`), o que quebrava a revogação
  de sessões — tratado

497 testes backend, 50 Vitest, 21 E2E.

### 2026-09-04 - Suíte E2E, Pint, Vitest 5 e deploy de produção
- **Criada a stack de produção**: `docker-compose.prod.yml`, `Dockerfile` do
  frontend (build estático servido por nginx, 104MB) e configs de OPcache/PHP
  para produção. Código copiado para a imagem, sem bind mount; `--no-dev`;
  autoloader com `--classmap-authoritative`; caches gerados no entrypoint
- `docker/php/Dockerfile` reestruturado em stages (`base` → `dev` | `prod`),
  para que as extensões PHP não sejam mantidas em dois lugares
- Separados `.env.production` (Laravel, runtime) e `.env.deploy` (Compose,
  antes de subir). O `--env-file` é obrigatório: sem ele o Compose leria o
  `.env` de desenvolvimento e injetaria credenciais de dev em produção
- **Validado apenas localmente** (portas 8080/8081): `Environment=production`,
  `Debug=OFF`, config/rotas/eventos em cache, `phpunit`/`sail`/`tests` fora da
  imagem, migrations, seeders e login funcionando. **Não há ambiente de
  produção real ainda**

- **Suíte E2E passou a fechar 21/21 em paralelo** (antes não fechava de jeito
  nenhum). Um único login por execução via projeto `setup` + `storageState`,
  no lugar de `loginViaApi` em todo `beforeEach`
- Corrigidos 3 bugs de teste: `addInitScript` reinjetando a sessão após
  `clearAuth`; race lendo `page.url()` antes da navegação client-side; e
  asserções de rota esperando ID numérico num projeto que usa UUID
- `login_max_attempts` virou configurável (`config/auth.php`), com limiter
  nomeado no `AppServiceProvider`. Default 5/min mantido em produção; dev usa
  30 via `LOGIN_MAX_ATTEMPTS`. **+2 testes** cobrindo o limite, que não tinha
  nenhum
- `phpunit.xml` passou a fixar `LOGIN_MAX_ATTEMPTS`, tornando a suíte
  independente do `.env` de cada máquina
- Pint aplicado em 69 arquivos (só estilo, 483 testes seguiram verdes)
- Vitest 4.1.11 → 5.0.0
- **Achado em aberto**: `Vendor` nunca aplicou o trait `BelongsToOrganization`
  (o Pint removeu o import morto). Fornecedores são globais entre organizações
  — correto pelo modelo de marketplace, mas dois testes se chamam
  `org_a_user_cannot_see_org_b_vendors` e não afirmam nada além de `assertOk()`

### 2026-09-02 - Atualização de Infraestrutura
- `npm install` -> `npm ci` no boot do container do frontend (instalação
  reprodutível a partir do `package-lock.json`)
- Retry (3x) no `pecl install redis` do Dockerfile: o espelho do PECL entrega
  downloads truncados de forma intermitente e quebrava o build em ambiente novo
- Criado `.env.production`, carregado quando `APP_ENV=production` chega pelo
  ambiente do processo; o compose repassa a variável ao container do PHP
- **Infra passou a ser versionada**: `docker-compose.yml`, `docker/` e este
  `CLAUDE.md` foram movidos para dentro do repo `orchestra-api`. Antes ficavam na
  raiz do projeto, que não é repositório git — ou seja, o Dockerfile e o compose
  não estavam em commit nenhum. Compose agora fixa `name: orchestra` para manter
  os volumes `orchestra_*`; symlink de `CLAUDE.md` na raiz
- Migrado o guard do Vue Router para a API de retorno (`next()` deprecado no v5)
- **PHP 8.2 → 8.5.10**, **Laravel 12.47 → 13.30.1**, Spatie Permission 6 → 8.3.0,
  PHPUnit 11 → 13.3.2, Sanctum 4.2 → 4.3.3, Tinker 2 → 3.0.2
- **MySQL 8.0 → 8.4 LTS** (upgrade in-place do volume, dados preservados),
  **Redis → 8-alpine**, **Nginx → 1.30-alpine**, **Node 20 → 24 LTS**
- **Mailhog → Mailpit v1.31** (Mailhog está sem manutenção desde 2020)
- Frontend: **Vite 7 → 8**, **Pinia 3 → 4**, **Vue Router 4 → 5**,
  **ApexCharts 5 → 7**, Vitest 4.1.11, jsdom 30, TypeScript 5.9 → 6.0.3
- Backend migrou **sem nenhuma alteração de código de aplicação** (483 testes verdes)
- Frontend: única mudança de código foi remover `baseUrl` do `tsconfig.app.json`
  (deprecado no TS 6, removido no TS 7) — 50 testes verdes
- Adicionado `serializable_classes => false` em `config/cache.php` (default novo do L13)

### 2026-02-19 - v1.0.0 Release
- Auditoria completa do projeto: identificados e corrigidos 10 bugs críticos
- 483 testes backend (Unit + Feature) com PHPUnit e SQLite in-memory
- 50 testes frontend (Vitest) para stores e services
- 20 specs E2E (Playwright) para fluxos críticos
- 10 factories completas para todas as entidades
- Views frontend completas: EventForm, EventDetail, VendorForm, VendorDetail, Settings
- Placeholder views informativos para Fase 2 (Quotes, Proposals)
- Hardening: rate limiting, N+1 fixes, SoftDeletes, token expiration
- CORS restrito, SQL injection prevention, multi-tenancy safety

### 2026-01-19 - Modelo Marketplace
- Documentado modelo de negócio marketplace (fornecedores gratuitos, clientes pagos)
- Definidos fluxos de entrada de fornecedores (self-service, importação, convite)
- Implementado sistema de aprovação de fornecedores
- Definido modelo de patrocínio (Free, Destaque, Premium)
- Documentado sistema de avaliações com regras anti-fraude
- Criadas APIs públicas para cadastro de fornecedores

### 2026-01-19 - Dashboards Diferenciados
- Documentado requisitos de dashboards diferenciados (Super Admin vs Cliente)
- Escolhida biblioteca ApexCharts (vue3-apexcharts) para gráficos Vue 3
- Definidas métricas e gráficos para cada tipo de usuário
- Implementado AdminDashboardController para plataforma
- Implementado gráficos no Dashboard do cliente

### 2026-01-19 - Fase 1 Completa
- Implementado CategoryController com CRUD completo
- Implementado VendorController com geolocalização
- Implementado EventController com estatísticas
- Implementado DashboardController com dados reais
- Criados seeders para vendors e events
- Dashboard frontend atualizado com dados da API

### 2026-01-19 - Setup Inicial
- Criado projeto com estrutura Laravel 12 + Vue 3
- Configurado Docker com portas customizadas (8001, 3001, 3308, 6380)
- Implementado autenticação Sanctum com UUIDs
- Corrigido Tailwind CSS v4 (plugin @tailwindcss/vite)
- Corrigido CORS para suportar credentials
- Removida opção de registro público (apenas admin cria usuários)

### Correções Importantes
1. **DB_PORT** deve ser 3306 (interno), não 3308 (externo)
2. **UUID nas migrations** do Spatie Permission e Sanctum
3. **Ordem das migrations**: organizations antes de users
4. **Tailwind v4**: não permite `@apply` com classes do mesmo arquivo
5. **CORS duplicado**: Nginx estava adicionando headers CORS + Laravel também = erro "multiple values". Removido CORS do Nginx (docker/nginx/default.conf)
6. **SANCTUM_STATEFUL_DOMAINS**: NÃO usar com auth baseada em token (causa CSRF mismatch)
7. **Ambiguous column**: Usar `proposals.status` ao invés de `status` em queries com joins

### v1 Bug Fixes (2026-02-19)
1. **DashboardController**: status inexistentes ('sent'->'open', 'submitted'->'pending', 'responded'->'in_review'), campo `value`->`total_value`
2. **QuoteRequestResource**: campos inexistentes (`description`->`technical_description`, removido `quantity`)
3. **EventService/Controller**: 'cancelled' vs 'canceled' (mismatch com enum da migration)
4. **AdminDashboardController**: tabela `sessions` inexistente, `TIMESTAMPDIFF` substituído por Carbon, N+1 queries corrigidas
5. **VendorRepository**: SQL injection em `sort_by` (whitelist adicionada)
6. **BelongsToOrganization**: vazamento de dados quando auth falha (`whereRaw('1 = 0')`)
7. **Proposal model**: approve/reject/contract sem `DB::transaction` (dados inconsistentes)
8. **CORS**: `allowed_origins` restrito a `env('FRONTEND_URL')`
9. **Sanctum**: tokens sem expiração (adicionado 480 min)
10. **Base Controller**: faltava trait `AuthorizesRequests` (500 em `$this->authorize()`)
11. **Route ordering**: rotas estáticas (`/events/upcoming`) capturadas por wildcard `{event}`
12. **EventService::duplicate()**: `start_date` setado como null (corrigido para copiar datas originais)

---

## Troubleshooting

### Erro de conexão com MySQL
```bash
# Verificar se DB_PORT está 3306 (não 3308) no .env
docker exec orchestra-php php artisan config:clear
```

### Erro de CORS
```bash
# Verificar config/cors.php tem supports_credentials = false
# E allowed_origins com env('FRONTEND_URL', 'http://localhost:3001')
docker exec orchestra-php php artisan config:clear
```

### Erro de UUID no Spatie Permission
- Verificar se migrations usam `uuid()` para `model_morph_key`
- Verificar se `personal_access_tokens` usa `uuidMorphs('tokenable')`

### Erro de Tailwind CSS v4
- Não usar `@apply btn` dentro de `.btn-primary` (referências circulares)
- Usar `@tailwindcss/vite` plugin ao invés de postcss

### Suíte E2E: como a autenticação funciona
A suíte faz **um único login** por execução. O projeto `setup`
(`e2e/auth.setup.ts`) autentica e grava a sessão em `e2e/.auth/admin.json`
(gitignored); os demais testes herdam via `storageState`, declarado como
`dependencies: ['setup']` no `playwright.config.ts`.

Isso existe por causa do rate limit do `/api/login`. Antes cada spec chamava
`loginViaApi` no `beforeEach`, a suíte consumia ~18 logins e, a partir do 6º
teste, recebia 429 (HTML "Too Many Requests") em vez de JSON. Hoje são 3
logins por execução: o do `setup`, o do teste de login pela UI e o de
credenciais inválidas.

Testes que precisam começar **deslogados** descartam a sessão herdada:

```ts
test.describe('Authentication (sem sessao)', () => {
  test.use({ storageState: { cookies: [], origins: [] } })
```

> Não use `page.addInitScript` para injetar a sessão: ele re-executa a cada
> navegação, então qualquer `page.goto` posterior a um `clearAuth` reinjeta o
> token e a sessão nunca é encerrada de fato. Use `page.evaluate`.

> Em navegação client-side não há tráfego de rede para aguardar, então
> `waitForLoadState('networkidle')` retorna antes de o router terminar e o
> `page.url()` sai desatualizado. Use `page.waitForURL(...)`.

> IDs são **UUID** (trait `HasUuid`), não inteiros. Para asserções de rota de
> detalhe use `detailRoute('events')` de `e2e/helpers/patterns.ts`, e não
> padrões como `/\/events\/\d+/`, que nunca casam.

### Rate limit de login
`config/auth.php` expõe `login_max_attempts`, com **default 5 por minuto por
IP** — o valor que vale em produção. O limiter nomeado `login` é registrado no
`AppServiceProvider` e resolvido a cada requisição, para que um `route:cache`
gerado em outro ambiente não congele o valor.

Em desenvolvimento o `.env` deste projeto usa `LOGIN_MAX_ATTEMPTS=30`, para
permitir rodar a suíte E2E várias vezes seguidas. O `phpunit.xml` fixa o valor
em `5`, de modo que os testes não dependam do `.env` de cada máquina.

### npm instala versões antigas mesmo após editar package.json
O serviço `frontend` usa um volume **anônimo** em `/app/node_modules`, e o Docker
Compose o **preserva** ao recriar o container. Um `npm install` sobre essa árvore
velha reinstala as versões antigas. Para forçar instalação limpa:

```bash
docker compose rm -fsv frontend   # -v remove o volume anônimo junto
rm frontend/package-lock.json     # regenera o lock do zero
docker compose up -d frontend
```

### Build do PHP falha em `pecl install redis`
O pecl.php.net entrega downloads truncados com alguma frequência. O sintoma é o
tamanho baixado não bater com o anunciado, seguido de erro de extração:

```
Starting to download redis-6.3.0.tgz (399,284 bytes)
......done: 199,714 bytes
ERROR: unable to unpack /tmp/pear/download/redis-6.3.0.tgz
```

Não é problema do Dockerfile — é instabilidade do espelho. O Dockerfile já tenta
até 3 vezes, limpando `/tmp/pear` entre as tentativas. Se as 3 falharem, o build
aborta de propósito (`test "$ok" = 1`), em vez de gerar uma imagem sem o Redis.

### Build do PHP falha em `install-modules` do opcache
`docker-php-ext-install opcache` quebra com `cp: cannot stat 'modules/*'` porque o
OPcache já vem compilado estaticamente na imagem base `php:8.5-fpm`. Não incluir
`opcache` na lista do `docker-php-ext-install`.

### Erro "Column is ambiguous"
- Em queries com joins, sempre prefixar com nome da tabela
- Ex: `proposals.status` ao invés de `status`
