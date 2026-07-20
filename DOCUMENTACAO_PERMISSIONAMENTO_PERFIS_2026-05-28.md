# Permissionamento por perfil - 2026-05-28

Este documento registra a separacao de funcoes e visoes entre os perfis:

- ADMINISTRADOR
- PRESIDENTE
- PARLAMENTAR
- USUARIO

## Objetivo

O sistema passa a trabalhar com uma regra clara:

- Perfil: cargo base do usuario.
- Permissao: acao que o usuario pode executar.
- Visao: menus, botoes e telas exibidos conforme as permissoes.

Assim, e possivel controlar separadamente o que o administrador ve, o que o presidente ve e o que o parlamentar ve.

## Arquivos alterados

- `app/Helpers/PermissaoCatalog.php`
- `app/Helpers/Permissao.php`
- `app/Controllers/SessaoController.php`
- `app/Controllers/PautaController.php`
- `app/Controllers/RoteiroController.php`
- `app/Controllers/PresidenciaController.php`
- `app/Controllers/VotacaoController.php`
- `app/Controllers/HistoricoController.php`
- `app/Controllers/AuditoriaController.php`
- `app/Controllers/DashboardController.php`
- `app/Views/permissoes/index.php`

## Pacotes padrao por perfil

### Administrador

O administrador usa o pacote completo:

- dashboard
- sessoes
- pauta
- roteiro
- votacao
- presidencia
- usuarios
- permissoes
- historico
- auditoria
- publico
- perfil parlamentar, quando aplicavel ao cadastro

Funcao esperada:

- montar e preparar sessoes
- cadastrar e editar pauta
- montar roteiro
- gerenciar usuarios
- gerenciar permissoes
- consultar auditoria
- operar o sistema quando necessario

### Presidente

O presidente usa um pacote de conducao, sem administracao cadastral:

- `dashboard.index`
- `menu.dashboard`
- `menu.sessoes`
- `menu.votacao`
- `menu.historico`
- `menu.publico`
- `sessao.index`
- `presidencia.index`
- `presidencia.conduzir`
- `presidencia.avancar`
- `presidencia.voltar`
- `presidencia.encerrarSessao`
- `roteiro.resetarExecucao`
- `votacao.index`
- `votacao.painel`
- `votacao.estadoRodada`
- `votacao.streamRodada`
- `votacao.abrir`
- `votacao.encerrar`
- `votacao.votar`
- `votacao.apuracao`
- `votacao.abrirRodada`
- `votacao.encerrarRodada`
- `votacao.votarRodada`
- `votacao.apuracaoRodada`
- `votacao.presenca`
- `historico.index`
- `historico.sessao`
- `publico.index`

Funcao esperada:

- conduzir a sessao
- ver a listagem de sessoes
- avancar e voltar passos
- abrir e encerrar votacoes
- acompanhar painel de votacao
- registrar presenca
- encerrar sessao
- consultar historico

O presidente nao recebe por padrao:

- `menu.usuarios`
- `usuario.*`
- `menu.permissoes`
- `permissoes.*`
- `menu.auditoria`
- `auditoria.*`
- edicao administrativa de sessao, pauta e roteiro

Na listagem de sessoes, o presidente ve a sessao e o botao de conducao, mas nao ve os botoes administrativos de criar, editar ou excluir sessao.

O menu lateral do presidente nao exibe `Presidencia`; o caminho principal de trabalho e `Sessoes > Conduzir`.
Na tela de conducao, o perfil presidente tambem nao ve o botao `Editar` do roteiro.

### Parlamentar

O parlamentar usa um pacote de acompanhamento e voto:

- `dashboard.index`
- `menu.dashboard`
- `menu.meu_perfil`
- `menu.sessoes_parlamentar`
- `menu.publico`
- `menu.historico`
- `publico.index`
- `historico.index`
- `historico.sessao`
- `parlamentar.meuperfil`
- `parlamentar.saveperfil`
- `parlamentar.sessoes`
- `parlamentar.sessao`
- `parlamentar.estado`
- `parlamentar.stream`
- `parlamentar.materia`
- `parlamentar.votar`

Funcao esperada:

- ver proprio perfil
- acompanhar sessoes
- ver pauta e materia
- votar quando houver votacao aberta
- consultar historico

O parlamentar nao recebe por padrao:

- administracao
- presidencia
- usuarios
- permissoes
- auditoria
- painel operacional de votacao

### Usuario

O usuario comum recebe somente acesso minimo:

- `dashboard.index`
- `menu.dashboard`
- `menu.publico`
- `publico.index`

## Mudancas tecnicas

### 1. Pacotes centralizados

Foram criados metodos em `PermissaoCatalog`:

- `chavesAdministrador()`
- `chavesPresidente()`
- `chavesParlamentar()`
- `chavesPadraoPerfil(string $role)`

Com isso, o perfil padrao de cada usuario passa a vir de uma lista clara e revisavel.

### 2. Regra padrao ficou mais segura

Antes, algumas permissoes desconhecidas podiam ser liberadas por padrao.

Agora, se uma rota protegida nao estiver no catalogo de permissoes, ela e bloqueada. Isso evita que uma nova acao seja criada no futuro e fique acessivel sem permissao cadastrada.

### 3. Roteador corrigido para camelCase

O roteador transformava as acoes para minusculo. Isso criava divergencia com chaves como:

- `sessao.gerarMinutaAta`
- `sessao.exportarPauta`
- `roteiro.toggleAtivo`
- `votacao.abrirRodada`

Agora o roteador normaliza a comparacao e retorna a chave real do catalogo.

### 4. Controllers usando permissao por acao

As regras antigas baseadas em `Auth::requireRole(...)` foram substituidas por `Permissao::exigir(...)` nos controllers operacionais.

Exemplo:

```php
Permissao::exigir('presidencia.conduzir');
```

Isso significa que nao basta ter um cargo especifico. O usuario precisa ter a permissao daquela acao.

### 5. Tela de permissoes com pacotes

A tela `Permissoes` ganhou botoes para aplicar rapidamente:

- pacote Administrador
- pacote Presidente
- pacote Parlamentar

Depois de aplicar um pacote, ainda e possivel marcar ou desmarcar permissoes manualmente para criar excecoes por usuario.

## Observacao sobre permissoes personalizadas

Usuarios sem permissao personalizada usam automaticamente o pacote padrao do perfil.

Usuarios com permissao personalizada continuam usando exatamente o que estiver salvo em `user_permissoes`.

Para voltar um usuario ao comportamento padrao do perfil, e necessario limpar a personalizacao desse usuario no banco ou reativar essa opcao na tela de permissoes caso a interface seja reabilitada.

## Validacoes realizadas

- Verificada ausencia de `Auth::requireRole(...)` nos controllers.
- Verificado que as rotas publicas e metodos publicos dos controllers possuem chave no catalogo.
- Verificado que o roteador resolve corretamente nomes camelCase.
- Verificada sintaxe PHP dos arquivos alterados.
- Mantido tratamento especial para endpoints internos do painel de votacao:
  - quem possui `votacao.painel` tambem pode acessar `votacao.estadoRodada` e `votacao.streamRodada`.

## Resultado

O sistema agora fica organizado para criar visoes separadas:

- administrador prepara e gerencia
- presidente conduz
- parlamentar acompanha e vota
