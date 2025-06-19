document.addEventListener('DOMContentLoaded', function() {
    
    document.querySelectorAll('form').forEach(function(form) {
        form.addEventListener('submit', function() {
            var loader = document.getElementById('importador-loader');
            if (loader) loader.style.display = 'block';
        });
    });
});
document.addEventListener('DOMContentLoaded', function() {

    // Função para atualizar barra de progresso
    function setProgress(container, percent) {
        if (!container) return;
        var bar = container.querySelector('.progressbar-bar');
        if (bar) {
            bar.style.width = percent + '%';
            container.style.display = percent > 0 ? 'block' : 'none';
        }
    }

    // Importar Todos
    var btnImportarTodos = document.getElementById('importar-todos');
    var statusTodos = document.getElementById('importar-todos-status');
    var barTodos = document.getElementById('importar-todos-progressbar');

    if (btnImportarTodos) {
        btnImportarTodos.addEventListener('click', function() {
            var destino = document.getElementById('destino_idx');
            if (!destino || !destino.value) {
                alert('Selecione um destino.');
                return;
            }
            btnImportarTodos.disabled = true;
            statusTodos.innerHTML = 'Iniciando importação...';
            setProgress(barTodos, 0);
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
                        statusTodos.innerHTML = '<span style="color:red;">' + (res.data.mensagem || 'Erro desconhecido') + '</span>';
                        btnImportarTodos.disabled = false;
                        setProgress(barTodos, 0);
                        return;
                    }
                    statusTodos.innerHTML = 'Progresso: ' + res.data.progresso + '<br>Total enviados: ' + (offset + res.data.enviados);
                    if (res.data.erros && res.data.erros.length) {
                        statusTodos.innerHTML += '<br><span style="color:red;">Erros:</span><ul>' + res.data.erros.map(e => '<li>' + e + '</li>').join('') + '</ul>';
                    }
                    var partes = res.data.progresso.split('/');
                    var atual = parseInt(partes[0]);
                    var total = parseInt(partes[1]);
                    var percent = total ? Math.round((atual / total) * 100) : 0;
                    setProgress(barTodos, percent);

                    if (!res.data.finalizado) {
                        importarLote(offset + 20);
                    } else {
                        statusTodos.innerHTML += '<br><strong>Importação finalizada!</strong>';
                        btnImportarTodos.disabled = false;
                        setTimeout(function() { setProgress(barTodos, 0); }, 2000);
                    }
                })
                .catch(() => {
                    statusTodos.innerHTML = '<span style="color:red;">Erro de comunicação com o servidor.</span>';
                    btnImportarTodos.disabled = false;
                    setProgress(barTodos, 0);
                });
            }
        });
    }

    // Importar Apenas Novos
    var btnImportarNovos = document.getElementById('importar-novos');
    var statusNovos = document.getElementById('importar-novos-status');
    var barNovos = document.getElementById('importar-novos-progressbar');

    if (btnImportarNovos) {
        btnImportarNovos.addEventListener('click', function() {
            var destino = document.getElementById('destino_idx');
            if (!destino || !destino.value) {
                alert('Selecione um destino.');
                return;
            }
            btnImportarNovos.disabled = true;
            statusNovos.innerHTML = 'Iniciando importação apenas dos novos...';
            setProgress(barNovos, 0);
            importarLoteNovos(0);

            function importarLoteNovos(offset) {
                var data = new FormData();
                data.append('action', 'importar_produtos_em_lote');
                data.append('destino_idx', destino.value);
                data.append('offset', offset);
                data.append('apenas_novos', '1');

                fetch(ajaxurl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: data
                })
                .then(resp => resp.json())
                .then(res => {
                    if (!res.success) {
                        statusNovos.innerHTML = '<span style="color:red;">' + (res.data.mensagem || 'Erro desconhecido') + '</span>';
                        btnImportarNovos.disabled = false;
                        setProgress(barNovos, 0);
                        return;
                    }
                    statusNovos.innerHTML = 'Progresso: ' + res.data.progresso + '<br>Enviados neste lote: ' + res.data.enviados;
                    if (res.data.erros && res.data.erros.length) {
                        statusNovos.innerHTML += '<br><span style="color:red;">Erros:</span><ul>' + res.data.erros.map(e => '<li>' + e + '</li>').join('') + '</ul>';
                    }
                    var partes = res.data.progresso.split('/');
                    var atual = parseInt(partes[0]);
                    var total = parseInt(partes[1]);
                    var percent = total ? Math.round((atual / total) * 100) : 0;
                    setProgress(barNovos, percent);

                    if (!res.data.finalizado) {
                        importarLoteNovos(offset + 20);
                    } else {
                        statusNovos.innerHTML += '<br><strong>Importação finalizada!</strong>';
                        btnImportarNovos.disabled = false;
                        setTimeout(function() { setProgress(barNovos, 0); }, 2000);
                    }
                })
                .catch(() => {
                    statusNovos.innerHTML = '<span style="color:red;">Erro de comunicação com o servidor.</span>';
                    btnImportarNovos.disabled = false;
                    setProgress(barNovos, 0);
                });
            }
        });
    }

    // Importação individual
    document.querySelectorAll('button[name="importar_produto_unico"]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();

            var produtoId = btn.value;
            var destino = document.getElementById('destino_idx');
            if (!destino || !destino.value) {
                alert('Selecione um destino.');
                return;
            }

            btn.disabled = true;

            var barDiv = document.getElementById('importar-produto-progressbar-' + produtoId);
            setProgress(barDiv, 50); // Meio enquanto processa

            var data = new FormData();
            data.append('action', 'importar_produto_unico_ajax');
            data.append('produto_id', produtoId);
            data.append('destino_idx', destino.value);
            data.append('_ajax_nonce', importar_produto_unico_ajax.nonce);

            fetch(ajaxurl, {
                method: 'POST',
                credentials: 'same-origin',
                body: data
            })
            .then(resp => resp.json())
            .then(res => {
                if (res.success) {
                    setProgress(barDiv, 100);
                } else {
                    setProgress(barDiv, 0);
                }
                btn.disabled = false;
                setTimeout(function() { setProgress(barDiv, 0); }, 2000);
            })
            .catch(() => {
                setProgress(barDiv, 0);
                btn.disabled = false;
            });
        });
    });
});