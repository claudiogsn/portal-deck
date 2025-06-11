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
        $('#contadorSelecionados').text('Selecionados: ' + selectedDocs.length);
    }

    function renderBalancos(requisicoes) {
        const tbody = $('#balancosTable tbody');
        tbody.empty();
        selectedDocs = [];
        atualizarContadorSelecionados();

        requisicoes.forEach(requisicao => {
            const row = $(`
                <tr>
                    <td><input type="checkbox" id="balanco-${requisicao.doc}" class="chk-col-blue balanco-checkbox" data-doc="${requisicao.doc}"><label for="balanco-${requisicao.doc}"> </label></td>
                    <td>${requisicao.doc}</td>
                    <td>${requisicao.status_descricao}</td>
                    <td>${new Date(requisicao.created_at).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })}</td>
                    <td>
                      <button class="btn btn-info btnDetalhes" data-doc="${requisicao.doc}">Ver Detalhes</button>
                      <button class="btn btn-success btnEntrega" data-doc="${requisicao.doc}">Realizar entrega</button>
                    </td>
                </tr>`
            );

            row.find('.btnEntrega').on('click', function () {
                abrirModalEntrega(requisicao.doc);
            });
            tbody.append(row);

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
                currentItems = response.data.itens;
                renderDetalhesComprasModal(requisicao, currentItems);
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

    function renderDetalhesComprasModal(requisicao, itens) {
        const tbody = $('#detalhesBalanco');
        tbody.empty();

        $('#balancoModal .modal-title').text(`Requisição: ${requisicao.doc} | Solicitante: ${requisicao.solicitante_nome} | Status: ${requisicao.status_descricao}`);

        itens.forEach(item => {
            tbody.append(`
                <tr>
                    <td>${item.produto}</td>
                    <td>${item.quantidade}</td>
                    <td>${item.preco != null ? `R$ ${item.preco.toFixed(2)}` : '-'}</td>
                </tr>
            `);
        });

        $('#balancoModal').data('doc', requisicao.doc);
        $('#balancoModal').data('tipoMov', requisicao.status_descricao);
        $('#balancoModal').data('unitName', requisicao.system_unit_id);
        $('#balancoModal').data('usuario_nome', requisicao.solicitante_nome);
        $('#balancoModal').data('created_at', requisicao.created_at);
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
                produto: item.produto,
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
    
        const listaDocs = selectedDocs.map(doc => `<li>${doc}</li>`).join('');
        const result = await Swal.fire({
            title: `${acaoNome} Selecionados`,
            html: `<p>Tem certeza que deseja <b>${acaoNome.toLowerCase()}</b> os seguintes documentos?</p><ul style='text-align:left'>${listaDocs}</ul>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: `Sim, ${acaoNome.toLowerCase()}`,
            cancelButtonText: 'Cancelar'
        });
    
        if (!result.isConfirmed) return;
    
        Swal.fire({
            title: 'Processando...',
            text: 'Alterando status das requisições...',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    
        let erros = [];
        for (const doc of selectedDocs) {
            try {
                const response = await axios.post(baseUrl, {
                    method: 'changeStatusRequisicao',
                    token: token,
                    data: {
                        doc: doc,
                        system_unit_id: unitId,
                        status_id: statusId,
                        username: username
                    }
                });
                if (!(response.data && response.data.success)) {
                    erros.push(doc);
                }
            } catch (e) {
                erros.push(doc);
            }
        }
        Swal.close();
        if (erros.length === 0) {
            Swal.fire('Sucesso', `Todos os documentos foram ${acaoNome === 'Aprovar' ? 'aprovados' : 'reprovados'} com sucesso!`, 'success');
            loadBalancos();
        } else {
            Swal.fire('Erro', `Os seguintes documentos falharam: <ul style='text-align:left'>${erros.map(d=>`<li>${d}</li>`).join('')}</ul>`, 'error');
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
                      <td><input type="text" inputmode="numeric" id="qtd-comprada" class="form-control qtd-comprada" data-codigo="${item.codigo}" placeholder="0"></td>
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
    

    loadBalancos();
});


