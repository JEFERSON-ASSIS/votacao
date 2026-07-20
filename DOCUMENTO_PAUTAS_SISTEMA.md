# Analise Das Pautas E Evolucao Do Sistema

## Objetivo

Este documento descreve:

- como o sistema funciona hoje
- o que existe nas pautas analisadas
- o que falta para o sistema conseguir gerar uma visao semelhante
- como o fluxo atual pode evoluir para um modelo mais completo e util para a Presidencia

Documentos analisados:

- `C:\Users\jefer\Downloads\Pauta 01.2026Pr.docx`
- `C:\Users\jefer\Downloads\Pauta 02.2026Pr.docx`
- `C:\Users\jefer\Downloads\Pauta 03.2026Pr.docx`

---

## Resumo Executivo

As pautas analisadas nao sao apenas uma lista de materias. Elas funcionam como um **roteiro operacional completo da sessao**, especialmente para quem preside.

Hoje o sistema ja cobre uma parte importante:

- cadastro de sessoes
- cadastro de materias
- roteiro curto por materia para o presidente
- votacao
- apuracao
- historico

Mas ele ainda **nao modela a sessao como um roteiro completo**. Falta estrutura para:

- abertura formal
- leitura e votacao da ata anterior
- tribuna livre
- ordem do dia por blocos
- falas e comandos padronizados da presidencia
- encaminhamento para comissoes
- pedido de urgencia
- leitura de parecer
- defesa do autor
- controle de fase da materia
- geracao automatica de um documento final de pauta

Em resumo:

- **como e hoje:** sistema de sessao e votacao com materias e texto de apoio
- **como deve ficar:** sistema de sessao, pauta, rito, presidencia e votacao

---

## O Que Foi Encontrado Nas Pautas

Os tres documentos seguem uma estrutura muito parecida.

### 1. Cabecalho da sessao

Sempre existe uma identificacao formal da sessao:

- numero ordinal da sessao
- tipo da sessao
- orgao legislativo
- data por extenso

Exemplos observados:

- `1ª (PRIMEIRA) SESSAO ORDINARIA`
- `2ª (SEGUNDA) SESSAO ORDINARIA`
- `3ª (TERCEIRA) SESSAO ORDINARIA`

### 2. Bloco de abertura

Existe uma sequencia de falas padronizadas da presidencia:

- boas-vindas
- invocacao religiosa
- declaracao formal de abertura

### 3. Bloco da ata anterior

As pautas trazem o rito da ata:

- leitura da ata da sessao anterior
- discussao
- votacao da ata

### 4. Leitura da pauta do dia

Ha sempre um momento de transicao para a pauta principal.

### 5. Itens de cerimonial ou participacao externa

Em algumas pautas aparece:

- tribuna livre
- convidado externo
- autoridade municipal
- tempo de fala

### 6. Materias com roteiro detalhado

Cada materia nao aparece apenas como cadastro.
Ela aparece com o **passo a passo de conducao**:

- apresentacao da materia
- encaminhamento para comissoes
- pedido de urgencia
- dispensa de reuniao das comissoes
- leitura da materia
- leitura do parecer
- defesa do autor
- discussao do merito
- votacao
- anuncio do resultado

### 7. Diferentes tipos de materia

Foram identificados:

- Projeto de Lei
- Projeto de Lei Complementar
- Projeto de Lei Legislativo
- Indicacao
- Emenda Supressiva
- Emenda a Lei Organica

### 8. Fases diferentes de votacao

As pautas distinguem fases como:

- unica votacao
- primeira votacao
- segunda e ultima votacao

### 9. Encerramento

No final existe fechamento padronizado:

- fala final da presidencia
- encerramento da sessao

---

## Como O Sistema E Hoje

Com base no codigo atual, o sistema trabalha principalmente com estes objetos:

- `sessoes`
- `materias`
- `votos`
- `atas`
- `logs`

Hoje a sessao possui:

- titulo
- numero
- ano
- tipo
- data
- hora
- presidente
- resumo
- status

Hoje a materia possui:

- tipo
- numero
- ano
- titulo
- ementa
- regime
- texto do presidente
- texto do parlamentar
- status
- flag de exige votacao

### Pontos positivos do modelo atual

- ja existe separacao entre sessao e materia
- ja existe um campo de apoio para a presidencia
- ja existe historico da sessao
- ja existe votacao por materia
- ja existe base para fluxo administrativo

### Limites do modelo atual

O modelo atual entende a pauta como:

- uma sessao
- com uma lista de materias

