(function($) {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        if (typeof $.fn.wpColorPicker === 'function') {
            $('.lmp-color-picker').wpColorPicker();
        }

        const importForm = document.getElementById('lmp-import-form');
        if (importForm) {
            importForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const feedbackDiv = document.getElementById('lmp-import-feedback');
                const submitButton = this.querySelector('input[type="submit"]');
                feedbackDiv.innerHTML = '';
                submitButton.value = 'Importando...';
                submitButton.disabled = true;

                const formData = new FormData(this);
                formData.append('action', 'lmp_ajax_import');
                formData.append('nonce', lmp_ajax.nonce);

                fetch(lmp_ajax.ajax_url, { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    feedbackDiv.className = data.success ? 'notice notice-success is-dismissible' : 'notice notice-error is-dismissible';
                    feedbackDiv.innerHTML = `<p>${data.data.message || 'Ocorreu um erro.'}</p>`;
                    if (data.success) {
                        importForm.reset();
                    }
                })
                .catch(error => {
                    feedbackDiv.className = 'notice notice-error is-dismissible';
                    feedbackDiv.innerHTML = `<p><strong>Erro de requisição:</strong> ${error}.</p>`;
                })
                .finally(() => {
                    submitButton.value = 'Importar Links';
                    submitButton.disabled = false;
                });
            });
        }
    });

})(jQuery);