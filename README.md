# Plenario

Sistema de condução de sessão, roteiro do presidente, pauta (import SAPL por matéria) e votação por passo.

## Estrutura

- `app/` — código da aplicação
- `public/` — entrada web (`index.php`) e assets
- `database/` — schema e seeds
- `scripts/` — migrações PHP

## Inicialização

1. Configure o banco em `app/Config/config.php` (via `.env` ou variáveis `DB_*`).
2. Execute as migrações (uma vez por ambiente):

```bash
php scripts/migrate_all.php
```

Ou individualmente:

```bash
php scripts/migrate_conducao.php
php scripts/migrate_roteiro_ativo.php
php scripts/migrate_votacao_rodadas.php
php scripts/migrate_materia_sapl_columns.php
php scripts/migrate_sessao_ata.php
```

3. Acesse via Apache/XAMPP apontando para `public/` (ou `APP_BASE_URL`).

## Fluxo operacional (sessão com roteiro)

1. **Sessões** — criar/editar sessão, status **Aberta**.
2. Montar **pauta** (formulário ou import JSON SAPL na matéria).
3. Bloco **Ata da sessão anterior** (`#ata`) — vínculo, texto ou carregar da sessão anterior.
4. **Gerar condução** → **Montar roteiro** (ordenar, textos, ⊘ passos opcionais).
5. **Presidência → Conduzir sessão** — leitura, ata, **Abrir votação** em cada passo marcado.
6. **Votação** — presença e painel da rodada aberta (atalho a partir de Conduzir).
7. Ao encerrar — preencher **Ata desta sessão** para a próxima.

## Votação

- **Com roteiro:** use sempre **Conduzir sessão**; cada passo com “votação” abre uma rodada no painel.
- **Sem roteiro:** menu Votação no modo legado (voto por item da pauta).

## Histórico, auditoria e pauta

- **Histórico** (`?r=historico/index`) — linha do tempo da sessão (eventos + votações encerradas).
- **Auditoria** (`?r=auditoria/index`) — trilha de alterações (perfil administrador).
- **Exportar pauta** — na sessão ou no histórico, abre HTML para impressão/PDF.
- **Minuta de ata** — aba Ata na central da sessão → “Gerar minuta automática”.
- **Cerimonial** — aba na central da sessão (tribuna, convidados).
- **Telão** (`?r=publico/index`) — pausa no resultado ~10 s após encerrar votação; placar com ausentes quando houver.

Migração das tabelas novas:

```bash
php scripts/migrate_historico_auditoria.php
```

## Login padrão

Criado pelo `scripts/install.php` se existir (ver script de instalação do projeto).