Mas as pautas reais sao:

- uma sessao
- com blocos de rito
- com itens de cerimonial
- com materias
- com roteiro de conducao por etapa
- com falas da presidencia
- com decisoes de fluxo

Ou seja: o sistema atual modela bem **materias e votacao**, mas ainda modela pouco **o rito da sessao**.

---

## O Que Precisa Mudar

Para o sistema conseguir gerar uma visao no estilo dessas pautas, ele precisa sair do conceito de "lista de materias" e passar para o conceito de "roteiro estruturado da sessao".

### 1. Criar o conceito de itens da pauta

Hoje a pauta depende quase toda de texto livre.

O ideal e que a sessao tenha uma lista ordenada de itens de pauta, onde cada item tenha:

- ordem
- tipo do item
- titulo
- descricao
- texto de conducao
- observacoes
- tempo previsto
- vinculo opcional com materia
- status de execucao

Exemplos de tipos de item:

- abertura
- leitura_ata
- votacao_ata
- leitura_pauta
- tribuna_livre
- apresentacao_materia
- encaminhamento_comissoes
- pedido_urgencia
- leitura_parecer
- defesa_autor
- discussao
- votacao_materia
- proclamacao_resultado
- encerramento

### 2. Separar rito fixo da sessao e rito da materia

Hoje o sistema mistura muita coisa no campo `texto_presidente`.

O ideal e dividir em dois niveis:

- **roteiro da sessao**
- **roteiro da materia**

#### Roteiro da sessao

Deve conter blocos padrao como:

- abertura
- leitura da ata
- pauta do dia
- tribuna
- encerramento

#### Roteiro da materia

Deve conter a sequencia operacional da materia:

- apresentacao
- leitura
- parecer
- defesa
- discussao
- votacao
- resultado

### 3. Modelar melhor o tipo e a fase da materia

O sistema hoje ja tem `tipo` e `regime`, mas ainda nao representa bem a fase de tramitacao e a fase de votacao.

Seria importante adicionar:

- fase_tramitacao
- fase_votacao
- precisa_parecer
- permite_urgencia
- exige_defesa_autor
- exige_encaminhamento_comissoes

Exemplos:

- `SEM_VOTACAO`
- `UNICA_VOTACAO`
- `PRIMEIRA_VOTACAO`
- `SEGUNDA_E_ULTIMA`

### 4. Cadastrar comissoes da materia

Nas pautas analisadas, o encaminhamento para comissoes e recorrente.

Hoje isso ainda aparece apenas como texto.

O ideal e o sistema registrar:

- comissoes vinculadas a materia
- ordem de exibicao
- texto padrao de encaminhamento

Assim a pauta pode gerar automaticamente frases como:

- Encaminho para as Comissoes de Constituicao, Justica e Redacao
- Orcamento, Financas e Tributacao
- Educacao, Saude e Outros Servicos Publicos

### 5. Cadastrar tribuna livre e convidados

As pautas mostram que nem tudo e materia.

Deve existir cadastro de item de cerimonial com:

- nome do convidado
- cargo
- tema
- tempo de fala
- observacao para a presidencia

### 6. Criar templates de falas da presidencia

Hoje o sistema deixa muito texto manual.

O ideal e ter uma base de frases padrao parametrizadas.

Exemplos:

- `Declaro aberta a presente sessao`
- `Peco a leitura da ata da sessao anterior`
- `Peco a apresentacao do {tipo} nº {numero}/{ano}`
- `Faco a votacao do {tipo} nº {numero}/{ano}`
- `{tipo} aprovado / reprovado por unanimidade`

Com isso, o sistema pode montar automaticamente a pauta e a tela da presidencia.

### 7. Criar uma visao especifica "Roteiro da Presidencia"

O painel atual da presidencia e mais operacional para votacao do que para conduzir a sessao.

Deve existir uma tela nova com:

- sessao atual
- item atual do roteiro
- proximo item
- falas sugeridas
- materia vinculada
- botoes de avancar etapa
- status do rito
- destaque visual do que esta em andamento

Essa tela seria o equivalente digital da pauta impressa.

### 8. Criar exportacao de pauta

O sistema deve conseguir gerar:

- pauta em HTML para visualizacao
- PDF para impressao
- DOCX opcional no futuro

O ideal e montar primeiro em HTML bem estruturado e depois exportar para PDF.

---

## Como E Hoje X Como Vai Ser

## Como e hoje

