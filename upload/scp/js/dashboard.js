// Gráfica de actividad de tickets con múltiples líneas (created, closed, deleted)
(function () {
    console.log('dashboard.js cargado');
    
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

        // Crear la gráfica con Chart.js
        const chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'created',
                        data: createdData,
                        borderColor: '#28a745', // Verde para created
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        fill: false,
                        tension: 0.1,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        pointBackgroundColor: '#28a745',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    },
                    {
                        label: 'closed',
                        data: closedData,
                        borderColor: '#007bff', // Azul para closed
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        fill: false,
                        tension: 0.1,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        pointBackgroundColor: '#007bff',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    },
                    {
                        label: 'deleted',
                        data: deletedData,
                        borderColor: '#dc3545', // Rojo para deleted
                        backgroundColor: 'rgba(220, 53, 69, 0.1)',
                        fill: false,
                        tension: 0.1,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        pointBackgroundColor: '#dc3545',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
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
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 10,
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
                scales: {
                    x: {
                        grid: {
                            display: true,
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            autoSkip: true,
                            maxTicksLimit: 10,
                            maxRotation: 45,
                            minRotation: 0,
                            font: {
                                size: 10
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        precision: 0,
                        ticks: {
                            stepSize: 1,
                            font: {
                                size: 10
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
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
