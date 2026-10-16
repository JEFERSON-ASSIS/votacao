<?php
declare(strict_types=1);

namespace App\Helpers;

/** Catálogo de permissões do sistema (menu + ações internas). */
final class PermissaoCatalog
{
    /** @return array<string, array{label: string, items: array<string, string>}> */
    public static function grupos(): array
    {
        return [
            'menu' => [
                'label' => 'Menu principal',
                'items' => [
                    'menu.dashboard' => 'Dashboard',
                    'menu.sessoes' => 'Menu: Sessões (admin — editor completo)',
                    'menu.sessoes_parlamentar' => 'Menu: Sessões (parlamentar — ver pauta)',
                    'menu.votacao' => 'Menu: Votação (operador)',
                    'menu.presidencia' => 'Menu: Presidência',
                    'menu.usuarios' => 'Menu: Usuários',
                    'menu.permissoes' => 'Menu: Permissões',
                    'menu.publico' => 'Menu: Visão pública',
                    'menu.historico' => 'Menu: Histórico da sessão',
                    'menu.auditoria' => 'Menu: Auditoria (admin)',
                    'menu.meu_perfil' => 'Menu: Meu perfil',
                ],
            ],
            'parlamentar' => [
                'label' => 'Parlamentar (ações)',
                'items' => [
                    'parlamentar.meuperfil' => 'Meu perfil — abrir tela',
                    'parlamentar.saveperfil' => 'Meu perfil — salvar',
                    'parlamentar.sessoes' => 'Sessões — listar',
                    'parlamentar.votacaoativa' => 'Sessões — alerta global de votação (JSON)',
                    'parlamentar.sessao' => 'Sessões — acompanhar sessão (leitura + voto)',
                    'parlamentar.estado' => 'Sessões — atualização em tempo real (JSON)',
                    'parlamentar.stream' => 'Sessões — canal ao vivo (SSE)',
                    'parlamentar.materia' => 'Sessões — ver matéria',
                    'parlamentar.votar' => 'Sessões — votar (Sim/Não/Abstenção)',
                ],
            ],
            'dashboard' => [
                'label' => 'Dashboard',
                'items' => [
                    'dashboard.index' => 'Acessar dashboard',
                ],
            ],
            'sessao' => [
                'label' => 'Sessões',
                'items' => [
                    'sessao.index' => 'Listar sessões',
                    'sessao.form' => 'Criar e editar sessão',
                    'sessao.ata' => 'Abrir tela da ata da sessão',
                    'sessao.salvarAta' => 'Salvar dados da ata',
                    'sessao.vincularAnteriorSugerida' => 'Vincular sessão anterior sugerida',
                    'sessao.carregarAtaAnterior' => 'Carregar ata da sessão anterior',
                    'sessao.pauta' => 'Abrir pauta da sessão',
                    'sessao.delete' => 'Excluir sessão',
                    'sessao.resetar' => 'Resetar sessão para testes (apaga presenças, votos e andamento)',
                    'sessao.exportarPauta' => 'Exportar pauta (impressão)',
                    'sessao.gerarMinutaAta' => 'Gerar minuta da ata',
                    'sessao.cerimonialSave' => 'Salvar item cerimonial',
                    'sessao.cerimonialDelete' => 'Excluir item cerimonial',
                    'sessao.publicar' => 'Publicar sessão (bloquear edição)',
                ],
            ],
            'historico' => [
                'label' => 'Histórico',
                'items' => [
                    'historico.index' => 'Listar sessões (histórico)',
                    'historico.sessao' => 'Ver linha do tempo da sessão',
                ],
            ],
            'auditoria' => [
                'label' => 'Auditoria',
                'items' => [
                    'auditoria.index' => 'Consultar trilha de alterações',
                ],
            ],
            'pauta' => [
                'label' => 'Pauta',
                'items' => [
                    'pauta.form' => 'Criar e editar item da pauta',
                    'pauta.parseSapl' => 'Interpretar texto SAPL',
                    'pauta.importar' => 'Importar pauta',
                    'pauta.delete' => 'Excluir item da pauta',
                    'pauta.reorder' => 'Reordenar pauta',
                ],
            ],
            'roteiro' => [
                'label' => 'Roteiro / condução',
                'items' => [
                    'roteiro.gerar' => 'Gerar roteiro',
                    'roteiro.montar' => 'Montar roteiro',
                    'roteiro.salvar' => 'Salvar passo do roteiro',
                    'roteiro.anexoSalvar' => 'Incluir anexo no passo do roteiro',
                    'roteiro.anexoExcluir' => 'Excluir anexo do passo do roteiro',
                    'roteiro.reordenar' => 'Reordenar passos',
                    'roteiro.toggleAtivo' => 'Ativar/desativar passo',
                    'roteiro.excluir' => 'Excluir passo',
                    'roteiro.resetarExecucao' => 'Resetar execução do roteiro',
                ],
            ],
            'votacao' => [
                'label' => 'Votação',
                'items' => [
                    'votacao.index' => 'Listar votações',
                    'votacao.painel' => 'Painel de votação',
                    'votacao.estadoRodada' => 'Painel de votação - JSON estado da rodada',
                    'votacao.streamRodada' => 'Painel de votação - canal ao vivo da rodada',
                    'votacao.abrir' => 'Abrir votação (item)',
                    'votacao.encerrar' => 'Encerrar votação (item)',
                    'votacao.votar' => 'Registrar voto (item)',
                    'votacao.apuracao' => 'Apuração (item)',
                    'votacao.abrirRodada' => 'Abrir rodada',
                    'votacao.encerrarRodada' => 'Encerrar rodada',
                    'votacao.votarRodada' => 'Registrar voto (rodada)',
                    'votacao.apuracaoRodada' => 'Apuração (rodada)',
                    'votacao.presenca' => 'Controle de presença',
                ],
            ],
            'presidencia' => [
                'label' => 'Presidência',
                'items' => [
                    'presidencia.index' => 'Painel da presidência',
                    'presidencia.iniciar' => 'Iniciar sessão (Criado → Em andamento)',
                    'presidencia.conduzir' => 'Conduzir sessão',
                    'presidencia.avancar' => 'Avançar passo',
                    'presidencia.voltar' => 'Voltar passo',
                    'presidencia.encerrarSessao' => 'Concluir / encerrar sessão',
                    'presidencia.destacarParlamentar' => 'Destacar parlamentar no telão',
                    'presidencia.limparDestaque' => 'Limpar destaque do telão',
                    'presidencia.transferirConducao' => 'Passar condução',
                    'presidencia.devolverConducao' => 'Devolver condução',
                ],
            ],
            'usuario' => [
                'label' => 'Usuários',
                'items' => [
                    'usuario.index' => 'Listar usuários',
                    'usuario.form' => 'Criar e editar usuário',
                    'usuario.save' => 'Salvar usuário',
                    'usuario.delete' => 'Excluir usuário',
                ],
            ],
            'permissoes' => [
                'label' => 'Permissões',
                'items' => [
                    'permissoes.index' => 'Tela de permissões',
                    'permissoes.save' => 'Salvar permissões de usuário',
                ],
            ],
            'publico' => [
                'label' => 'Visão pública',
                'items' => [
                    'publico.index' => 'Acessar visão pública',
                    'publico.sessao' => 'Visão pública — sessão específica',
                    'publico.estado' => 'Visão pública — JSON estado (sem login)',
                    'publico.stream' => 'Visão pública — SSE ao vivo (sem login)',
                    'publico.atual' => 'Visão pública — sessão atual (JSON)',
                ],
            ],
        ];
    }

