(function () {
    'use strict';

    if (window.appDialogs) {
        return;
    }

    var defaultTitle = 'Confirmar accion';
    var defaultAlertTitle = 'Aviso';
    var queue = Promise.resolve();

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function ensureTemplate() {
        if (document.getElementById('appPopupModal')) {
            return;
        }

        if (!document.getElementById('appPopupStyles')) {
            var style = document.createElement('style');
            style.id = 'appPopupStyles';
            style.textContent = '' +
                '#appPopupModal .modal-content{border:0;box-shadow:0 1rem 3rem rgba(0,0,0,.2);}' +
                '#appPopupModal .modal-header{background:#212529;color:#fff;}' +
                '#appPopupModal .modal-title{font-weight:600;}' +
                '#appPopupModal .modal-body pre{white-space:pre-wrap;word-break:break-word;margin:0;font-family:inherit;font-size:.96rem;}' +
                '#appPopupModal .modal-footer{gap:.5rem;}';
            document.head.appendChild(style);
        }

        var wrapper = document.createElement('div');
        wrapper.innerHTML = '' +
            '<div class="modal fade" id="appPopupModal" tabindex="-1" aria-hidden="true">' +
            '  <div class="modal-dialog modal-dialog-centered">' +
            '    <div class="modal-content">' +
            '      <div class="modal-header">' +
            '        <h5 class="modal-title" id="appPopupModalTitle"></h5>' +
            '        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>' +
            '      </div>' +
            '      <div class="modal-body"><pre id="appPopupModalMessage"></pre></div>' +
            '      <div class="modal-footer" id="appPopupModalFooter">' +
            '        <button type="button" class="btn btn-outline-secondary" data-role="cancel" data-bs-dismiss="modal">Cancelar</button>' +
            '        <button type="button" class="btn btn-success" data-role="ok">Confirmar</button>' +
            '      </div>' +
            '    </div>' +
            '  </div>' +
            '</div>';
        document.body.appendChild(wrapper.firstChild);
    }

    function enqueueDialog(openFn) {
        queue = queue.then(function () {
            return openFn();
        }).catch(function () {
            return undefined;
        });

        return queue;
    }

    function openModal(message, options) {
        options = options || {};

        if (!window.bootstrap || !window.bootstrap.Modal) {
            if (options.mode === 'alert') {
                window.alert(message);
                return Promise.resolve(true);
            }

            return Promise.resolve(window.confirm(message));
        }

        ensureTemplate();

        return enqueueDialog(function () {
            return new Promise(function (resolve) {
                var modalEl = document.getElementById('appPopupModal');
                var titleEl = document.getElementById('appPopupModalTitle');
                var messageEl = document.getElementById('appPopupModalMessage');
                var footerEl = document.getElementById('appPopupModalFooter');
                var cancelBtn = footerEl.querySelector('[data-role="cancel"]');
                var okBtn = footerEl.querySelector('[data-role="ok"]');

                titleEl.textContent = options.title || (options.mode === 'alert' ? defaultAlertTitle : defaultTitle);
                messageEl.innerHTML = escapeHtml(message || '');

                if (options.mode === 'alert') {
                    cancelBtn.classList.add('d-none');
                    okBtn.textContent = options.okText || 'Aceptar';
                    okBtn.className = 'btn btn-primary';
                } else {
                    cancelBtn.classList.remove('d-none');
                    cancelBtn.textContent = options.cancelText || 'Cancelar';
                    okBtn.textContent = options.okText || 'Confirmar';
                    okBtn.className = options.okClass || 'btn btn-success';
                }

                var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                var settled = false;

                function settle(value) {
                    if (settled) {
                        return;
                    }

                    settled = true;
                    modalEl.removeEventListener('hidden.bs.modal', onHidden);
                    okBtn.removeEventListener('click', onOk);
                    cancelBtn.removeEventListener('click', onCancel);
                    resolve(value);
                }

                function onOk() {
                    settle(true);
                    modal.hide();
                }

                function onCancel() {
                    settle(false);
                }

                function onHidden() {
                    if (!settled) {
                        settle(false);
                    }
                }

                okBtn.addEventListener('click', onOk);
                cancelBtn.addEventListener('click', onCancel);
                modalEl.addEventListener('hidden.bs.modal', onHidden);
                modal.show();
            });
        });
    }

    window.appDialogs = {
        confirm: function (message, options) {
            return openModal(message, Object.assign({ mode: 'confirm' }, options || {}));
        },
        alert: function (message, options) {
            return openModal(message, Object.assign({ mode: 'alert' }, options || {})).then(function () {
                return undefined;
            });
        }
    };

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form || !form.matches('form[data-confirm-message]')) {
            return;
        }

        if (form.dataset.confirmed === '1') {
            form.dataset.confirmed = '0';
            return;
        }

        event.preventDefault();

        window.appDialogs.confirm(form.dataset.confirmMessage, {
            title: form.dataset.confirmTitle || defaultTitle,
            okText: form.dataset.confirmOk || 'Confirmar',
            cancelText: form.dataset.confirmCancel || 'Cancelar'
        }).then(function (ok) {
            if (!ok) {
                return;
            }

            form.dataset.confirmed = '1';
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        });
    }, true);

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-confirm-message]');
        if (!trigger || trigger.matches('form')) {
            return;
        }

        if (trigger.dataset.confirmed === '1') {
            trigger.dataset.confirmed = '0';
            return;
        }

        event.preventDefault();

        window.appDialogs.confirm(trigger.dataset.confirmMessage, {
            title: trigger.dataset.confirmTitle || defaultTitle,
            okText: trigger.dataset.confirmOk || 'Confirmar',
            cancelText: trigger.dataset.confirmCancel || 'Cancelar'
        }).then(function (ok) {
            if (!ok) {
                return;
            }

            trigger.dataset.confirmed = '1';
            trigger.click();
        });
    }, true);
})();
