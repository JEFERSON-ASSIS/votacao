# Backlog de Reconstrução - 2026-05-23

## Objetivo

Transformar a base reconstruída em um sistema legislativo funcional, com foco no fluxo da presidência, votação e rastreabilidade.

## Estado Atual

Entregues até aqui:

- login e logout
- dashboard administrativo
- painel público da sessão
- cadastro e listagem de sessões
- cadastro e edição de pauta
- painel da presidência
- layout administrativo base
- instalador e schema inicial

## Backlog Prioritário

### P0 - Essencial para operação

1. Criar menu real do `/admin`
   - Organizar o painel interno por áreas funcionais.
   - Incluir atalhos para sessões, pauta, presidência, usuários e relatórios.
   - Ajustar o fluxo para que o dashboard deixe de ser apenas uma tela-resumo.

2. Implementar permissões e perfis
   - Definir papéis de acesso.
   - Aplicar autorização por rota, ação e área.
   - Separar o que admin, presidência, secretaria e usuário comum podem fazer.

3. Estruturar votação nominal
   - Criar modelagem de votos.
   - Registrar presença, quórum e voto por usuário.
   - Permitir acompanhamento em tempo real da votação.

4. Implementar apuração e resultado
   - Consolidar votos por matéria.
   - Exibir resultado por tipo de votação.
   - Registrar situação final da matéria.

### P1 - Fluxo legislativo

5. Expandir a tela de pauta para o padrão SAPL
   - Melhorar hierarquia visual.
   - Organizar a pauta por blocos e etapas.
   - Destacar ações da presidência com mais clareza.

6. Estruturar histórico da sessão
   - Salvar eventos operacionais da sessão.
   - Registrar abertura, leitura, discussão, votação e encerramento.
   - Permitir consulta posterior do que aconteceu em cada sessão.

7. Criar atas
   - Modelar ata da sessão.
   - Permitir geração/edição de minuta.
   - Relacionar a ata aos eventos e à pauta.

### P2 - Evolução e acabamento

8. Refinar a navegação administrativa
   - Ajustar sidebar, títulos e estados ativos.
   - Melhorar a experiência em telas de cadastro e detalhe.

9. Fortalecer a auditoria
   - Registrar quem alterou o quê e quando.
   - Guardar trilha de mudanças de pauta, sessão e votação.

10. Padronizar interface e componentes
   - Unificar botões, cartões, tabelas e formulários.
   - Reduzir estilos inline e concentrar o visual no CSS.

## Ordem Recomendada de Execução

1. Permissões e perfis
2. Menu real do admin
3. Votação nominal
4. Apuração e resultado
5. Histórico da sessão
6. Atas
7. Ajustes visuais do fluxo legislativo
8. Auditoria e refinamentos

## Observações Técnicas

- O schema atual ainda é enxuto e não cobre votação nem histórico.
- O `role` já existe em `users`, mas ainda não há regra de autorização aplicada de forma consistente.
- A base atual é suficiente para começar a evolução sem refazer a reconstrução.

## Próxima Entrega Sugerida

Criar primeiro a camada de permissões e o menu real do `/admin`, porque isso organiza o restante do trabalho e reduz retrabalho nas telas futuras.
