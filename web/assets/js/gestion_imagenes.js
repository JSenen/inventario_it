function initImageManager(config) {
    const previewEditar = document.getElementById(config.previewEditar);
    const previewNueva = document.getElementById(config.previewNueva);
    const inputExistente = document.getElementById(config.inputExistente);
    const inputNuevo = document.getElementById(config.inputNuevo);
    const btnLimpiar = document.getElementById(config.btnLimpiar);
    const btnSelectores = document.querySelectorAll(config.btnSelector);

    function setPreview(src, isNew = false) {
        if (isNew) {
            if (previewNueva) {
                previewNueva.src = src || '';
                previewNueva.style.display = src ? 'block' : 'none';
            }
            if (previewEditar) {
                previewEditar.style.display = 'none';
            }
        } else {
            if (previewEditar) {
                previewEditar.src = src || '';
                previewEditar.style.display = src ? 'block' : 'none';
            }
            if (previewNueva) {
                previewNueva.style.display = 'none';
            }
        }
    }

    if (btnSelectores.length) {
        btnSelectores.forEach(btn => {
            btn.addEventListener('click', () => {
                btnSelectores.forEach(b => b.classList.remove('active-selection'));
                btn.classList.add('active-selection');
                const file = btn.dataset.file || '';
                if (inputExistente) inputExistente.value = file;
                if (inputNuevo) inputNuevo.value = '';
                if (file) {
                    setPreview(config.uploadPath + file, false);
                }
            });
        });
    }

    if (inputNuevo) {
        inputNuevo.addEventListener('change', (e) => {
            if (e.target.files && e.target.files[0]) {
                btnSelectores.forEach(b => b.classList.remove('active-selection'));
                if (inputExistente) inputExistente.value = '';
                const reader = new FileReader();
                reader.onload = function (ev) {
                    setPreview(ev.target.result, true);
                };
                reader.readAsDataURL(e.target.files[0]);
            }
        });
    }

    if (btnLimpiar) {
        btnLimpiar.addEventListener('click', () => {
            btnSelectores.forEach(b => b.classList.remove('active-selection'));
            if (inputExistente) inputExistente.value = '';
            if (inputNuevo) inputNuevo.value = '';
            setPreview('', false);
            setPreview('', true);
        });
    }

    if (inputExistente && inputExistente.value) {
        setPreview(config.uploadPath + inputExistente.value, false);
    }
}