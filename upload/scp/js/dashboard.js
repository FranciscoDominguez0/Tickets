// Gráfica de actividad de tickets con múltiples líneas (created, closed, deleted)
(function () {
    if (window._dashboardJsLoaded) {
        return;
    }
    window._dashboardJsLoaded = true;

    console.log('dashboard.js cargado');

    // Leer datos desde JSON embebido (evita JS inline en PHP)
    function initDashboardData() {
        var el = document.getElementById('dashboard-data');
        if (!el) return;
        try {
            var raw = (el.textContent || el.innerText || '').toString().trim();
            if (!raw) return;
            var obj = JSON.parse(raw);
            if (obj && typeof obj === 'object') {
                window.dashboardData = obj;
            }
        } catch (e) {
            console.warn('No se pudo parsear dashboard-data', e);
        }
    }
    initDashboardData();

    // Export CSV
    document.addEventListener('click', function (e) {
        var t = e.target;
        if (!t) return;
        var btn = t.closest ? t.closest('[data-action="dashboard-export"]') : null;
        if (!btn) return;
        e.preventDefault();

        var type = (btn.getAttribute('data-export-type') || '').toString();
        if (!type) return;

        var form = document.querySelector('form[action="dashboard.php"]') || document.querySelector('form');
        var startEl = form ? form.querySelector('input[name="start"]') : null;
        var periodEl = form ? form.querySelector('select[name="period"]') : null;

        var start = startEl ? (startEl.value || '').toString() : '';
        var period = periodEl ? (periodEl.value || '').toString() : '';

        var qs = new URLSearchParams();
        qs.set('action', 'export_csv');
        qs.set('type', type);
        if (period) qs.set('period', period);
        if (start) qs.set('start', start);

        window.location.href = 'dashboard.php?' + qs.toString();
    });
    
    let currentChart = null;

    // Esperar a que el DOM esté completamente cargado
    function initChart() {
        const ctx = document.getElementById('ticketsActivityChart');
        if (!ctx) {
            console.error('Canvas element not found: ticketsActivityChart');
            // Ya no reintentamos ciegamente, porque puede que no estemos en la página de dashboard.
            return;
        }
        
        console.log('Canvas encontrado:', ctx);

        // Actualizar los datos desde el HTML inyectado
        initDashboardData();

        // Obtener datos (formato nuevo o antiguo)
        let labels, createdData, closedData, deletedData;
        
        if (window.dashboardData) {
            // Formato nuevo (similar a osTicket)
            labels = window.dashboardData.labels || [];
            createdData = window.dashboardData.plots?.created || [];
            closedData = window.dashboardData.plots?.closed || [];
            deletedData = window.dashboardData.plots?.deleted || [];
        } else {
            // Formato antiguo (compatibilidad)
            labels = window.dashboardLabels || [];
            createdData = window.dashboardCreated || [];
            closedData = window.dashboardClosed || [];
            deletedData = window.dashboardDeleted || [];
        }
        
        // Verificar que hay datos
        if (!labels || labels.length === 0) {
            ctx.parentElement.innerHTML = '<p class="text-muted text-center p-4">No hay datos disponibles para el período seleccionado.</p>';
            return;
        }
        
        // Verificar que Chart.js esté disponible
        if (typeof Chart === 'undefined') {
            ctx.parentElement.innerHTML = '<p class="text-danger text-center p-4">Error: Chart.js no está cargado correctamente.</p>';
            return;
        }

        // Destruir gráfica anterior si existe
        if (currentChart) {
            currentChart.destroy();
        }

        function makeGradient(canvasCtx, area, color) {
            var g = canvasCtx.createLinearGradient(0, area.top, 0, area.bottom);
            g.addColorStop(0, color + '33');
            g.addColorStop(0.55, color + '14');
            g.addColorStop(1, color + '00');
            return g;
        }

        var isShortRange = Array.isArray(labels) && labels.length <= 2;

        // Crear la gráfica con Chart.js
        currentChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Creados',
                        data: createdData,
                        borderColor: '#28a745',
                        backgroundColor: function(context) {
                            var chart = context.chart;
                            var area = chart.chartArea;
                            if (!area) return 'rgba(40, 167, 69, 0.08)';
                            return makeGradient(chart.ctx, area, '#28a745');
                        },
                        fill: true,
                        tension: 0.4,
                        pointRadius: isShortRange ? 3 : 0,
                        pointHoverRadius: isShortRange ? 5 : 4,
                        pointHitRadius: 10,
                        borderWidth: 2,
                        borderCapStyle: 'round',
                        pointBackgroundColor: '#28a745',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: isShortRange ? 2 : 0
                    },
                    {
                        label: 'Cerrados',
                        data: closedData,
                        borderColor: '#007bff',
                        backgroundColor: function(context) {
                            var chart = context.chart;
                            var area = chart.chartArea;
                            if (!area) return 'rgba(0, 123, 255, 0.08)';
                            return makeGradient(chart.ctx, area, '#007bff');
                        },
                        fill: true,
                        tension: 0.4,
                        pointRadius: isShortRange ? 3 : 0,
                        pointHoverRadius: isShortRange ? 5 : 4,
                        pointHitRadius: 10,
                        borderWidth: 2,
                        borderCapStyle: 'round',
                        pointBackgroundColor: '#007bff',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: isShortRange ? 2 : 0
                    },
                    {
                        label: 'Borrados',
                        data: deletedData,
                        borderColor: '#dc3545',
                        backgroundColor: function(context) {
                            var chart = context.chart;
                            var area = chart.chartArea;
                            if (!area) return 'rgba(220, 53, 69, 0.08)';
                            return makeGradient(chart.ctx, area, '#dc3545');
                        },
                        fill: true,
                        tension: 0.4,
                        pointRadius: isShortRange ? 3 : 0,
                        pointHoverRadius: isShortRange ? 5 : 4,
                        pointHitRadius: 10,
                        borderWidth: 2,
                        borderCapStyle: 'round',
                        pointBackgroundColor: '#dc3545',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: isShortRange ? 2 : 0
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 650, easing: 'easeOutQuart' },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        mode: 'index', intersect: false, backgroundColor: 'rgba(15, 23, 42, 0.92)',
                        padding: 12, cornerRadius: 10, caretSize: 6, displayColors: true, boxPadding: 6,
                        titleFont: { size: 12 }, bodyFont: { size: 11 }
                    }
                },
                interaction: { mode: 'index', intersect: false },
                elements: { line: { borderJoinStyle: 'round' } },
                scales: {
                    x: {
                        grid: { display: true, color: 'rgba(148, 163, 184, 0.25)' },
                        ticks: { autoSkip: true, maxTicksLimit: 10, maxRotation: 45, minRotation: 0, font: { size: 10 }, color: '#64748b' }
                    },
                    y: {
                        beginAtZero: true, precision: 0,
                        ticks: { stepSize: 1, font: { size: 10 }, color: '#64748b' },
                        grid: { color: 'rgba(148, 163, 184, 0.25)' }
                    }
                }
            }
        });
        
        const legendContainer = document.getElementById('line-chart-legend');
        if (legendContainer) {
            legendContainer.innerHTML = '';
            legendContainer.style.cssText = 'display: flex; flex-wrap: wrap; justify-content: center; gap: 12px; margin-top: 18px; padding: 0 10px;';
            const datasets = currentChart.data.datasets;
            datasets.forEach((dataset, index) => {
                const legendItem = document.createElement('div');
                legendItem.className = 'chart-legend-chip';
                
                const colorBox = document.createElement('span');
                colorBox.className = 'chart-legend-dot';
                colorBox.style.backgroundColor = dataset.borderColor;
                
                const label = document.createElement('span');
                label.className = 'chart-legend-label';
                label.textContent = dataset.label;
                
                legendItem.appendChild(colorBox);
                legendItem.appendChild(label);
                
                legendItem.addEventListener('click', function() {
                    const meta = currentChart.getDatasetMeta(index);
                    meta.hidden = !meta.hidden;
                    currentChart.update();
                    
                    if (meta.hidden) {
                        legendItem.style.opacity = '0.4';
                        legendItem.style.textDecoration = 'line-through';
                    } else {
                        legendItem.style.opacity = '1';
                        legendItem.style.textDecoration = 'none';
                    }
                });
                
                legendContainer.appendChild(legendItem);
            });
        }
    }
    
    // Escuchar eventos de navegación SPA
    window.addEventListener('spaContentUpdated', function() {
        // Le damos 50ms para que el DOM se asiente
        setTimeout(initChart, 50);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initChart);
    } else {
        setTimeout(initChart, 50);
    }
})();
