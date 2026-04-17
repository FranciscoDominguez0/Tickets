// Gráfica de actividad de tickets con múltiples líneas (created, closed, deleted)
(function () {
    console.log('dashboard.js cargado');

    // Leer datos desde JSON embebido (evita JS inline en PHP)
    (function initDashboardData() {
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
    })();

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
    
    // Esperar a que el DOM esté completamente cargado
    function initChart() {
        const ctx = document.getElementById('ticketsActivityChart');
        if (!ctx) {
            console.error('Canvas element not found: ticketsActivityChart');
            // Reintentar después de un breve delay
            setTimeout(initChart, 100);
            return;
        }
        
        console.log('Canvas encontrado:', ctx);

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
        
        console.log('Chart Data:', { labels, createdData, closedData, deletedData });
        
        // Verificar que hay datos
        if (!labels || labels.length === 0) {
            console.warn('No hay datos para mostrar en la gráfica');
            ctx.parentElement.innerHTML = '<p class="text-muted text-center p-4">No hay datos disponibles para el período seleccionado.</p>';
            return;
        }
        
        // Verificar que Chart.js esté disponible
        if (typeof Chart === 'undefined') {
            console.error('Chart.js no está cargado');
            ctx.parentElement.innerHTML = '<p class="text-danger text-center p-4">Error: Chart.js no está cargado correctamente.</p>';
            return;
        }

        console.log('Creando gráfica...');

        var isShortRange = Array.isArray(labels) && labels.length <= 2;

        function makeGradient(canvasCtx, area, color) {
            var g = canvasCtx.createLinearGradient(0, area.top, 0, area.bottom);
            g.addColorStop(0, color + '33');
            g.addColorStop(0.55, color + '14');
            g.addColorStop(1, color + '00');
            return g;
        }

        // Crear la gráfica con Chart.js
        const chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'created',
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
                        label: 'closed',
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
                        label: 'deleted',
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
                animation: {
                    duration: 650,
                    easing: 'easeOutQuart'
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 15,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(15, 23, 42, 0.92)',
                        padding: 12,
                        cornerRadius: 10,
                        caretSize: 6,
                        displayColors: true,
                        boxPadding: 6,
                        titleFont: {
                            size: 12
                        },
                        bodyFont: {
                            size: 11
                        }
                    }
                },
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                elements: {
                    line: {
                        borderJoinStyle: 'round'
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: true,
                            color: 'rgba(148, 163, 184, 0.25)'
                        },
                        ticks: {
                            autoSkip: true,
                            maxTicksLimit: 10,
                            maxRotation: 45,
                            minRotation: 0,
                            font: {
                                size: 10
                            },
                            color: '#64748b'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        precision: 0,
                        ticks: {
                            stepSize: 1,
                            font: {
                                size: 10
                            },
                            color: '#64748b'
                        },
                        grid: {
                            color: 'rgba(148, 163, 184, 0.25)'
                        }
                    }
                }
            }
        });

        console.log('Gráfica creada exitosamente');

        // Crear leyenda personalizada (similar a osTicket)
        const legendContainer = document.getElementById('line-chart-legend');
        if (legendContainer) {
            legendContainer.innerHTML = ''; // Limpiar contenido anterior
            const datasets = chart.data.datasets;
            datasets.forEach((dataset, index) => {
                const legendItem = document.createElement('div');
                legendItem.style.cssText = 'margin-bottom: 5px; cursor: pointer; display: flex; align-items: center;';
                
                const colorBox = document.createElement('span');
                colorBox.style.cssText = `display: inline-block; width: 16px; height: 16px; background-color: ${dataset.borderColor}; margin-right: 8px; border-radius: 3px;`;
                
                const label = document.createElement('span');
                label.textContent = dataset.label;
                label.style.cssText = 'font-size: 12px; color: #333;';
                
                legendItem.appendChild(colorBox);
                legendItem.appendChild(label);
                
                // Toggle al hacer clic
                legendItem.addEventListener('click', function() {
                    const meta = chart.getDatasetMeta(index);
                    meta.hidden = !meta.hidden;
                    chart.update();
                    
                    if (meta.hidden) {
                        legendItem.style.opacity = '0.5';
                    } else {
                        legendItem.style.opacity = '1';
                    }
                });
                
                legendContainer.appendChild(legendItem);
            });
        }
    }
    
    // Inicializar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initChart);
    } else {
        // Si ya está cargado, esperar un poco para que los scripts se ejecuten
        setTimeout(initChart, 100);
    }
})();
