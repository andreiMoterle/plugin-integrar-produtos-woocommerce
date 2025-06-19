document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('form').forEach(function(form) {
        form.addEventListener('submit', function() {
            var loader = document.getElementById('importador-loader');
            if (loader) loader.style.display = 'block';
        });
    });
});

document.addEventListener('DOMContentLoaded', function() {
    // Importar Todos
    var btnImportarTodos = document.getElementById('importar-todos');
    var statusTodos = document.getElementById('importar-todos-status');
    var barTodos = new ProgressBar.Line('#importar-todos-progressbar', {
        strokeWidth: 4,
        color: '#0073aa',
        trailColor: '#eee',
        trailWidth: 1,
        duration: 300,
        svgStyle: {width: '100%', height: '100%'}
    });

    if (btnImportarTodos) {
        btnImportarTodos.addEventListener('click', function() {
            var destino = document.getElementById('destino_idx');
            if (!destino || !destino.value) {
                alert('Selecione um destino.');
                return;
            }
            btnImportarTodos.disabled = true;
            statusTodos.innerHTML = 'Iniciando importação...';
            barTodos.set(0);
            barTodos.animate(0); // reset
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
                        barTodos.set(0);
                        return;
                    }
                    statusTodos.innerHTML = 'Progresso: ' + res.data.progresso + '<br>Total enviados: ' + (offset + res.data.enviados);
                    if (res.data.erros && res.data.erros.length) {
                        statusTodos.innerHTML += '<br><span style="color:red;">Erros:</span><ul>' + res.data.erros.map(e => '<li>' + e + '</li>').join('') + '</ul>';
                    }
                    var partes = res.data.progresso.split('/');
                    var atual = parseInt(partes[0]);
                    var total = parseInt(partes[1]);
                    var percent = total ? (atual / total) : 0;
                    barTodos.animate(percent);

                    if (!res.data.finalizado) {
                        importarLote(offset + 20);
                    } else {
                        statusTodos.innerHTML += '<br><strong>Importação finalizada!</strong>';
                        btnImportarTodos.disabled = false;
                        setTimeout(function() { barTodos.set(0); }, 2000);
                    }
                })
                .catch(() => {
                    statusTodos.innerHTML = '<span style="color:red;">Erro de comunicação com o servidor.</span>';
                    btnImportarTodos.disabled = false;
                    barTodos.set(0);
                });
            }
        });
    }

    // Importar Apenas Novos
    var btnImportarNovos = document.getElementById('importar-novos');
    var statusNovos = document.getElementById('importar-novos-status');
    var barNovos = new ProgressBar.Line('#importar-novos-progressbar', {
        strokeWidth: 4,
        color: '#0073aa',
        trailColor: '#eee',
        trailWidth: 1,
        duration: 300,
        svgStyle: {width: '100%', height: '100%'}
    });

    if (btnImportarNovos) {
        btnImportarNovos.addEventListener('click', function() {
            var destino = document.getElementById('destino_idx');
            if (!destino || !destino.value) {
                alert('Selecione um destino.');
                return;
            }
            btnImportarNovos.disabled = true;
            statusNovos.innerHTML = 'Iniciando importação apenas dos novos...';
            barNovos.set(0);
            barNovos.animate(0);
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
                        barNovos.set(0);
                        return;
                    }
                    statusNovos.innerHTML = 'Progresso: ' + res.data.progresso + '<br>Enviados neste lote: ' + res.data.enviados;
                    if (res.data.erros && res.data.erros.length) {
                        statusNovos.innerHTML += '<br><span style="color:red;">Erros:</span><ul>' + res.data.erros.map(e => '<li>' + e + '</li>').join('') + '</ul>';
                    }
                    var partes = res.data.progresso.split('/');
                    var atual = parseInt(partes[0]);
                    var total = parseInt(partes[1]);
                    var percent = total ? (atual / total) : 0;
                    barNovos.animate(percent);

                    if (!res.data.finalizado) {
                        importarLoteNovos(offset + 20);
                    } else {
                        statusNovos.innerHTML += '<br><strong>Importação finalizada!</strong>';
                        btnImportarNovos.disabled = false;
                        setTimeout(function() { barNovos.set(0); }, 2000);
                    }
                })
                .catch(() => {
                    statusNovos.innerHTML = '<span style="color:red;">Erro de comunicação com o servidor.</span>';
                    btnImportarNovos.disabled = false;
                    barNovos.set(0);
                });
            }
        });
    }
});

document.addEventListener('DOMContentLoaded', function() {
    // Para cada botão de importação individual
    document.querySelectorAll('button[name="importar_produto_unico"]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault(); // Impede o submit tradicional

            var produtoId = btn.value;
            var destino = document.getElementById('destino_idx');
            if (!destino || !destino.value) {
                alert('Selecione um destino.');
                return;
            }

            // Desabilita o botão durante o processo
            btn.disabled = true;

            // Status e barra de progresso individuais
            var statusDiv = btn.closest('tr').querySelector('.importar-produto-status');
            if (!statusDiv) {
                statusDiv = document.createElement('div');
                statusDiv.className = 'importar-produto-status';
                btn.closest('td').appendChild(statusDiv);
            }
            statusDiv.innerHTML = 'Enviando...';

            var barId = 'importar-produto-progressbar-' + produtoId;
            var barDiv = document.getElementById(barId);
            if (!barDiv) {
                barDiv = document.createElement('div');
                barDiv.id = barId;
                barDiv.style.width = '100px';
                barDiv.style.height = '10px';
                btn.closest('td').appendChild(barDiv);
            }
            // Limpa barra anterior
            barDiv.innerHTML = '';

            var bar = new ProgressBar.Line('#' + barId, {
                strokeWidth: 4,
                color: '#0073aa',
                trailColor: '#eee',
                trailWidth: 1,
                duration: 300,
                svgStyle: {width: '100%', height: '100%'}
            });
            bar.set(0);
            bar.animate(0.5); // Meio enquanto processa

            // Monta dados do formulário
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
                    statusDiv.innerHTML = '<span style="color:green;">' + res.data.mensagem + '</span>';
                    bar.animate(1.0);
                } else {
                    statusDiv.innerHTML = '<span style="color:red;">' + (res.data && res.data.mensagem ? res.data.mensagem : 'Erro ao importar produto.') + '</span>';
                    bar.set(0);
                }
                btn.disabled = false;
                setTimeout(function() { bar.set(0); }, 2000);
            })
            .catch(() => {
                statusDiv.innerHTML = '<span style="color:red;">Erro de comunicação com o servidor.</span>';
                bar.set(0);
                btn.disabled = false;
            });
        });
    });
});