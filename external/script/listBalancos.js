$(document).ready(function () {
    const baseUrl = window.location.hostname !== 'localhost' ?
        'https://portal.vemprodeck.com.br/api/v1/index.php' :
        'http://localhost/portal-deck/api/v1/index.php';

    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('token');
    const unitId = urlParams.get('unit_id');

    let selectedDocs = [];
    let currentItems = [];

    function showLoader() {
        $('.page-loader-wrapper').fadeIn();
    }

    function hideLoader() {
        $('.page-loader-wrapper').fadeOut();
    }

    // Carregar balanços
    async function loadBalancos(dataInicial = null, dataFinal = null, doc = null) {
        if (dataInicial) {
            dataInicial += " 00:00:00";
        }

        if (dataFinal) {
            dataFinal += " 23:59:59";
        }

        showLoader();
        try {
            const response = await axios.post(baseUrl, {
                method: 'listBalance',
                token: token,
                data: {
                    system_unit_id: unitId,
                    data_inicial: dataInicial,
                    data_final: dataFinal,
                    doc: doc
                }
            });

            if (response.data && response.data.success) {
                renderBalancos(response.data.balances);
            } else {
                swal("Erro", "Erro ao carregar balanços", "error");
            }
        } catch (error) {
            console.error('Erro ao carregar balanços:', error);
        } finally {
            hideLoader();
        }
    }

    function renderBalancos(balances) {
        const tbody = $('#balancosTable tbody');
        tbody.empty();
        selectedDocs = [];  // Reinicializar a lista de documentos selecionados

        balances.forEach(balanco => {
            const dataHora = new Date(balanco.created_at);
            dataHora.setHours(dataHora.getHours() - 5); // Ajuste de fuso horário UTC-5

            const row = $(`
            <tr>
                <td><input type="checkbox" id="balanco-${balanco.doc}" class="chk-col-blue balanco-checkbox" data-doc="${balanco.doc}"><label for="balanco-${balanco.doc}"> </label></td>
                <td>${balanco.doc}</td>
                <td>${balanco.tipo_mov}</td>
                <td>${dataHora.toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })}</td>
                <td><button class="btn btn-info btnDetalhes" data-doc="${balanco.doc}">Ver Detalhes</button></td>
            </tr>
        `);

            tbody.append(row);

            // Evento para selecionar/desmarcar a linha e aplicar o estilo
            row.find('.balanco-checkbox').on('change', function () {
                const doc = $(this).data('doc');
                if ($(this).prop('checked')) {
                    row.addClass('selected-row');
                    if (!selectedDocs.includes(doc)) {
                        selectedDocs.push(doc);
                    }
                } else {
                    row.removeClass('selected-row');
                    selectedDocs = selectedDocs.filter(item => item !== doc);
                }
            });
        });

        // Adicionar evento para selecionar todos
        $('#selectAll').off('click').on('click', function () {
            const isChecked = $(this).prop('checked');
            $('.balanco-checkbox').prop('checked', isChecked).trigger('change');
        });

        // Adicionar evento para ver detalhes
        $('.btnDetalhes').off('click').on('click', function () {
            const doc = $(this).data('doc');
            loadDetalhesBalanco(doc);
        });
    }


    // Carregar detalhes do balanço
    async function loadDetalhesBalanco(doc) {
        showLoader();
        try {
            const response = await axios.post(baseUrl, {
                method: 'getBalanceByDoc',
                token: token,
                data: {
                    system_unit_id: unitId,
                    doc: doc
                }
            });

            if (response.data && response.data.success) {
                currentItems = response.data.balance.itens;
                renderDetalhesModal(response.data.balance);
                $('#balancoModal').modal('show');
            } else {
                swal("Erro", "Erro ao carregar detalhes do balanço", "error");
            }
        } catch (error) {
            console.error('Erro ao carregar detalhes:', error);
        } finally {
            hideLoader();
        }
    }

    function renderDetalhesModal(balanco) {
        const tbody = $('#detalhesBalanco');
        tbody.empty();

        $('#balancoModal .modal-title').text(`Detalhes do Balanço - Doc: ${balanco.doc} - Modelo: ${balanco.tipo_mov} - Empresa: ${balanco.unit_name}`);

        balanco.itens.forEach(item => {
            tbody.append(`
                <tr>
                    <td>${item.produto}</td>
                    <td>${item.quantidade}</td>
                    <td>${item.categoria}</td>
                </tr>
            `);
        });

        // Armazena o documento do balanço no modal para exportação
        $('#balancoModal').data('doc', balanco.doc);
        $('#balancoModal').data('tipoMov', balanco.tipo_mov);
        $('#balancoModal').data('unitName', balanco.unit_name);
        $('#balancoModal').data('usuario_nome', balanco.usuario_nome);
        $('#balancoModal').data('created_at', balanco.created_at);

    }

    // Exportar balanços selecionados para Excel
$// Exportar balanços selecionados para Excel
$('#btnExportarSelecionados').click(async function () {
    if (selectedDocs.length === 0) {
        swal("Atenção", "Nenhum balanço foi selecionado.", "warning");
        return;
    }

    swal({
        title: "Exportar",
        text: `Deseja exportar os ${selectedDocs.length} balanços selecionados para Excel?`,
        icon: "info",
        buttons: true,
        dangerMode: false,
    }, async function (willExport) {  // Correção aqui
        if (willExport) {
            let allItems = [];

            // Para cada documento selecionado, faça a requisição e agrupe os itens
            for (const doc of selectedDocs) {
                try {
                    const response = await axios.post(baseUrl, {
                        method: 'getBalanceByDoc',
                        token: token,
                        data: {
                            system_unit_id: unitId,
                            doc: doc
                        }
                    });

                    if (response.data && response.data.success) {
                        const balance = response.data.balance;
                        const items = balance.itens.map(item => ({
                            'Documento': doc,
                            'Codigo': item.codigo,
                            'Produto': item.produto,
                            'Quantidade': item.quantidade,
                            'Categoria': item.categoria
                        }));
                        allItems = allItems.concat(items);  // Agrupar os itens
                    }
                } catch (error) {
                    console.error(`Erro ao obter itens do doc ${doc}:`, error);
                }
            }

            if (allItems.length > 0) {
                exportToExcel(allItems, 'balancos_agrupados');
                swal("Sucesso", "Balanços exportados com sucesso!", "success");
            } else {
                swal("Erro", "Nenhum item para exportar.", "error");
            }
        }
    });
});



    $('#btnExportarModal').click(function () {
        if (currentItems.length > 0) {
            exportToExcel(currentItems, $('#balancoModal').data('doc'));
            swal("Sucesso", "Balanço exportado com sucesso!", "success");
        } else {
            swal("Erro", "Nenhum item para exportar.", "error");
        }
    });

    function exportToExcel(items, fileName) {
        const ws = XLSX.utils.json_to_sheet(items);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, fileName);
        XLSX.writeFile(wb, `${fileName}.xlsx`);
    }

    async function imprimirRelatorio(docNumber, dataItens, detalhes) {
        try {
            // Carregar o arquivo HTML externo da pasta reports
            const response = await fetch('./reports/impressao_balanco.html');
            if (!response.ok) {
                throw new Error('Erro ao carregar o template de impressão');
            }

            const templateHtml = await response.text();

            // Substituir os placeholders no template com os dados reais
            let htmlToPrint = templateHtml
                .replace('{{DOC_NUMBER}}', docNumber)
                .replace('{{ESTABELECIMENTO}}', detalhes.estabelecimento)
                .replace('{{DATA_BALANCO}}', detalhes.dataBalanco)
                .replace('{{USUARIO}}', detalhes.usuario)
                .replace('{{TIPO_MOV}}', detalhes.tipoMov)
                .replace('{{DATA_ITENS}}', gerarLinhasTabela(dataItens));

            // Abrir uma nova janela para impressão
            const printWindow = window.open('', '_blank');
            printWindow.document.open();
            printWindow.document.write(htmlToPrint);
            printWindow.document.close();

            // Adicionar um delay para garantir o carregamento antes de imprimir
            printWindow.onload = () => {
                printWindow.focus();
                printWindow.print();
                printWindow.close(); // Opcional, fecha a janela de impressão após o uso
            };
        } catch (error) {
            console.error('Erro ao imprimir o relatório:', error);
            alert('Erro ao imprimir o relatório. Verifique o console para mais detalhes.');
        }
    }


    function gerarLinhasTabela(dataItens) {
        return dataItens.map(item => `
        <tr>
            <td>${item.produto}</td>
            <td>${item.quantidade}</td>
            <td>${item.categoria}</td>
        </tr>
    `).join('');
    }


    $('#btnImprimir').click(function () {
        const docNumber = $('#balancoModal').data('doc');
        const dataItens = currentItems;
        const detalhes = {
            estabelecimento: $('#balancoModal').data('unitName'),
            tipoMov:  $('#balancoModal').data('tipoMov'),
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

    loadBalancos();
});
