---
description: Valida os padrões do projeto no que acabou de ser alterado (SOLID, testes, transações, try/catch, lint, análise estática). Corrige e repete até passar.
---

# Validação de padrões

Rode isto **sempre** ao terminar um passo do roadmap ou um ajuste solicitado
fora dele, **antes de commitar**.

## Escopo

Valide **apenas o que foi alterado agora**, não o projeto inteiro. Descubra o
alvo com:

```bash
git -C backend status --short && git -C backend diff --name-only HEAD
git -C frontend status --short && git -C frontend diff --name-only HEAD
```

Se não há nada alterado, diga isso e pare — não invente trabalho.

## Etapa 1 — Padrões de arquitetura

Para **cada arquivo PHP alterado**, verifique:

- [ ] **Camadas**: Controller só recebe e delega. Lógica de negócio em Service,
      acesso a dados em Repository. Controller com `Model::where(...)`,
      `$model->save()` ou regra de negócio é violação.
- [ ] **Injeção por interface**: o Controller recebe `FooServiceInterface`, não
      `FooService`. O binding existe em `RepositoryServiceProvider`.
- [ ] **FormRequest**: validação em `Http/Requests/`, não `$request->validate()`
      inline no controller. Com `messages()` em português.
- [ ] **Transação**: toda operação que escreve em **mais de um lugar** (duas
      tabelas, ou tabela + revogação de token, ou update + evento) está dentro
      de `DB::transaction()`. Escrita única não precisa.
- [ ] **Tratamento de erro no controller**: operação que pode falhar por causa
      externa (I/O, serviço terceiro, integridade) tem `try/catch` devolvendo
      resposta adequada. Não engula exceção só para o teste passar — se o
      framework já trata (`ModelNotFoundException` → 404,
      `ValidationException` → 422, `AuthorizationException` → 403), deixe o
      framework tratar e **não** adicione try/catch redundante.
- [ ] **Defesa em profundidade**: regra de negócio crítica (ex.: "e-mail não é
      alterável") vale no Service também, não só no FormRequest. O Service é a
      última barreira antes da escrita.

Para **cada arquivo Vue/TS alterado**:

- [ ] Sem `any` — use `unknown` + os helpers de `@/types/api-error`
- [ ] Ação protegida por permissão usa `auth.hasPermission(...)`, e a rota
      correspondente tem `meta.permission` no router
- [ ] Texto de UI em **pt-BR com acentuação correta**
- [ ] Lógica de transformação de dado extraída em `computed`/função pura, para
      poder ser testada sem montar a view inteira

## Etapa 2 — Testes

Toda mudança de comportamento precisa de teste **na camada onde a regra mora**:

| O que mudou | Teste obrigatório |
|---|---|
| Service ou Repository | Unitário em `tests/Unit/{Services,Repositories}/` |
| Endpoint | Feature em `tests/Feature/Api/` — caminho feliz, validação e **permissão** |
| Componente ou lógica de view | Vitest em `__tests__/` ao lado do arquivo |
| Fluxo de usuário novo | Spec E2E em `e2e/` |

Regra prática: **se você validou algo com script de browser descartável, isso
precisa virar teste.** Script confirma; teste protege.

```bash
docker exec orchestra-php php vendor/bin/phpunit
docker exec orchestra-frontend npm run test
```

E2E (roda um spec por vez; ver a seção de E2E no CLAUDE.md):
```bash
docker exec orchestra-redis redis-cli FLUSHALL
npx playwright test
```

## Etapa 3 — Ferramentas

```bash
# PHP: estilo + análise estática
docker exec orchestra-php ./vendor/bin/pint --test
docker exec orchestra-php ./vendor/bin/phpstan analyse --memory-limit=512M

# Frontend: lint + tipos + build
docker exec orchestra-frontend npm run lint
docker exec orchestra-frontend npx vue-tsc --noEmit -p tsconfig.app.json
docker exec orchestra-frontend npm run build
```

> O `phpstan-baseline.neon` congela os erros que já existiam. **Nunca
> regenere o baseline para fazer erro novo desaparecer** — isso esconde o
> problema em vez de resolvê-lo. Reduzir o baseline é item próprio do roadmap.

## Etapa 4 — Corrigir e repetir

Se qualquer item falhou: **corrija e rode a validação de novo desde a Etapa 1.**
Repita até tudo passar. Não commite com item pendente e não relate como
concluído o que não passou.

Se um item não se aplica, diga **por quê** — não marque em silêncio.

## Relatório

Ao final, informe:

- o que foi validado (arquivos/áreas)
- o resultado de cada etapa, com os números reais (testes, erros de lint)
- o que precisou ser corrigido durante a validação
- o que **não** foi coberto e por quê
