/*
 * "Pedir a palavra" na tela do vereador.
 * O botão envia o pedido sem recarregar a página; a situação (pendente,
 * concedido, recusado) chega ao vivo pelo estado da sessão
 * (parlamentar-acompanhar.js chama ParlPedidoPalavra.atualizar).
 */
(function () {
    'use strict';

    var card = document.getElementById('parl-palavra');
    if (!card) return;

    var form = card.querySelector('form');
    var campoAcao = card.querySelector('[data-palavra-acao]');
    var titulo = card.querySelector('[data-palavra-titulo]');
    var sub = card.querySelector('[data-palavra-sub]');
    var botao = card.querySelector('[data-palavra-botao]');
    var textos = {};
    try {
        textos = JSON.parse(card.getAttribute('data-textos') || '{}');
    } catch (e) {
        textos = {};
    }
    var situacaoAtual = '';
    var enviando = false;

    function render(situacao) {
        var t = textos[situacao] || textos.NENHUM;
        if (!t) return;
        situacaoAtual = situacao;
        card.className = card.className.replace(/\bparl-palavra--\w+/g, '').trim() +
            ' parl-palavra--' + situacao.toLowerCase();
        titulo.textContent = t.titulo;
        sub.textContent = t.sub;
        campoAcao.value = t.acao;
        botao.textContent = t.botao;
        botao.hidden = t.botao === '';
        botao.classList.toggle('btn-soft', situacao === 'PENDENTE');
        botao.classList.toggle('btn-primary', situacao !== 'PENDENTE');
    }

    function atualizar(pedido) {
        if (!pedido) return;
        card.hidden = !pedido.pode_pedir;
        if (enviando) return;
        var situacao = pedido.situacao || 'NENHUM';
        if (situacao !== situacaoAtual) render(situacao);
    }

    form.addEventListener('submit', function (ev) {
        if (!window.fetch || !window.FormData) return; // sem JS moderno: envio normal
        ev.preventDefault();
        if (enviando) return;
        enviando = true;
        botao.disabled = true;

        var dados = new FormData(form);
        dados.append('ajax', '1');
        fetch(form.action, {
            method: 'POST',
            body: dados,
            credentials: 'same-origin',
            headers: { Accept: 'application/json' }
        })
            .then(function (r) {
                return r.json();
            })
            .then(function (resp) {
                if (resp && resp.ok) {
                    render(resp.situacao || 'NENHUM');
                } else {
                    sub.textContent = (resp && resp.erro) || 'Não foi possível enviar. Tente de novo.';
                }
            })
            .catch(function () {
                sub.textContent = 'Sem conexão. Tente de novo.';
            })
            .then(function () {
                enviando = false;
                botao.disabled = false;
            });
    });

    var inicial = (card.className.match(/parl-palavra--(\w+)/) || [])[1];
    situacaoAtual = inicial ? inicial.toUpperCase() : 'NENHUM';

    window.ParlPedidoPalavra = { atualizar: atualizar };
})();
