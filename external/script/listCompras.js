$(document).ready(function () {
    const baseUrl = window.location.hostname !== 'localhost' ?
        'https://portal.vemprodeck.com.br/api/v1/index.php' :
        'http://localhost/portal-deck/api/v1/index.php';

    const baseUrlredirect = window.location.hostname !== 'localhost' ?
        'https://portal.vemprodeck.com.br/external' :
        'http://localhost/portal-deck/external';

    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('token');
    const unitId = urlParams.get('unit_id');
    const username = urlParams.get('username');

    let selectedDocs = [];
    let currentItems = [];

    function showLoader() {
        $('.page-loader-wrapper').fadeIn();
    }

    function hideLoader() {
        $('.page-loader-wrapper').fadeOut();
    }

    async function loadBalancos(dataInicial = null, dataFinal = null, doc = null) {
        if (dataInicial) dataInicial += " 00:00:00";
        if (dataFinal) dataFinal += " 23:59:59";

        showLoader();
        try {
            const response = await axios.post(baseUrl, {
                method: 'listPurchaseRequests',
                token: token,
                data: {
                    system_unit_id: unitId,
                    data_inicial: dataInicial,
                    data_final: dataFinal,
                    doc: doc
                }
            });

            if (response.data && response.data.success) {
                const requisicoesOrdenadas = response.data.requisicoes.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
                renderBalancos(requisicoesOrdenadas);
            } else {
                Swal.fire("Erro", "Erro ao carregar requisições", "error");
            }
        } catch (error) {
            console.error('Erro ao carregar requisições:', error);
        } finally {
            hideLoader();
        }
    }

    function atualizarContadorSelecionados() {
        $('#contadorSelecionados').text(selectedDocs.length);
    }

    function gerarItemConfirmarRecebimento(requisicao) {
        if (requisicao.status === 4) {
            return `<li><a href="#" class="btnConfirmarRecebimento" data-doc="${requisicao.doc}">Confirmar Recebimento</a></li>`;
        } else {
            return `<li>
                <a class="dropdown-item confirmar-recebimento disabled-link"
                   href="#"
                   tabindex="-1"
                   aria-disabled="true"
                   style="pointer-events: none; color: #aaa; cursor: not-allowed;">
                   Confirmar Recebimento
                </a>
            </li>`;
        }
    }
    

    function renderBalancos(requisicoes) {
        const tbody = $('#balancosTable tbody');
        tbody.empty();
        selectedDocs = [];
        atualizarContadorSelecionados();
    
        requisicoes.forEach(requisicao => {
            console.log(requisicao);
            const confirmarRecebimentoItem = gerarItemConfirmarRecebimento(requisicao); 

            const entregarItem = [2, 4].includes(requisicao.status)
                ? `<li><a href="#" class="btnEntrega" data-doc="${requisicao.doc}">Realizar entrega</a></li>`
                : `<li>
                    <a class="dropdown-item disabled-link"
                    href="#"
                    tabindex="-1"
                    aria-disabled="true"
                    style="pointer-events: none; color: #aaa; cursor: not-allowed;">
                    Realizar entrega
                    </a>
                </li>`;
    
            const row = $(`
                <tr>
                    <td><input type="checkbox" id="balanco-${requisicao.doc}" class="chk-col-blue balanco-checkbox" data-doc="${requisicao.doc}"><label for="balanco-${requisicao.doc}"> </label></td>
                    <td>${requisicao.doc}</td>
                    <td>${requisicao.status_descricao}</td>
                    <td>${new Date(requisicao.created_at).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })}</td>
                    <td>
                        <div class="btn-group">
                            <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                Ações <span class="caret"></span>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a href="#" class="btnDetalhes" data-doc="${requisicao.doc}">Ver Detalhes</a></li>
                                ${entregarItem}
                                ${confirmarRecebimentoItem}
                            </ul>
                        </div>
                    </td>
                </tr>
            `);
    
            row.find('.btnEntrega').on('click', function () {
                abrirModalEntrega(requisicao.doc);
            });
    
            row.find('.balanco-checkbox').on('change', function () {
                const doc = $(this).data('doc');
                if ($(this).prop('checked')) {
                    row.addClass('selected-row');
                    if (!selectedDocs.includes(doc)) selectedDocs.push(doc);
                } else {
                    row.removeClass('selected-row');
                    selectedDocs = selectedDocs.filter(item => item !== doc);
                }
                atualizarContadorSelecionados();
                console.log(selectedDocs);
            });
    
            tbody.append(row);
        });
    
        $('#selectAll').off('click').on('click', function () {
            const isChecked = $(this).prop('checked');
            $('.balanco-checkbox').prop('checked', isChecked).trigger('change');
        });
    
        $('.btnDetalhes').off('click').on('click', function () {
            const doc = $(this).data('doc');
            loadDetalhesCompras(doc);
        });
    }
    

    async function loadDetalhesCompras(doc) {
        showLoader();
        try {
            const response = await axios.post(baseUrl, {
                method: 'getPurchaseRequestByDoc',
                token: token,
                data: {
                    system_unit_id: unitId,
                    doc: doc
                }
            });

            if (response.data && response.data.success) {
                const requisicao = response.data.requisicao;
                const logs = response.data.logs;
                currentItems = response.data.itens;
                renderDetalhesComprasModal(requisicao, currentItems,logs);
                $('#balancoModal').modal('show');
            } else {
                Swal.fire("Erro", "Erro ao carregar detalhes da requisição", "error");
            }
        } catch (error) {
            console.error('Erro ao carregar detalhes da requisição:', error);
        } finally {
            hideLoader();
        }
    }

    function renderDetalhesComprasModal(requisicao, itens, logs) {
        const tbody = $('#detalhesBalanco');
        tbody.empty();

        $('#balancoModal .modal-title').html(`
            <ul style="list-style:none; padding-left:0; margin-bottom:0;">
                <li><strong>Requisição:</strong> ${requisicao.doc}</li>
                <li><strong>Solicitante:</strong> ${requisicao.solicitante_nome}</li>
                <li><strong>Status:</strong> ${requisicao.status_descricao}</li>
            </ul>
        `);

        itens.forEach(item => {
            tbody.append(`
                <tr>
                    <td>${item.produto}</td>
                    <td>${item.observacao}</td>
                    <td>${item.quantidade}</td>
                    <td>${item.categoria_nome}</td>
                    <td>${item.quantidade_comprada != null ? item.quantidade_comprada : '-' }</td>
                    <td>${item.preco != null ? `R$ ${item.preco.toFixed(2)}` : '-'}</td>
                </tr>
            `);
        });

        $('#balancoModal').data('doc', requisicao.doc);
        $('#balancoModal').data('tipoMov', requisicao.status_descricao);
        $('#balancoModal').data('unitName', requisicao.system_unit_id);
        $('#balancoModal').data('usuario_nome', requisicao.solicitante_nome);
        $('#balancoModal').data('created_at', requisicao.created_at);


        // Renderiza os logs no rodapé
        if (logs && logs.length > 0) {
            const logsOrdenados = logs.sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
            const logMensagens = logsOrdenados.map(log =>
                `${log.observacao} por ${log.usuario_nome} em ${new Date(log.created_at).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })}`
            );

            const logHtml = logMensagens.map(msg => `<li>${msg}</li>`).join('');

            // Remove histórico anterior, se houver
            $('#balancoModal .modal-footer .log-historico').remove();

            // Adiciona histórico colapsável antes dos botões
            $('#balancoModal .modal-footer').prepend(`
                <div class="log-historico" style="width: 100%; margin-bottom: 10px;">
                    <div class="collapse mt-2" id="historicoCollapse">
                        <ul style="list-style: none; padding-left: 0; margin-bottom: 0;">
                            ${logMensagens.map(msg => `
                                <li><code>${msg}</code></li>
                            `).join('')}
                        </ul>
                    </div>
                </div>
                <br>
            `);            

        }

    }

    $('#acaoExportar').click(async function () {
        // Mesma lógica do antigo btnExportarSelecionados
        if (selectedDocs.length === 0) {
            Swal.fire("Atenção", "Nenhuma requisição foi selecionada.", "warning");
            return;
        }

        const result = await Swal.fire({
            title: "Exportar",
            text: `Deseja exportar as ${selectedDocs.length} requisições selecionadas para Excel?`,
            icon: "info",
            showCancelButton: true,
            confirmButtonText: "Sim, exportar",
            cancelButtonText: "Cancelar"
        });

        if (result.isConfirmed) {
            Swal.fire({
                title: 'Processando...',
                text: 'Carregando e agrupando dados...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            let agrupado = {};

            for (const doc of selectedDocs) {
                try {
                    const response = await axios.post(baseUrl, {
                        method: 'getPurchaseRequestByDoc',
                        token: token,
                        data: {
                            system_unit_id: unitId,
                            doc: doc
                        }
                    });

                    if (response.data && response.data.success) {
                        response.data.itens.forEach(item => {
                            const key = item.produto;

                            if (!agrupado[key]) {
                                agrupado[key] = {
                                    Produto: item.produto,
                                    'Quantidade Total': 0,
                                    'Preço Estimado Médio': 0,
                                    'Documentos (quantidade)': []
                                };
                            }

                            agrupado[key]['Quantidade Total'] += item.quantidade;

                            if (item.preco != null) {
                                agrupado[key]['Preço Estimado Médio'] += item.preco * item.quantidade;
                            }

                            agrupado[key]['Documentos (quantidade)'].push(`${doc} (${item.quantidade})`);
                        });
                    }
                } catch (error) {
                    console.error(`Erro ao obter itens da requisição ${doc}:`, error);
                }
            }

            const resultList = Object.values(agrupado).map(item => {
                const totalQtd = item['Quantidade Total'];
                const precoTotal = item['Preço Estimado Médio'];
                return {
                    Produto: item.Produto,
                    'Quantidade Total': totalQtd,
                    'Preço Estimado Médio': precoTotal > 0 ? (precoTotal / totalQtd).toFixed(2) : '-',
                    'Documentos (quantidade)': item['Documentos (quantidade)'].join(', ')
                };
            });

            Swal.close();

            if (resultList.length > 0) {
                exportToExcel(resultList, 'requisicoes_agrupadas');
                Swal.fire("Sucesso", "Requisições exportadas com sucesso!", "success");
            } else {
                Swal.fire("Erro", "Nenhum item para exportar.", "error");
            }
        }
    });

    $('#btnExportarModal').click(function () {
        if (currentItems.length > 0) {
            const itensFiltrados = currentItems.map(item => ({
                seq: item.seq,
                codigo: item.codigo,
                observacao: item.observacao,
                produto: item.produto,
                categoria: item.categoria_nome,
                quantidade: item.quantidade,
                preco: item.preco != null ? item.preco.toFixed(2) : '-'
            }));

            console.log('Exportando itens do modal:', itensFiltrados);
            exportToExcel(itensFiltrados, $('#balancoModal').data('doc'));
            Swal.fire("Sucesso", "Requisição exportada com sucesso!", "success");
        } else {
            Swal.fire("Erro", "Nenhum item para exportar.", "error");
        }
    });

    function exportToExcel(items, fileName) {
        const ws = XLSX.utils.json_to_sheet(items);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, fileName);
        XLSX.writeFile(wb, `${fileName}.xlsx`);
    }

    function imprimirRelatorio(docNumber, dataItens, detalhes) {
        try {
            const requisicaoPayload = {
                unidade: detalhes.estabelecimento,
                solicitante: detalhes.usuario,
                data: detalhes.dataBalanco,
                doc: docNumber,
                itens: dataItens
            };

            localStorage.setItem('requisicaoPdfData', JSON.stringify(requisicaoPayload));
            window.open(`${baseUrlredirect}/reports/requisicao.html`, '_blank');
        } catch (error) {
            console.error('Erro ao preparar impressão:', error);
            alert('Erro ao preparar a impressão. Verifique o console para mais detalhes.');
        }
    }

    $('#btnImprimir').click(function () {
        const docNumber = $('#balancoModal').data('doc');
        const dataItens = currentItems;
        const detalhes = {
            estabelecimento: $('#balancoModal').data('unitName'),
            tipoMov: $('#balancoModal').data('tipoMov'),
            dataBalanco: $('#balancoModal').data('created_at'),
            usuario: $('#balancoModal').data('usuario_nome')
        };

        if (dataItens && dataItens.length > 0) {
            imprimirRelatorio(docNumber, dataItens, detalhes);
        } else {
            alert('Nenhum item para imprimir.');
        }
    });

    $('#btnBuscar').click(function () {
        const dataInicial = $('#dataInicial').val();
        const dataFinal = $('#dataFinal').val();
        const doc = $('#searchDoc').val();

        loadBalancos(dataInicial, dataFinal, doc);
    });

    $('#acaoAprovar').click(function () {
        handleStatusChangeSelecionados(2, 'Aprovar');
    });
    
    $('#acaoRejeitar').click(function () {
        handleStatusChangeSelecionados(3, 'Reprovar');
    });
    
    async function handleStatusChangeSelecionados(statusId, acaoNome) {
        if (selectedDocs.length === 0) {
            Swal.fire('Atenção', 'Nenhuma requisição foi selecionada.', 'warning');
            return;
        }
    
        // Regras de transição válidas por status atual
        const transicoesValidas = {
            1: [2, 3],     // PEDIDO REALIZADO → APROVADA ou REPROVADA
            2: [4],        // COMPRA APROVADA → ENTREGUE
            4: [5, 6],     // ENTREGUE → FINALIZADA ou FINALIZADA COM CORTE
            3: [],         // REPROVADA → não pode mudar
            5: [],         // FINALIZADA → não pode mudar
            6: []          // FINALIZADA COM CORTE → não pode mudar
        };
    
        let erros = [];
        for (const doc of selectedDocs) {
            try {
                const response = await axios.post(baseUrl, {
                    method: 'getPurchaseRequestByDoc',
                    token: token,
                    data: {
                        system_unit_id: unitId,
                        doc: doc
                    }
                });
    
                if (!(response.data && response.data.success)) {
                    erros.push(`${doc} (erro ao buscar status atual)`);
                    continue;
                }
    
                const statusAtual = response.data.requisicao.status;
    
                // Verifica se a transição é permitida
                const permitidos = transicoesValidas[statusAtual] || [];
                if (!permitidos.includes(statusId)) {
                    erros.push(`${doc} (transição ${statusAtual} → ${statusId} não permitida)`);
                    continue;
                }
    
                const resultado = await axios.post(baseUrl, {
                    method: 'changeStatusRequisicao',
                    token: token,
                    data: {
                        doc: doc,
                        system_unit_id: unitId,
                        status_id: statusId,
                        username: username
                    }
                });
    
                if (!(resultado.data && resultado.data.success)) {
                    erros.push(`${doc} (falha na atualização)`);
                }
    
            } catch (e) {
                erros.push(`${doc} (erro inesperado)`);
            }
        }
    
        if (erros.length === 0) {
            Swal.fire('Sucesso', `Todos os documentos foram ${acaoNome.toLowerCase()}s com sucesso!`, 'success');
            loadBalancos();
        } else {
            Swal.fire('Erro', `Ocorreram erros em alguns documentos:<ul style='text-align:left'>${erros.map(e => `<li>${e}</li>`).join('')}</ul>`, 'error');
        }
    }
    
    
    async function abrirModalEntrega(doc) {
        showLoader();
        try {
            const response = await axios.post(baseUrl, {
                method: 'getPurchaseRequestByDoc',
                token: token,
                data: {
                    system_unit_id: unitId,
                    doc: doc
                }
            });
            if (response.data && response.data.success) {
                const itens = response.data.itens;
                $('#modalEntrega').data('doc', doc);
                const grid = itens.map(item => `
                    <tr>
                        <td>${item.produto}</td>
                        <td>${item.quantidade}</td>
                        <td><input type="text" inputmode="numeric" id="qtd-comprada" class="form-control qtd-comprada" data-codigo="${item.codigo}" value="${item.quantidade.toString().replace('.', ',')}" /></td>
                    </tr>
                `).join('');
                $('#entregaItensGrid').html(grid);
                $('#modalEntrega').modal('show');
                $('.qtd-comprada').each(function () {
                    const unidade = 'KG';
                    if (['KG', 'LT', 'L'].includes(unidade)) {
                        $(this).on('input', function () {
                            let valor = $(this).val().replace(/\D/g, '');
                            valor = (valor / 1000).toFixed(3);
                            $(this).val(valor.replace('.', ','));
                        });
                    } else {
                        $(this).on('input', function () {
                            $(this).val($(this).val().replace(/\D/g, ''));
                        });
                    }
                });
            } else {
                Swal.fire('Erro', 'Erro ao carregar itens da requisição.', 'error');
            }
        } catch (e) {
            Swal.fire('Erro', 'Erro ao carregar itens da requisição.', 'error');
        } finally {
            hideLoader();
        }
    }

   
   
    $('<style>\n.disabled-link {\n  pointer-events: none;\n  color: #aaa !important;\n  background: none !important;\n  cursor: not-allowed !important;\n}\n</style>').appendTo('head');
    
    $('#btnEnviarEntrega').on('click', async function () {
        const doc = $('#modalEntrega').data('doc');
        const itens = [];
        $('#entregaItensGrid tr').each(function () {
            const codigo = $(this).find('.qtd-comprada').data('codigo');
            let quantidade = $(this).find('.qtd-comprada').val();
            quantidade = quantidade === '' ? 0 : parseFloat(quantidade);
            itens.push({ codigo, quantidade });
        });
        showLoader();
        try {
            const response = await axios.post(baseUrl, {
                method: 'realizarEntrega',
                token: token,
                data: {
                    doc: doc,
                    system_unit_id: unitId,
                    username: username,
                    itens: itens
                }
            });
            if (response.data && response.data.success) {
                Swal.fire('Sucesso', 'Entrega realizada com sucesso!', 'success');
                $('#modalEntrega').modal('hide');
                loadBalancos();
            } else {
                Swal.fire('Erro', 'Erro ao realizar entrega.', 'error');
            }
        } catch (e) {
            Swal.fire('Erro', 'Erro ao realizar entrega.', 'error');
        } finally {
            hideLoader();
        }
    });

    $('#balancosTable').on('click', '.btnConfirmarRecebimento', async function () {
        const doc = $(this).data('doc');
    
        try {
            const response = await axios.post(baseUrl, {
                method: 'getPurchaseRequestByDoc',
                token: token,
                data: {
                    system_unit_id: unitId,
                    doc: doc
                }
            });
    
            if (response.data.success && response.data.requisicao) {
                const requisicao = response.data.requisicao;
                const itens = response.data.itens || [];
                const logs = response.data.logs || [];
                const usuarioId = requisicao.usuario_id;
    
                const logsStatus4 = logs.filter(l => l.status === 4);
                const userLogStatus4 = logsStatus4.find(l => l.usuario_id == username);
    
                if (userLogStatus4) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Atenção',
                        text: 'O usuário que despachou não pode confirmar o recebimento.'
                    });
                    return;
                }
    
                const todosIguais = itens.every(item => item.quantidade === item.quantidade_comprada);
    
                // 🟢 Aqui está o truque: força `selectedDocs` com apenas o doc atual
                selectedDocs = [doc];
    
                if (todosIguais) {
                    await handleStatusChangeSelecionados(5, 'COMPRA FINALIZADA');
                } else {
                    await handleStatusChangeSelecionados(6, 'COMPRA FINALIZADA COM CORTE');
                }
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: 'Não foi possível obter os dados da requisição.'
                });
            }
        } catch (err) {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: 'Falha ao buscar dados da requisição.'
            });
        }
    });
    
    

    loadBalancos();
});