- a sessao possui dados basicos
- as materias possuem texto livre para o presidente
- o presidente ve uma lista de materias e controla votacao
- a pauta formal continua dependendo de documento externo
- varios passos do rito ainda ficam fora do sistema

## Como vai ser

- a sessao tera um roteiro completo
- cada item da pauta sera estruturado
- o sistema sabera o que e abertura, ata, tribuna, materia, urgencia, parecer, discussao e encerramento
- a presidencia tera uma tela de conducao da sessao
- a pauta podera ser gerada automaticamente para visualizacao e impressao
- as materias terao fases e comportamentos mais fieis ao rito real

---

## Proposta De Evolucao Funcional

### Fase 1 - Organizar a base

Objetivo:
permitir que a sessao tenha um roteiro estruturado.

Mudancas sugeridas:

- criar tabela `sessao_itens`
- criar tabela `materia_comissoes`
- ampliar o cadastro de materia com campos de rito
- permitir itens livres e itens vinculados a materia

Resultado esperado:

- o sistema passa a entender a pauta como uma lista ordenada de eventos

### Fase 2 - Melhorar a experiencia da presidencia

Objetivo:
transformar a pauta em painel de conducao.

Mudancas sugeridas:

- criar a tela `roteiro da presidencia`
- destacar item atual e proximo item
- exibir fala padrao e observacoes
- permitir marcar item como concluido
- permitir avancar sessao por etapas

Resultado esperado:

- o presidente consegue conduzir a sessao olhando para o sistema, sem depender tanto de pauta externa

### Fase 3 - Geracao automatica de pauta

Objetivo:
fazer o sistema emitir documento semelhante aos `.docx` analisados.

Mudancas sugeridas:

- template de pauta em HTML
- bloco de cabecalho da sessao
- bloco de abertura
- bloco da ata
- bloco da tribuna
- bloco das materias
- bloco de encerramento
- exportacao para PDF

Resultado esperado:

- a pauta passa a ser produzida a partir dos dados cadastrados no sistema

### Fase 4 - Fechamento completo do ciclo

Objetivo:
integrar pauta, sessao, votacao e historico.

Mudancas sugeridas:

- reaproveitar o roteiro executado para gerar historico
- reaproveitar apuracao para ata e resumo
- manter trilha do que foi lido, discutido e votado

Resultado esperado:

- o sistema deixa de ser apenas de votacao e passa a ser de gestao completa da sessao legislativa

---

## Estrutura Sugerida De Dados

### Tabela `sessao_itens`

Campos sugeridos:

- id
- sessao_id
- ordem
- tipo_item
- titulo
- descricao
- texto_presidencia
- tempo_previsto
- materia_id nullable
- convidado_nome nullable
- convidado_cargo nullable
- status_item
- created_at
- updated_at

### Tabela `materia_comissoes`

Campos sugeridos:

- id
- materia_id
- nome_comissao
- ordem

### Novos campos em `materias`

- fase_votacao
- exige_parecer
- exige_defesa_autor
- permite_pedido_urgencia
- texto_resultado_aprovado
- texto_resultado_reprovado

---

## Sugestao De Telas

### 1. Cadastro da sessao

Manter e ampliar com:

- configuracao de rito padrao
- selecao de modelo de sessao

### 2. Editor de pauta

Nova tela para:

- ordenar itens
- incluir tribuna livre
- incluir blocos fixos
- vincular materias
- editar textos de conducao

### 3. Painel da presidencia

Nova tela principal da sessao, contendo:

- cabecalho da sessao
- item atual
- fala sugerida
- botoes de avancar
- materia atual
- comandos de votacao

### 4. Gerador de documento

Tela para:

- visualizar pauta em formato oficial
- imprimir
- exportar PDF

---

## Beneficios Esperados

- menos dependencia de documento manual externo
- pauta padronizada
- presidencia mais segura na conducao da sessao
- historico mais fiel ao que ocorreu
- base pronta para gerar ata e relatorios
- melhor usabilidade para a secretaria e para o administrativo

---

## Conclusao

Os documentos analisados mostram que o sistema precisa evoluir de:

- **sistema de votacao com materias**

para:

- **sistema de pauta, rito, presidencia e votacao**

O caminho mais seguro e fazer isso em etapas:

1. estruturar os itens da pauta
2. criar a visao da presidencia
3. gerar a pauta automaticamente
4. integrar isso ao historico e a ata

Com essa evolucao, o sistema conseguira produzir uma experiencia muito mais proxima do funcionamento real das sessoes e das pautas que hoje ainda sao mantidas em documentos separados.
