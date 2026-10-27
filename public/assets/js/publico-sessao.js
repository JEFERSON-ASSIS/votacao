/**
 * Telão — giro contínuo entre telas; durante votação aberta, fixa na tela da rodada.
 */
(function () {
    'use strict';

    var root = document.getElementById('publico-sessao');
    if (!root) return;

    var estadoUrl = root.getAttribute('data-estado-url') || '';
    var streamUrl = root.getAttribute('data-stream-url') || '';
    var segPorSlide = parseFloat(root.getAttribute('data-carousel-seg') || '5') || 5;
    var pausaResultadoSeg = parseFloat(root.getAttribute('data-pausa-resultado-seg') || '10') || 10;
    var liveBadge = document.getElementById('publico-live-badge');
    var carousel = document.getElementById('publico-carousel');
    var track = document.getElementById('publico-carousel-track');
    var contador = document.getElementById('publico-carousel-contador');

    var es = null;
    var sseAtivo = false;
    var pollTimer = null;
    var resizeTimer = null;
    var slidesKeys = '';
    var ultimoEstado = null;
    var rodadaPausaResultado = null;
    var pausaResultadoTimer = null;

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function escapeAttr(s) {
        return escapeHtml(s).replace(/"/g, '&quot;');
    }

    function setLive(on) {
        if (!liveBadge) return;
        liveBadge.classList.toggle('is-offline', !on);
    }

    // O telão consulta o servidor a cada 1 s, sempre — o canal ao vivo é só um extra.
    // Assim ele nunca depende de uma conexão longa que o proxy/rede pode derrubar em silêncio.
    var POLL_MS = 1000;
    var ultimaAtualizacao = Date.now();

    function reiniciarPolling() {
        if (pollTimer) return;
        pollTimer = setInterval(buscarEstado, POLL_MS);
    }

    function placarTelaoHtml(resumo) {
        var r = resumo || {};
        return (
            '<div class="public-telao-placar" role="group" aria-label="Placar da votação">' +
            '<div class="public-telao-placar-col public-telao-placar-col--sim">' +
            '<span class="public-telao-placar-num">' +
            escapeHtml(r.SIM || 0) +
            '</span><span class="public-telao-placar-label">Sim</span></div>' +
            '<div class="public-telao-placar-col public-telao-placar-col--nao">' +
            '<span class="public-telao-placar-num">' +
            escapeHtml(r.NAO || 0) +
            '</span><span class="public-telao-placar-label">Não</span></div>' +
            '<div class="public-telao-placar-col public-telao-placar-col--abst">' +
            '<span class="public-telao-placar-num">' +
            escapeHtml(r.ABSTENCAO || 0) +
            '</span><span class="public-telao-placar-label">Abstenção</span></div>' +
            ((r.AUSENTE || 0) > 0
                ? '<div class="public-telao-placar-col public-telao-placar-col--aus">' +
                  '<span class="public-telao-placar-num">' +
                  escapeHtml(r.AUSENTE) +
                  '</span><span class="public-telao-placar-label">Ausente</span></div>'
                : '') +
            '</div>'
        );
    }

    function parlamentarCardGrande(p) {
        var foto = p.foto_url
            ? '<img src="' + escapeAttr(p.foto_url) + '" alt="" onerror="this.onerror=null;this.remove()">'
            : '<span class="public-vereador-iniciais">' + escapeHtml(p.iniciais || '?') + '</span>';
        var voto = p.voto
            ? '<span class="voto-chip ' +
              escapeAttr(p.voto_classe || '') +
              '">' +
              escapeHtml(p.voto_label || p.voto) +
              '</span>'
            : '<span class="public-vereador-aguardando">Aguardando voto</span>';
        return (
            '<li class="public-vereador-card public-vereador-card--carrossel' +
            (p.voto ? ' public-vereador-card--votou' : '') +
            '">' +
            '<div class="public-vereador-foto">' +
            foto +
            '</div>' +
            '<div class="public-vereador-info">' +
            '<strong>' +
            escapeHtml(p.nome) +
            '</strong>' +
            '<span>' +
            escapeHtml(p.partido || '—') +
            '</span>' +
            '</div>' +
            voto +
            '</li>'
        );
    }

    function gridParlamentares(lista) {
        if (!lista || !lista.length) {
            return '<p class="public-carrossel-sem-votos">Nenhum voto registrado nesta rodada.</p>';
        }
        var n = lista.length;
        var gridClass =
            'public-vereador-grid public-vereador-grid--carrossel' +
            (n >= 9 ? ' public-vereador-grid--denso' : '') +
            (n >= 12 ? ' public-vereador-grid--muitos' : '');
        return (
            '<ul class="' +
            gridClass +
            '" data-parl-count="' +
            n +
            '">' +
            lista.map(parlamentarCardGrande).join('') +
            '</ul>'
        );
    }

    function slideDestaqueHtml(d) {
        var dest = d && d.destaque ? d.destaque : null;
        if (!dest || !dest.ativo) {
            return '';
        }
        var foto = dest.foto_url
            ? '<img class="public-destaque-foto" src="' +
              escapeAttr(dest.foto_url) +
              '" alt="' +
              escapeAttr(dest.nome || '') +
              '">'
            : '<div class="public-destaque-foto public-destaque-foto--iniciais" aria-hidden="true">' +
              escapeHtml(dest.iniciais || '?') +
              '</div>';
        var meta = [];
        if ((dest.partido || '').trim()) {
            meta.push('<span class="public-destaque-partido">Partido ' + escapeHtml(dest.partido) + '</span>');
        }
        return (
            '<article class="public-carrossel-slide public-carrossel-slide--destaque">' +
            '<p class="public-carrossel-kicker">Parlamentar em destaque</p>' +
            '<div class="public-destaque-body">' +
            '<div class="public-destaque-pessoa">' +
            foto +
            '<h2 class="public-carrossel-titulo public-destaque-nome">' +
            escapeHtml(dest.nome || '') +
            '</h2>' +
            (meta.length
                ? '<p class="public-destaque-meta">' + meta.join('<span class="public-destaque-sep" aria-hidden="true">·</span>') + '</p>'
                : '') +
            '</div>' +
            '<div class="public-destaque-info">' +
            (d.momento_titulo
                ? '<p class="public-destaque-momento">' + escapeHtml(d.momento_titulo) + '</p>'
                : '') +
            (dest.tempo && window.SessaoTimer
                ? window.SessaoTimer.html(dest.tempo, { className: 'sessao-timer--telao sessao-timer--destaque', label: 'Tempo de fala' })
                : '') +
            '</div></div></article>'
        );
    }

    function slideMomentoHtml(d) {
        return (
            '<article class="public-carrossel-slide public-carrossel-slide--momento">' +
            '<p class="public-carrossel-kicker">Em discussão na sessão</p>' +
            '<h2 class="public-carrossel-titulo">' +
            escapeHtml(d.momento_titulo || 'Sessão plenária') +
            '</h2>' +
            '<p class="public-carrossel-texto public-carrossel-texto--suave">Acompanhe o andamento da sessão plenária.</p>' +
            '</article>'
        );
    }

    function slideVotacaoHtml(r, aoVivo) {
        var badge = aoVivo
            ? '<span class="public-votacao-badge public-votacao-badge--live">Em votação agora</span>'
            : '<span class="public-votacao-badge public-votacao-badge--' +
              (r.resultado === 'APROVADO' ? 'ok' : r.resultado === 'REJEITADO' ? 'no' : 'neutral') +
              '">' +
              escapeHtml(r.resultado_label || 'Encerrada') +
              '</span>';
        return (
            '<article class="public-carrossel-slide public-carrossel-slide--votacao">' +
            '<header class="public-carrossel-cabecalho-votacao">' +
            '<p class="public-carrossel-kicker">' +
            escapeHtml(r.tipo_label || 'Votação') +
            '</p>' +
            '<div class="public-carrossel-slide-titulo-row">' +
            '<h2 class="public-carrossel-titulo">' +
            escapeHtml(r.titulo || '') +
            '</h2>' +
            badge +
            '</div>' +
            (aoVivo && r.tempo && window.SessaoTimer
                ? window.SessaoTimer.html(r.tempo, { className: 'sessao-timer--telao', label: 'Tempo da votação' })
                : '') +
            placarTelaoHtml(r.resumo) +
            '</header>' +
            '<div class="public-carrossel-slide-corpo">' +
            gridParlamentares(r.parlamentares) +
            '</div>' +
            '</article>'
        );
    }

    function emVotacaoAtiva(d) {
        return !!(d && d.em_votacao && d.rodada_aberta);
    }

    function montarSlides(d) {
        if (rodadaPausaResultado) {
            return [
                {
                    key: 'pausa-' + rodadaPausaResultado.id,
                    html: slideVotacaoHtml(rodadaPausaResultado, false),
                },
            ];
        }

        if (emVotacaoAtiva(d)) {
            return [
                {
                    key: 'votacao-' + d.rodada_aberta.id,
                    html: slideVotacaoHtml(d.rodada_aberta, true),
                },
            ];
        }

        if (d.destaque && d.destaque.ativo) {
            return [
                {
                    key: 'destaque-' + (d.destaque.user_id || '0'),
                    html: slideDestaqueHtml(d),
                },
            ];
        }

        return [
            {
                key: 'momento',
                html: slideMomentoHtml(d),
            },
        ];
    }

    function slidesSignature(slides) {
        return slides
            .map(function (s) {
                return s.key;
            })
            .join('|');
    }

    function htmlPanes(slides) {
        var n = slides.length;
        var blocos = [];
        var vezes = n < 2 ? 1 : 2;

        for (var v = 0; v < vezes; v++) {
            slides.forEach(function (s, i) {
                blocos.push(
                    '<div class="public-carrossel-pane" data-slide-key="' +
                        escapeAttr(s.key) +
                        '" data-set="' +
                        v +
                        '" data-index="' +
                        i +
                        '">' +
                        s.html +
                        '</div>'
                );
            });
        }
        return blocos.join('');
    }

    function syncAnimacaoContinua(slides, reiniciar, fixoVotacao) {
        if (!carousel || !track) return;

        var n = slides.length;
        var w = carousel.clientWidth || carousel.offsetWidth || 0;
        if (w < 1) return;

        var parado = !!fixoVotacao || !!rodadaPausaResultado || n < 2;
        var duracao = Math.max(n * segPorSlide, segPorSlide * 2);

        carousel.style.setProperty('--telao-pane-w', w + 'px');
        carousel.style.setProperty('--telao-slide-count', String(n));
        carousel.style.setProperty('--telao-roll-end', '-' + n * w + 'px');
        carousel.style.setProperty('--telao-roll-duration', duracao + 's');
        root.classList.toggle('publico-sessao--giro-unico', parado);
        root.classList.toggle('publico-sessao--fixo-votacao', !!fixoVotacao);

        if (contador) {
            if (rodadaPausaResultado) {
                contador.textContent = 'Resultado da votação';
            } else if (fixoVotacao) {
                contador.textContent = 'Em votação — tela fixa';
            } else if (n < 2) {
                contador.textContent = 'Transmissão ao vivo';
            } else {
                contador.textContent = n + ' telas · giro contínuo';
            }
        }

        if (reiniciar && n >= 2 && !fixoVotacao) {
            track.style.animation = 'none';
            void track.offsetWidth;
            track.style.removeProperty('animation');
        }
    }

    // Conteúdo já exibido em cada tela do carrossel. Redesenhar sem mudança
    // fazia as fotos piscarem; o valor do cronômetro não conta (ele se atualiza sozinho).
    var conteudoExibido = {};

    function assinaturaConteudo(html) {
        return String(html).replace(/(data-sessao-timer-valor>)[^<]*/g, '$1');
    }

    function atualizarConteudoPanes(slides) {
        if (!track) return;
        slides.forEach(function (s) {
            var assinatura = assinaturaConteudo(s.html);
            if (conteudoExibido[s.key] === assinatura) return;
            conteudoExibido[s.key] = assinatura;
            var panes = track.querySelectorAll(
                '.public-carrossel-pane[data-slide-key="' + s.key + '"]'
            );
            for (var i = 0; i < panes.length; i++) {
                panes[i].innerHTML = s.html;
            }
        });
    }

    function renderCarrossel(slides, fixoVotacao) {
        if (!track) return;

        var sig = slidesSignature(slides);
        var rebuild = sig !== slidesKeys;
        slidesKeys = sig;

        if (rebuild) {
            conteudoExibido = {};
            slides.forEach(function (s) {
                conteudoExibido[s.key] = assinaturaConteudo(s.html);
            });
            track.innerHTML = htmlPanes(slides);
            syncAnimacaoContinua(slides, true, fixoVotacao);
        } else {
            atualizarConteudoPanes(slides);
            syncAnimacaoContinua(slides, false, fixoVotacao);
        }
    }

    function aplicarEstado(d) {
        if (!d || !d.ok) return;

        if (window.SessaoTimer && d.ts) {
            window.SessaoTimer.sincronizar(d.ts);
        }

        var eraVotacao = ultimoEstado && ultimoEstado.em_votacao;
        var agoraVotacao = emVotacaoAtiva(d);
        if (eraVotacao && !agoraVotacao && !rodadaPausaResultado) {
            var hist = d.historico || [];
            if (hist.length) {
                rodadaPausaResultado = hist[hist.length - 1];
                root.classList.add('publico-sessao--pausa-resultado');
                if (pausaResultadoTimer) clearTimeout(pausaResultadoTimer);
                pausaResultadoTimer = setTimeout(function () {
                    rodadaPausaResultado = null;
                    root.classList.remove('publico-sessao--pausa-resultado');
                    if (ultimoEstado) {
                        var fixo = emVotacaoAtiva(ultimoEstado);
                        renderCarrossel(montarSlides(ultimoEstado), fixo);
                        syncAnimacaoContinua(montarSlides(ultimoEstado), true, fixo);
                    }
                }, pausaResultadoSeg * 1000);
            }
        }

        ultimoEstado = d;

        var tituloEl = document.getElementById('publico-sessao-titulo');
        if (tituloEl && d.sessao_titulo) {
            tituloEl.textContent = d.sessao_titulo;
        }

        root.classList.toggle('publico-sessao--votando', !!d.em_votacao);
        root.classList.toggle('publico-sessao--ocioso', !d.em_votacao);

        var chip = document.getElementById('publico-status-chip');
        if (chip) {
            chip.textContent = d.status_label || '';
            chip.className = 'public-chip public-chip--' + (d.status_chip || 'open');
        }

        var prog = document.getElementById('publico-progresso-meta');
        if (prog && d.progresso_label) {
            prog.textContent = d.progresso_label + ' passos';
        }

        if (rodadaPausaResultado) {
            renderCarrossel(montarSlides(d), true);
            return;
        }

        var fixoVotacao = emVotacaoAtiva(d);
        var slides = montarSlides(d);
        if (slides.length === 0) {
            if (track) {
                track.innerHTML =
                    '<div class="public-carrossel-pane"><article class="public-carrossel-slide public-carrossel-slide--momento">' +
                    '<p class="public-carrossel-kicker">Sessão</p>' +
                    '<h2 class="public-carrossel-titulo">Aguardando</h2></article></div>';
            }
            syncAnimacaoContinua([{ key: 'x', html: '' }], false, false);
            return;
        }

        renderCarrossel(slides, fixoVotacao);
    }

    var buscando = false;
    var servidorRespondeuEm = 0;

    function aplicarSeguro(d) {
        try {
            aplicarEstado(d);
            ultimaAtualizacao = Date.now();
        } catch (err) {
            // Um erro de desenho não pode travar as próximas atualizações.
            if (window.console) console.error('[telao] falha ao desenhar estado', err);
        }
    }

    function buscarEstado() {
        if (!estadoUrl || buscando) return Promise.resolve();
        buscando = true;
        var ctrl = typeof AbortController === 'function' ? new AbortController() : null;
        var limite = setTimeout(function () {
            if (ctrl) ctrl.abort();
        }, 5000);
        return fetch(estadoUrl + (estadoUrl.indexOf('?') >= 0 ? '&' : '?') + '_=' + Date.now(), {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'application/json' },
            signal: ctrl ? ctrl.signal : undefined,
        })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP');
                return r.json();
            })
            .then(function (d) {
                servidorRespondeuEm = Date.now();
                aplicarSeguro(d);
                setLive(true);
            })
            .catch(function () {
                setLive(false);
            })
            .then(function () {
                clearTimeout(limite);
                buscando = false;
            });
    }

    function conectarStream() {
        if (!streamUrl || typeof EventSource === 'undefined') {
            reiniciarPolling();
            return;
        }
        if (es) es.close();
        try {
            es = new EventSource(streamUrl);
        } catch (e) {
            reiniciarPolling();
            return;
        }

        es.addEventListener('open', function () {
            sseAtivo = true;
            setLive(true);
            reiniciarPolling();
        });

        es.addEventListener('estado', function (ev) {
            var d = null;
            try {
                d = JSON.parse(ev.data);
            } catch (err) { /* ignore */ }
            if (d) {
                aplicarSeguro(d);
                setLive(true);
            }
        });

        es.addEventListener('reconnect', function () {
            sseAtivo = false;
            if (es) es.close();
            es = null;
            setTimeout(conectarStream, 400);
        });

        es.onerror = function () {
            sseAtivo = false;
            if (es) es.close();
            es = null;
            setLive(false);
            reiniciarPolling();
            buscarEstado();
            setTimeout(conectarStream, 2000);
        };
    }

    function onResize() {
        if (resizeTimer) clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            if (!slidesKeys) return;
            var n = slidesKeys.split('|').filter(Boolean).length;
            syncAnimacaoContinua(
                Array.from({ length: n }, function (_, i) {
                    return { key: slidesKeys.split('|')[i] };
                }),
                false,
                emVotacaoAtiva(ultimoEstado)
            );
        }, 120);
    }

    window.addEventListener('resize', onResize);

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            buscarEstado();
        }
    });

    if (window.PUBLICO_SESSAO_INICIAL && window.PUBLICO_SESSAO_INICIAL.ok) {
        aplicarEstado(window.PUBLICO_SESSAO_INICIAL);
        setLive(true);
    }

    buscarEstado();
    reiniciarPolling();
    conectarStream();

    // Vigia: se passar 15 s sem nenhuma atualização, refaz a consulta e o canal ao vivo;
    // depois de 60 s com o servidor respondendo mas a tela sem mudar, recarrega a página.
    setInterval(function () {
        var parado = Date.now() - ultimaAtualizacao;
        // Só recarrega se o servidor está respondendo (senão o telão ficaria numa página de erro).
        if (parado > 60000 && Date.now() - servidorRespondeuEm < 5000) {
            window.location.reload();
        } else if (parado > 15000) {
            buscando = false;
            buscarEstado();
            conectarStream();
        }
    }, 5000);
})();
