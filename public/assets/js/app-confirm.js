/**
 * Modal de confirmação (substitui window.confirm nativo).
 * Uso: <form data-confirm="Mensagem" data-confirm-title="Título" data-confirm-ok="Confirmar">
 */
(function () {
    'use strict';

    var modal = document.getElementById('app-confirm-modal');
    if (!modal) return;

    var titleEl = document.getElementById('app-confirm-title');
    var copyEl = document.getElementById('app-confirm-copy');
    var okBtn = document.getElementById('app-confirm-ok');
    var cancelBtn = document.getElementById('app-confirm-cancel');
    var iconEl = document.getElementById('app-confirm-icon');
    var activeForm = null;

    function closeModal() {
        modal.classList.add('is-hidden');
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        activeForm = null;
        document.body.classList.remove('app-confirm-open');
    }

    function openModal(form) {
        activeForm = form;
        var title = form.getAttribute('data-confirm-title') || 'Confirmar ação';
        var message = form.getAttribute('data-confirm') || 'Deseja continuar?';
        var okLabel = form.getAttribute('data-confirm-ok') || 'Confirmar';
        var variant = form.getAttribute('data-confirm-variant') || 'danger';

        if (titleEl) titleEl.textContent = title;
        if (copyEl) copyEl.textContent = message;
        if (okBtn) {
            okBtn.textContent = okLabel;
            okBtn.className = 'btn ' + (variant === 'primary' ? 'btn-primary' : 'btn-danger');
        }
        if (iconEl) {
            iconEl.className =
                'app-confirm-icon' + (variant === 'primary' ? '' : ' app-confirm-icon--danger');
            iconEl.textContent = variant === 'primary' ? '?' : '⚖';
        }

        modal.classList.remove('is-hidden');
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('app-confirm-open');
        if (okBtn) okBtn.focus();
    }

    document.addEventListener('submit', function (ev) {
        var form = ev.target;
        if (!form || form.tagName !== 'FORM') return;
        if (!form.hasAttribute('data-confirm')) return;
        if (form.getAttribute('data-confirm-sent') === '1') {
            form.removeAttribute('data-confirm-sent');
            return;
        }
        ev.preventDefault();
        openModal(form);
    });

    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

    modal.addEventListener('click', function (ev) {
        if (ev.target === modal) closeModal();
    });

    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape' && modal.classList.contains('is-open')) {
            closeModal();
        }
    });

    if (okBtn) {
        okBtn.addEventListener('click', function () {
            if (!activeForm) return;
            var form = activeForm;
            closeModal();
            form.setAttribute('data-confirm-sent', '1');
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        });
    }
})();
