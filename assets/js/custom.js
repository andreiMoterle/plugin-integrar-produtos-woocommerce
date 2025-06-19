document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('form').forEach(function(form) {
        form.addEventListener('submit', function() {
            var loader = document.getElementById('importador-loader');
            if (loader) loader.style.display = 'block';
        });
    });
});

document.addEventListener('DOMContentLoaded', function() {
    var btnImportarTodos = document.getElementById('importar-todos');
    if (btnImportarTodos) {
        btnImportarTodos.addEventListener('click', function() {
            var destino = document.getElementById('destino_idx');
            if (!destino || !destino.value) {
                alert('Selecione um destino.');
                return;
            }
            btnImportarTodos.disabled = true;
            var statusDiv = document.getElementById('importar-todos-status');
            statusDiv.innerHTML = 'Iniciando importação...';
            importarLote(0);

            function importarLote(offset) {
                var data = new FormData();
                data.append('action', 'importar_produtos_em_lote');
                data.append('destino_idx', destino.value);
                data.append('offset', offset);

                fetch(ajaxurl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: data
                })
                .then(resp => resp.json())
                .then(res => {
                    if (!res.success) {
                        statusDiv.innerHTML = '<span style="color:red;">' + (res.data.mensagem || 'Erro desconhecido') + '</span>';
                        btnImportarTodos.disabled = false;
                        return;
                    }
                    statusDiv.innerHTML = 'Progresso: ' + res.data.progresso + '<br>Enviados neste lote: ' + res.data.enviados;
                    if (res.data.erros && res.data.erros.length) {
                        statusDiv.innerHTML += '<br><span style="color:red;">Erros:</span><ul>' + res.data.erros.map(e => '<li>' + e + '</li>').join('') + '</ul>';
                    }
                    if (!res.data.finalizado) {
                        importarLote(offset + 20);
                    } else {
                        statusDiv.innerHTML += '<br><strong>Importação finalizada!</strong>';
                        btnImportarTodos.disabled = false;
                    }
                })
                .catch(() => {
                    statusDiv.innerHTML = '<span style="color:red;">Erro de comunicação com o servidor.</span>';
                    btnImportarTodos.disabled = false;
                });
            }
        });
    }
});