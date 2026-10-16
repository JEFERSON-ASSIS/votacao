/**
 * Acompanhar sessão — tempo real: SSE (push) + polling (fallback) + alerta de votação.
 */
(function () {
    'use strict';

    var root = document.getElementById('parl-acompanhar');
    if (!root) return;

    var streamUrl = root.getAttribute('data-stream-url') || '';
    var estadoUrl = root.getAttribute('data-estado-url') || '';
    var votoUrl = root.getAttribute('data-voto-url') || '';
    var ultimoRodadaId = parseInt(root.getAttribute('data-rodada-inicial') || '0', 10) || 0;
    var ultimaPodeVotar = root.getAttribute('data-pode-votar-inicial') === '1';
    var alertaDismissRodada = 0;
    var votoBloqueadoLocal = false;
    var pollTimer = null;
    var pollMsRapido = 500;
    var pollMsLento = 2000;
    var es = null;
    var sseAtivo = false;

    function hintModo(modo) {
        if (modo === 'votacao') return 'O presidente abriu a votação. Use o box abaixo para votar.';
        if (modo === 'voto_registrado') return 'Seu voto foi registrado. Aguarde o encerramento da votação pelo presidente.';
        if (modo === 'leitura') return 'Modo leitura — acompanhe o texto exibido pelo presidente neste momento.';
        return 'Aguardando o próximo passo da sessão.';
    }

    function tagModo(modo) {
        if (modo === 'votacao' || modo === 'voto_registrado') {
            return { cls: 'parl-momento-tag--vote', txt: 'Votação' };
        }
        if (modo === 'leitura') {
            return { cls: 'parl-momento-tag--leitura', txt: 'Leitura' };
        }
        return { cls: '', txt: 'Aguardando' };
    }

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function nl2brEscaped(s) {
        return escapeHtml(s).replace(/\n/g, '<br>');
    }

    function toast(msg, ok) {
        var el = document.getElementById('parl-toast');
        if (!el) {
            el = document.createElement('div');
            el.id = 'parl-toast';
            el.className = 'parl-toast';
            el.setAttribute('role', 'status');
            document.body.appendChild(el);
        }
        el.className = 'parl-toast' + (ok ? ' parl-toast--ok' : ' parl-toast--err');
        el.textContent = msg;
        el.classList.add('is-visible');
        clearTimeout(toast._t);
        toast._t = setTimeout(function () {
            el.classList.remove('is-visible');
        }, 3200);
    }

    function setLiveOnline(online, modo) {
        var live = document.getElementById('parl-live-badge');
        if (!live) return;
        live.classList.toggle('is-offline', !online);
        if (!online) {
            live.title = 'Reconectando…';
            return;
        }
        if (modo === 'sse') {
            live.title = 'Ao vivo — push instantâneo (SSE)';
        } else if (modo === 'poll') {
            live.title = 'Ao vivo — atualização por consulta (fallback)';
        } else {
            live.title = 'Conexão ao vivo ativa';
        }
    }

    function reiniciarPolling() {
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(buscarEstado, sseAtivo ? pollMsLento : pollMsRapido);
    }

    function ensureAlertaVotacao() {
        var el = document.getElementById('parl-alerta-votacao');
        if (el) return el;

        el = document.createElement('div');
        el.id = 'parl-alerta-votacao';
        el.className = 'parl-alerta-votacao';
        el.setAttribute('role', 'alert');
        el.setAttribute('aria-live', 'assertive');
        el.style.gridColumn = '1 / -1';
        el.innerHTML =
            '<div class="parl-alerta-votacao-inner">' +
            '<span class="parl-alerta-icone" aria-hidden="true">!</span>' +
            '<div class="parl-alerta-corpo">' +
            '<strong class="parl-alerta-titulo">Votação aberta — vote agora</strong>' +
            '<p class="parl-alerta-texto parl-alerta-subtitulo"></p>' +
            '</div>' +
            '<div class="parl-alerta-acoes">' +
            '<button type="button" class="btn btn-primary btn--sm" id="parl-alerta-ir-voto">Ir para o voto</button>' +
            '<button type="button" class="btn btn-soft btn--sm" id="parl-alerta-fechar" aria-label="Fechar aviso">×</button>' +
            '</div></div>';

        var hero = root.querySelector('.parl-acompanhar-hero');
        if (hero && hero.parentNode) {
            hero.parentNode.insertBefore(el, hero.nextSibling);
        } else {
            root.insertBefore(el, root.firstChild);
        }

        el.querySelector('#parl-alerta-fechar').addEventListener('click', function () {
            alertaDismissRodada = ultimoRodadaId;
            el.classList.remove('is-visible');
        });
        el.querySelector('#parl-alerta-ir-voto').addEventListener('click', irParaVoto);

        return el;
    }

    function irParaVoto() {
        var box = document.getElementById('parl-voto-box');
        if (box) {
            box.scrollIntoView({ behavior: 'smooth', block: 'center' });
            box.classList.add('parl-voto-card--pulse');
            setTimeout(function () {
                box.classList.remove('parl-voto-card--pulse');
            }, 1200);
        }
    }

    function mostrarAlertaVotacao(d, animar) {
        if (!d.pode_registrar_voto || !d.rodada_id) return;
        if (alertaDismissRodada === d.rodada_id) return;

        var el = ensureAlertaVotacao();
        var sub = el.querySelector('.parl-alerta-subtitulo');
        var titulo = (d.rodada_titulo || d.titulo_momento || 'Votação nominal').trim();
        if (sub) sub.textContent = titulo;

        el.classList.add('is-visible');
        if (animar) {
            el.classList.remove('parl-alerta-votacao--pulse');
            void el.offsetWidth;
            el.classList.add('parl-alerta-votacao--pulse');
            irParaVoto();
        }
    }

    function ocultarAlertaVotacao() {
        var el = document.getElementById('parl-alerta-votacao');
        if (el) el.classList.remove('is-visible');
    }

    function setChipRodada(rodadaId) {
        var chip = document.getElementById('parl-rodada-chip');
        var badges = document.querySelector('.parl-materia-hero-badges');
        if (rodadaId > 0) {
            if (!chip && badges) {
                chip = document.createElement('span');
                chip.className = 'status-chip vote';
                chip.id = 'parl-rodada-chip';
                chip.textContent = 'Votação aberta';
                badges.appendChild(chip);
            }
            if (chip) chip.style.display = '';
        } else if (chip) {
            chip.style.display = 'none';
        }
    }

    function setStatusSessao(d) {
        var chip = document.getElementById('parl-status-chip');
        if (chip) {
            chip.textContent = d.status_label || '';
            chip.className = 'status-chip ' + (d.status_chip || 'open');
        }
    }

    function setProgresso(d) {
        var bar = document.querySelector('.parl-acompanhar-progress-bar > div');
        var meta = document.querySelector('.parl-acompanhar-progress .panel-meta');
        if (bar) bar.style.width = (d.progresso_pct || 0) + '%';
        if (meta && d.progresso_label) {
            meta.textContent = 'Roteiro: ' + d.progresso_label + ' passos';
        }
    }

    function setMomento(d) {
        var card = document.getElementById('parl-momento-card');
        var titulo = document.getElementById('parl-momento-titulo');
        var hint = document.getElementById('parl-momento-hint');
        var jaVotou = !!(d.ja_votou || d.meu_voto);
        var modoUi = jaVotou ? 'voto_registrado' : d.modo || '';
        if (card) card.setAttribute('data-modo', modoUi);
        if (titulo && d.titulo_momento) titulo.textContent = d.titulo_momento;
        if (hint) {
            if (jaVotou && d.meu_voto) {
                hint.innerHTML =
                    'Seu voto: <span class="voto-chip ' +
                    escapeHtml(d.meu_voto_classe || '') +
                    '">' +
                    escapeHtml(d.meu_voto_label || '') +
                    '</span> — registrado. Aguarde o encerramento da votação pelo presidente.';
            } else if (jaVotou) {
                hint.textContent =
                    'Seu voto foi registrado. Aguarde o encerramento da votação pelo presidente.';
            } else if (d.pode_registrar_voto) {
                hint.textContent =
                    'O presidente abriu a votação. Use o box abaixo para votar.';
            } else {
                hint.textContent = hintModo(d.modo);
            }
        }
        var tag = card ? card.querySelector('.parl-momento-tag') : null;
        if (tag) {
            var t = tagModo(modoUi || d.modo);
            tag.className = 'parl-momento-tag ' + t.cls;
            tag.textContent = t.txt;
        }
    }

    function setLeitura(d) {
        var panel = document.getElementById('parl-leitura-panel');
        var corpo = document.getElementById('parl-leitura-corpo');
        var texto = (d.texto_leitura || '').trim();
        var emVotacao = d.modo === 'votacao' || d.modo === 'voto_registrado';

        if (texto === '' || emVotacao) {
            if (panel) panel.style.display = 'none';
            return;
        }
        if (!panel) {
            panel = document.createElement('article');
            panel.className = 'panel-card parl-leitura-card';
            panel.id = 'parl-leitura-panel';
            panel.style.gridColumn = '1 / -1';
            panel.innerHTML =
                '<h3 class="parl-leitura-label">Texto do momento</h3>' +
                '<div class="parl-leitura-corpo" id="parl-leitura-corpo"></div>';
            var slot = document.getElementById('parl-voto-slot');
            if (slot && slot.parentNode) {
                slot.parentNode.insertBefore(panel, slot.nextSibling);
            } else {
                root.appendChild(panel);
            }
            corpo = document.getElementById('parl-leitura-corpo');
        }
        panel.style.display = '';
        if (corpo) corpo.innerHTML = nl2brEscaped(texto);
    }

    function setAnexos(d) {
        var panel = document.getElementById('parl-anexos-panel');
        var grid = document.getElementById('parl-anexos-grid');
        var anexos = Array.isArray(d.anexos) ? d.anexos : [];
        if (!panel || !grid) return;
        grid.replaceChildren();
        anexos.forEach(function (anexo) {
            var link = document.createElement('a');
            link.className = 'parl-anexo';
            link.href = String(anexo.href || '#');
            link.target = '_blank';
            link.rel = 'noopener';
            if (anexo.imagem) {
                var img = document.createElement('img');
                img.src = link.href;
                img.alt = '';
                link.appendChild(img);
            }
            var texto = document.createElement('span');
            texto.className = 'parl-anexo-texto';
            var titulo = document.createElement('strong');
            titulo.textContent = anexo.titulo || anexo.nome || 'Link externo';
            var tipo = document.createElement('span');
            tipo.className = 'parl-anexo-tipo';
            tipo.textContent = anexo.tipo === 'URL' ? 'URL externa' : (anexo.imagem ? 'Imagem' : 'Documento');
            texto.appendChild(titulo);
            texto.appendChild(tipo);
            link.appendChild(texto);
            grid.appendChild(link);
        });
        panel.style.display = anexos.length ? '' : 'none';
    }

    function setChipVotoRegistrado(jaVotou) {
        var chip = document.getElementById('parl-voto-registrado-chip');
        var badges = document.querySelector('.parl-materia-hero-badges');
        var rodadaChip = document.getElementById('parl-rodada-chip');
        if (jaVotou) {
            if (rodadaChip) rodadaChip.style.display = 'none';
            if (!chip && badges) {
                chip = document.createElement('span');
                chip.className = 'status-chip open';
                chip.id = 'parl-voto-registrado-chip';
                chip.textContent = 'Voto registrado';
                badges.appendChild(chip);
            } else if (chip) {
                chip.style.display = '';
            }
        } else if (chip) {
            chip.style.display = 'none';
        }
    }

    function servidorDizJaVotou(d) {
        return !!(d.ja_votou || d.meu_voto);
    }

    function setVotoSlot(d) {
        var slot = document.getElementById('parl-voto-slot');
        if (!slot) return;

        if (d.pode_registrar_voto) {
            votoBloqueadoLocal = false;
        }

        if (servidorDizJaVotou(d) || (votoBloqueadoLocal && !d.pode_registrar_voto)) {
            slot.innerHTML = '';
            ultimoRodadaId = d.rodada_id || ultimoRodadaId;
            setChipVotoRegistrado(servidorDizJaVotou(d) || votoBloqueadoLocal);
            setChipRodada(d.pode_registrar_voto ? d.rodada_id || 0 : 0);
            return;
        }

        votoBloqueadoLocal = false;
        setChipVotoRegistrado(false);
        var mostrarForm = !!d.pode_registrar_voto;

        if (mostrarForm && d.voto_html) {
            var novaRodada = d.rodada_id !== ultimoRodadaId;
            if (!slot.querySelector('#parl-voto-box') || novaRodada) {
                slot.innerHTML = d.voto_html;
                ultimoRodadaId = d.rodada_id;
                var box = document.getElementById('parl-voto-box');
                if (box) {
                    box.classList.add('parl-voto-card--entrada');
                    setTimeout(function () {
                        box.classList.remove('parl-voto-card--entrada');
                    }, 600);
                }
            }
        } else if (mostrarForm && !d.voto_html) {
            slot.innerHTML = '';
            buscarEstado();
        } else {
            slot.innerHTML = '';
        }

        setChipRodada(mostrarForm ? d.rodada_id || 0 : 0);
    }

    function aplicarEstado(d) {
        if (!d || !d.ok) return;

        var rodadaAntes = ultimoRodadaId;
        var podeAntes = ultimaPodeVotar;

        setStatusSessao(d);
        setMomento(d);
        setChipRodada(d.rodada_id || 0);
        setProgresso(d);
        setLeitura(d);
        setAnexos(d);
        setVotoSlot(d);

        var ata = document.getElementById('parl-ata-panel');
        if (ata) ata.style.display = d.exibir_ata ? '' : 'none';

        if (d.rodada_id && d.rodada_id !== rodadaAntes) {
            alertaDismissRodada = 0;
            votoBloqueadoLocal = false;
        }

        var precisaAlerta =
            !!d.pode_registrar_voto && !!d.rodada_id && !servidorDizJaVotou(d);
        if (precisaAlerta && alertaDismissRodada !== d.rodada_id) {
            mostrarAlertaVotacao(d, d.rodada_id !== rodadaAntes || !podeAntes);
        } else {
            ocultarAlertaVotacao();
            if (d.ja_votou && d.rodada_id) {
                alertaDismissRodada = d.rodada_id;
            }
            if (!d.pode_votar) {
                alertaDismissRodada = 0;
            }
        }

        ultimaPodeVotar = !!d.pode_registrar_voto;
    }

    function urlEstado() {
        if (!estadoUrl) return '';
        var sep = estadoUrl.indexOf('?') >= 0 ? '&' : '?';
        return estadoUrl + sep + 'html_voto=1&_=' + Date.now();
    }

    function buscarEstado() {
        var url = urlEstado();
        if (!url) return Promise.resolve();

        return fetch(url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function (d) {
                aplicarEstado(d);
                if (!sseAtivo) setLiveOnline(true, 'poll');
            })
            .catch(function () {
                if (!sseAtivo) setLiveOnline(false);
            });
    }

    function conectarStream() {
        if (!streamUrl || typeof EventSource === 'undefined') {
            sseAtivo = false;
            reiniciarPolling();
            return;
        }
        if (es) es.close();
        try {
            es = new EventSource(streamUrl);
        } catch (e) {
            sseAtivo = false;
            reiniciarPolling();
            return;
        }

        es.addEventListener('open', function () {
            sseAtivo = true;
            setLiveOnline(true, 'sse');
            reiniciarPolling();
        });

        es.addEventListener('estado', function (ev) {
            try {
                aplicarEstado(JSON.parse(ev.data));
                setLiveOnline(true, 'sse');
            } catch (err) { /* ignore */ }
        });

        es.addEventListener('reconnect', function () {
            sseAtivo = false;
            es.close();
            setTimeout(conectarStream, 200);
        });

        es.onerror = function () {
            if (sseAtivo) {
                sseAtivo = false;
                setLiveOnline(false);
                reiniciarPolling();
                buscarEstado();
            }
            es.close();
            setTimeout(conectarStream, 1500);
        };
    }

    var ultimoBotaoVoto = null;

    function enviarVoto(form, submitter) {
        var fd = new FormData(form);
        fd.append('ajax', '1');
        var btn =
            (submitter && submitter.name === 'voto' ? submitter : null) ||
            ultimoBotaoVoto ||
            form.querySelector('button[type="submit"][name="voto"]:focus') ||
            form.querySelector('button[type="submit"][name="voto"]');
        if (btn && btn.name === 'voto' && btn.value) {
            fd.set('voto', btn.value);
        }
        if (btn) btn.disabled = true;

        fetch(votoUrl, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(function (r) {
                return r.text().then(function (text) {
                    var j = null;
                    try {
                        j = text ? JSON.parse(text) : null;
                    } catch (parseErr) {
                        j = {
                            ok: false,
                            mensagem: text
                                ? text.replace(/<[^>]+>/g, ' ').trim().slice(0, 180)
                                : 'Resposta inválida do servidor.',
                        };
                    }
                    return { httpOk: r.ok, body: j };
                });
            })
            .then(function (res) {
                if (!res.httpOk || !res.body || typeof res.body.ok === 'undefined') {
                    if (btn) btn.disabled = false;
                    toast(
                        (res.body && res.body.mensagem) || 'Resposta inválida do servidor.',
                        false
                    );
                    return;
                }
                if (!res.body.ok) {
                    if (btn) btn.disabled = false;
                    toast(res.body.mensagem || 'Não foi possível registrar o voto.', false);
                    return;
                }
                votoBloqueadoLocal = true;
                var slot = document.getElementById('parl-voto-slot');
                if (slot) slot.innerHTML = '';
                ocultarAlertaVotacao();
                toast(res.body.mensagem || 'Voto registrado.', true);
                if (res.body.estado) {
                    aplicarEstado(res.body.estado);
                } else {
                    buscarEstado();
                }
            })
            .catch(function (err) {
                if (btn) btn.disabled = false;
                toast(
                    'Falha ao enviar voto. Verifique a rede ou recarregue a página (F5).',
                    false
                );
            });
    }

    function bindAlertaExistente() {
        var el = document.getElementById('parl-alerta-votacao');
        if (!el) return;
        var fechar = document.getElementById('parl-alerta-fechar');
        var ir = document.getElementById('parl-alerta-ir-voto');
        if (fechar) {
            fechar.addEventListener('click', function () {
                alertaDismissRodada = ultimoRodadaId;
                el.classList.remove('is-visible');
            });
        }
        if (ir) ir.addEventListener('click', irParaVoto);
    }

    root.addEventListener('click', function (ev) {
        var btn = ev.target.closest('button[type="submit"][name="voto"]');
        if (btn && btn.closest('.parl-voto-form')) {
            ultimoBotaoVoto = btn;
        }
    });

    root.addEventListener('submit', function (ev) {
        var form = ev.target;
        if (!form || !form.classList.contains('parl-voto-form')) return;
        ev.preventDefault();
        enviarVoto(form, ev.submitter || null);
    });

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) buscarEstado();
    });

    bindAlertaExistente();
    buscarEstado();
    reiniciarPolling();
    conectarStream();
})();
