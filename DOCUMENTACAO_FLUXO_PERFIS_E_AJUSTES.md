# Documentacao Dos Ajustes De Fluxo, Perfis E Interface

**Data de consolidacao:** 15/04/2026

## Objetivo

Este documento registra o que foi ajustado no sistema ate o momento, com foco em:

- fluxo de criacao e operacao das sessoes
- separacao correta entre os perfis `ADMIN`, `PRESIDENTE` e `PARLAMENTAR`
- simplificacao da interface
- correcoes de seguranca e de logica de negocio

O objetivo principal das mudancas foi deixar o sistema mais coerente com o uso real:

- o administrador prepara a sessao
- o presidente conduz a sessao
- o parlamentar apenas acompanha o que for pertinente e vota quando liberado

---

## Resumo Executivo

O sistema saiu de um modelo com telas duplicadas e responsabilidades misturadas para um modelo mais claro:

- `ADMIN` passou a ter a responsabilidade de montagem da sessao
- `PRESIDENTE` deixou de cair no fluxo administrativo e passou a abrir direto no painel de conducao
- `PARLAMENTAR` continua com foco apenas em votacao e historico

Tambem foram feitos ajustes importantes de seguranca, permissao, integridade de dados e limpeza visual.

---

## Modelo Atual De Perfis

### ADMIN

Responsavel por preparar a operacao.

Pode:

- acessar `Sessoes`
- criar nova sessao
- preencher a central da sessao
- montar roteiro da presidencia
- cadastrar materias
- cadastrar ata
- gerenciar usuarios
- gerenciar permissoes
- consultar logs
- acessar historico
- acessar painel de votacao

Fluxo atual apos login:

- entra diretamente em `admin/sessoes`

### PRESIDENTE

Responsavel por conduzir a sessao, nao por monta-la.

Pode:

- acessar `votacao/painel`
- acessar `votacao/telao`
- consultar historico
- visualizar orientacoes da presidencia e a pauta operacional

Nao deve:

- criar sessao
- editar estrutura administrativa da sessao
- acessar usuarios
- acessar permissoes
- acessar logs administrativos por padrao

Fluxo atual apos login:

- entra diretamente em `votacao/painel`

### PARLAMENTAR

Responsavel por acompanhar e votar.

Pode:

- acessar `votacao/painel`
- consultar historico

Nao deve:

- criar sessao
- montar pauta
- acessar administracao
- acessar controles da presidencia

Fluxo atual apos login:

- entra diretamente em `votacao/painel`

---

## Ajustes Estruturais De Fluxo

## 1. Fluxo De Criacao De Sessao

Antes:

- existia formulario antigo de sessao
- existia central nova da sessao
- o usuario via duas experiencias para praticamente a mesma tarefa

Agora:

- o botao `Nova Sessao` cria um rascunho automaticamente
- o sistema abre direto a `Central da Sessao`
- a criacao e o preenchimento passaram a ser conduzidos por um fluxo unico

Resultado:

- reducao de duplicidade
- menos confusao de navegacao
- preenchimento em etapas mais coerente

## 2. Tela Principal Administrativa

Antes:

- `Admin` e `Sessoes` competiam entre si
- o dashboard repetia informacoes da lista de sessoes

Agora:

- `Sessoes` virou a tela principal do trabalho administrativo
- o item `Admin` saiu do menu principal
- `admin/dashboard` passou a agir como alias de redirecionamento quando aplicavel

Resultado:

- menos telas redundantes
- menos decisao desnecessaria na entrada
- fluxo mais direto para o administrador

## 3. Central Da Sessao

Antes:

- hero grande
- bloco de recomendacao
- cards de etapas
- cards de metricas
- checklist lateral
- muitas camadas visuais antes do conteudo principal

Agora:

- cabecalho simples
- botoes principais reduzidos
- etapas em formato de acoes simples
- abas como ponto principal da navegacao
- foco direto no conteudo da aba

Resultado:

- menos ruido visual
- tela mais objetiva
- menor sensacao de excesso de blocos

---

## Ajustes De Interface Por Perfil

## Visao Do Administrador

O administrador ficou com:

- menu focado em administracao
- lista simples de sessoes
- acesso a `Abrir central`
- central da sessao enxuta e voltada para preenchimento

## Visao Do Presidente

O presidente ficou com:

- menu reduzido a `Votacao`, `Telao` e `Historico`
- acesso direto ao painel de presidencia
- visao de conducao da sessao
- orientacoes de fala e roteiro visiveis no painel da presidencia

Quando nao existe sessao aberta:

- ele recebe uma mensagem clara para aguardar a preparacao do administrador
- nao recebe mais instrucoes de entrar em criacao administrativa

## Visao Do Parlamentar

O parlamentar ficou com:

