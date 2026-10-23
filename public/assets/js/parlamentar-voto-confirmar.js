/**
 * Confirmação do voto do parlamentar.
 * O primeiro toque em Sim / Não / Abstenção só mostra "Confirmar voto";
 * o envio acontece depois de Confirmar. Funciona em qualquer .parl-voto-form,
 * com ou sem o envio por AJAX do parlamentar-acompanhar.js.
 */
(function () {
    'use strict';

    var CLASSES_VOTO = { SIM: 'voto-sim', NAO: 'voto-nao', ABSTENCAO: 'voto-abst' };

    function painelAberto(form) {
        return form.querySelector('.parl-voto-confirmacao');
    }

    function fechar(form) {
        var painel = painelAberto(form);
        if (painel) painel.remove();
        var botoes = form.querySelector('.parl-voto-botoes');
        if (botoes) botoes.style.display = '';
    }

    function abrir(form, botaoVoto) {
        fechar(form);
        var botoes = form.querySelector('.parl-voto-botoes');
        var rotulo = (botaoVoto.textContent || botaoVoto.value).trim();

        var painel = document.createElement('div');
        painel.className = 'parl-voto-confirmacao';
        painel.setAttribute('role', 'group');
        painel.setAttribute('aria-label', 'Confirmar voto');

        var texto = document.createElement('p');
        texto.className = 'parl-voto-confirmacao-texto';
        texto.appendChild(document.createTextNode('Confirmar voto:'));
        var chip = document.createElement('span');
        chip.className = 'voto-chip ' + (CLASSES_VOTO[botaoVoto.value] || '');
        chip.textContent = rotulo;
        texto.appendChild(chip);

        var aviso = document.createElement('p');
        aviso.className = 'panel-meta';
        aviso.textContent = 'Depois de confirmado, o voto não pode ser alterado.';

        var acoes = document.createElement('div');
        acoes.className = 'parl-voto-botoes';

        var confirmar = document.createElement('button');
        confirmar.type = 'button';
        confirmar.className = botaoVoto.className;
        confirmar.setAttribute('data-acao', 'confirmar');
        confirmar.textContent = 'Confirmar voto ' + rotulo;

        var voltar = document.createElement('button');
        voltar.type = 'button';
        voltar.className = 'btn btn-soft parl-voto-btn';
        voltar.setAttribute('data-acao', 'voltar');
        voltar.textContent = 'Voltar';

        confirmar.addEventListener('click', function () {
            fechar(form);
            form.setAttribute('data-voto-confirmado', '1');
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit(botaoVoto);
            } else {
                var campo = document.createElement('input');
                campo.type = 'hidden';
                campo.name = botaoVoto.name;
                campo.value = botaoVoto.value;
                form.appendChild(campo);
                form.submit();
            }
        });
        voltar.addEventListener('click', function () {
            fechar(form);
            botaoVoto.focus();
        });

        acoes.appendChild(confirmar);
        acoes.appendChild(voltar);
        painel.appendChild(texto);
        painel.appendChild(aviso);
        painel.appendChild(acoes);

        if (botoes) {
            botoes.style.display = 'none';
            botoes.insertAdjacentElement('afterend', painel);
        } else {
            form.appendChild(painel);
        }
        confirmar.focus();
    }

    // Fase de captura: roda antes do envio por AJAX do parlamentar-acompanhar.js.
    document.addEventListener(
        'submit',
        function (ev) {
            var form = ev.target;
            if (!form || !form.classList || !form.classList.contains('parl-voto-form')) return;

            if (form.getAttribute('data-voto-confirmado') === '1') {
                form.removeAttribute('data-voto-confirmado');
                return;
            }

            var botao = ev.submitter;
            if (!botao || botao.name !== 'voto') return;

            ev.preventDefault();
            ev.stopImmediatePropagation();
            abrir(form, botao);
        },
        true
    );

    document.addEventListener('keydown', function (ev) {
        if (ev.key !== 'Escape') return;
        document.querySelectorAll('.parl-voto-form').forEach(function (form) {
            if (painelAberto(form)) fechar(form);
        });
    });
})();