    /** Pacote completo para perfil parlamentar (menu Sessões + pauta + voto). */
    public static function chavesParlamentar(): array
    {
        return [
            'dashboard.index',
            'menu.dashboard',
            'menu.meu_perfil',
            'menu.sessoes_parlamentar',
            'menu.publico',
            'menu.historico',
            'publico.index',
            'historico.index',
            'historico.sessao',
            'parlamentar.meuperfil',
            'parlamentar.saveperfil',
            'parlamentar.sessoes',
            'parlamentar.sessao',
            'parlamentar.estado',
            'parlamentar.stream',
            'parlamentar.materia',
            'parlamentar.votar',
        ];
    }

    /** Pacote padrao do presidente: conduz sessao e votacao, sem cadastro administrativo. */
    public static function chavesPresidente(): array
    {
        return [
            'dashboard.index',
            'menu.dashboard',
            'menu.sessoes',
            'menu.votacao',
            'menu.historico',
            'menu.publico',
            'sessao.index',
            'presidencia.index',
            'presidencia.iniciar',
            'presidencia.conduzir',
            'presidencia.avancar',
            'presidencia.voltar',
            'presidencia.encerrarSessao',
            'presidencia.destacarParlamentar',
            'presidencia.limparDestaque',
            'presidencia.transferirConducao',
            'presidencia.devolverConducao',
            'roteiro.resetarExecucao',
            'votacao.index',
            'votacao.painel',
            'votacao.estadoRodada',
            'votacao.streamRodada',
            'votacao.abrir',
            'votacao.encerrar',
            'votacao.votar',
            'votacao.apuracao',
            'votacao.abrirRodada',
            'votacao.encerrarRodada',
            'votacao.votarRodada',
            'votacao.apuracaoRodada',
            'votacao.presenca',
            'historico.index',
            'historico.sessao',
            'publico.index',
        ];
    }

    /** Pacote padrao do administrador: acesso completo ao sistema. */
    public static function chavesAdministrador(): array
    {
        return self::todasChaves();
    }

    /** @return list<string> */
    public static function chavesPadraoPerfil(string $role): array
    {
        $role = UserPerfil::normalize($role);

        return match ($role) {
            UserPerfil::ADMINISTRADOR => self::chavesAdministrador(),
            UserPerfil::PRESIDENTE => self::chavesPresidente(),
            UserPerfil::PARLAMENTAR => self::chavesParlamentar(),
            default => [
                'dashboard.index',
                'menu.dashboard',
                'menu.publico',
                'publico.index',
            ],
        };
    }

    /** @return list<string> */
    public static function todasChaves(): array
    {
        $keys = [];
        foreach (self::grupos() as $grupo) {
            foreach ($grupo['items'] as $chave => $_) {
                $keys[] = $chave;
            }
        }

        return $keys;
    }

    public static function rotulo(string $chave): string
    {
        foreach (self::grupos() as $grupo) {
            if (isset($grupo['items'][$chave])) {
                return $grupo['items'][$chave];
            }
        }

        return $chave;
    }

    public static function chaveRota(string $controller, string $action): string
    {
        $chaveNormalizada = strtolower($controller) . '.' . strtolower($action);
        static $mapa = null;
        if ($mapa === null) {
            $mapa = [];
            foreach (self::todasChaves() as $chave) {
                $mapa[strtolower($chave)] = $chave;
            }
            foreach (self::rotasPublicas() as $chave) {
                $mapa[strtolower($chave)] = $chave;
            }
        }

        return $mapa[$chaveNormalizada] ?? $chaveNormalizada;
    }

    /** Rotas que não exigem login. */
    public static function rotasPublicas(): array
    {
        return [
            'auth.login',
            'auth.logout',
            'publico.index',
            'publico.sessao',
            'publico.estado',
            'publico.stream',
            'publico.atual',
            'home.index',
        ];
    }
}