- fluxo enxuto de painel de votacao
- historico
- nenhuma camada administrativa

---

## Ajustes Funcionais E De Regra De Negocio

Os seguintes pontos foram corrigidos ou reforcados:

### Integridade Da Sessao E Da Votacao

- o sistema impede mais de uma sessao ativa ao mesmo tempo
- o sistema impede mais de uma materia `EM_VOTACAO` por sessao
- a sessao nao pode ser encerrada enquanto ainda houver materia em votacao
- o parlamentar nao consegue votar fora da hora ou sem sessao apta
- o voto so e aceito quando a materia esta de fato em `EM_VOTACAO`

### Consistencia De Permissoes

- o backend passou a respeitar as permissoes reais, nao apenas o menu
- o redirecionamento pos-login passou a considerar o que o perfil realmente pode acessar
- foi criada a permissao `votacao.presidente` para separar melhor a ideia de painel da presidencia
- o perfil `ADMIN` passou a ter protecao contra auto-bloqueio de permissoes essenciais

### Seguranca

- exclusao de usuario foi migrada para `POST` com validacao CSRF
- upload de foto passou a validar melhor o arquivo
- o endpoint publico de apuracao deixou de expor detalhes sensiveis para visitantes
- exibicao de erros PHP foi limitada para nao vazar detalhes em ambiente nao local

---

## Ajustes Visuais E De Usabilidade

Foram feitos ajustes de UX para reduzir confusao:

- destaque do item ativo no menu
- melhoria da lista de sessoes
- simplificacao da central da sessao
- refinamento do historico
- melhoria do painel do presidente
- melhoria do painel sem sessao ativa
- fallback visual de avatar padrao

Tambem foi feita validacao visual com navegador automatizado para comparar o antes e o depois das telas principais.

---

## Ajustes Em Seed E Estrutura Inicial

O `database/seed.sql` foi alinhado ao modelo atual:

- `ADMIN` com conjunto completo de permissoes administrativas e operacionais
- `PRESIDENTE` focado em `votacao.painel`, `votacao.presidente` e `historico.view`
- `PARLAMENTAR` focado em `votacao.painel` e `historico.view`

Isso significa que uma instalacao nova do sistema passa a nascer mais perto do comportamento esperado.

---

## Comportamento Esperado Do Sistema A Partir De Agora

### Administrador

1. entra no sistema
2. vai para `Sessoes`
3. cria uma nova sessao
4. o sistema abre direto a central
5. preenche dados, roteiro, materias, ata e documento final
6. deixa a sessao pronta para operacao

### Presidente

1. entra no sistema
2. vai direto ao painel de votacao
3. se nao houver sessao aberta, aguarda a liberacao
4. quando houver sessao aberta, conduz a operacao vendo:
   - item atual da pauta
   - fala sugerida
   - materia vinculada
   - roteiro da presidencia
   - comandos de votacao

### Parlamentar

1. entra no sistema
2. abre o painel de votacao
3. acompanha a materia liberada
4. vota quando autorizado

---

## Pendencias E Melhorias Recomendadas

Embora a base esteja muito mais organizada, ainda existem proximos passos recomendados:

- criar uma visao ainda mais executiva para o presidente, com leitura corrida e comandos mais destacados
- revisar textos com caracteres corrompidos que ainda existem em documentos antigos e comentarios de arquivos legados
- atualizar `README.md` e `SESSION_HISTORY.md`, que ainda possuem trechos antigos e encoding inconsistente
- padronizar melhor a tela sem sessao aberta para presidente e parlamentar, caso se deseje mensagens diferentes por perfil
- avaliar se a permissao `admin.dashboard` ainda faz sentido permanecer no cadastro de permissoes, ja que o fluxo principal foi migrado para `Sessoes`

---

## Arquivos Mais Impactados Nesta Etapa

Principais arquivos relacionados a essas mudancas:

- `app/Modules/Auth/Controllers/AuthController.php`
- `app/Modules/Admin/Controllers/AdminController.php`
- `app/Views/layouts/navbar.php`
- `app/Modules/Admin/Views/sessoes_list.php`
- `app/Modules/Admin/Views/sessao_workspace.php`
- `app/Modules/Votacao/Views/painel_presidente.php`
- `app/Modules/Votacao/Views/painel_sem_sessao.php`
- `database/seed.sql`

---

## Conclusao

O sistema esta hoje mais proximo do uso real da camara:

- menos duplicidade
- menos confusao visual
- mais separacao de responsabilidade por perfil
- mais seguranca
- mais coerencia entre preparacao da sessao e conducao da sessao

Em resumo:

- `ADMIN` prepara
- `PRESIDENTE` conduz
- `PARLAMENTAR` acompanha e vota

Esse passa a ser o modelo de referencia para a evolucao das proximas etapas.
