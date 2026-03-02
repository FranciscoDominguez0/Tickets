/**
 * estadisticas.js  —  /scp/superadmin/js/estadisticas.js
 *
 * Optimizaciones:
 *  - Chart.defaults.animation = false  → render instantáneo
 *  - Configuración base compartida (TT, scaleX, scaleY)
 *  - Guard por canvas ausente (sale sin error)
 *  - Destrucción limpia de instancias previas (PJAX / turbo)
 *  - Sin timers, sin polling, sin efectos diferidos
 *
 * Requiere: Chart.js cargado antes (ver <script src> en PHP)
 * Datos:    window.dashData (inyectado por PHP)
 */
(function () {
    'use strict';

    if (typeof Chart === 'undefined') return;

    /* ── Destruir instancias si la página se recargó vía PJAX ── */
    ['incomeChart','statusChart','growthChart','compareChart',
     'pagosDistChart','ticketsChart'].forEach(function (id) {
        var inst = Chart.getChart(id);
        if (inst) inst.destroy();
    });

    /* ── Paleta Bootstrap ─────────────────────────────────── */
    var C = { primary:'#0d6efd', success:'#198754', warning:'#ffc107', danger:'#dc3545', info:'#0dcaf0' };

    /* ── Defaults globales ────────────────────────────────── */
    Chart.defaults.font.family         = 'system-ui,-apple-system,sans-serif';
    Chart.defaults.font.size           = 12;
    Chart.defaults.color               = '#6c757d';
    Chart.defaults.animation           = false;   /* sin animación = render instantáneo */
    Chart.defaults.responsive          = true;
    Chart.defaults.maintainAspectRatio = true;

    /* ── Tooltip compartido ───────────────────────────────── */
    var TT = {
        backgroundColor:'#fff', borderColor:'#dee2e6', borderWidth:1,
        titleColor:'#212529',   bodyColor:'#495057',
        padding:11, displayColors:false, cornerRadius:8,
    };

    /* ── Escalas compartidas ──────────────────────────────── */
    var sX = { grid:{display:false}, border:{display:false}, ticks:{font:{size:11}} };
    var sY = { grid:{color:'rgba(0,0,0,.05)'}, border:{display:false}, ticks:{font:{size:11}} };

    /* ── Datos desde PHP ──────────────────────────────────── */
    var d = window.dashData || {};

    var monthLabels = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];

    function toMonthSeries(year) {
        var byYear = d.incomeByYear || {};
        var yearObj = byYear[String(year)] || byYear[year] || {};
        var out = [];
        for (var i = 1; i <= 12; i++) {
            var v = yearObj[String(i)];
            out.push(typeof v === 'number' ? v : 0);
        }
        return out;
    }

    /* helper */
    function ctx(id){ var e=document.getElementById(id); return e?e.getContext('2d'):null; }

    /* ================================================================
       1. INGRESOS — línea + gradiente
       ================================================================ */
    (function(){
        var c=ctx('incomeChart'); if(!c)return;
        var g=c.createLinearGradient(0,0,0,250);
        g.addColorStop(0,'rgba(13,110,253,.20)');
        g.addColorStop(1,'rgba(13,110,253,.00)');
        var yearSelect = document.getElementById('incomeYearSelect');
        var year = (yearSelect && yearSelect.value) ? parseInt(yearSelect.value, 10) : (d.incomeYearDefault || (new Date()).getFullYear());
        var incomeSeries = toMonthSeries(year);

        var incomeChart = new Chart(c,{
            type:'line',
            data:{labels:monthLabels,datasets:[{
                data:incomeSeries, fill:true, backgroundColor:g,
                borderColor:C.primary, borderWidth:2.5,
                pointBackgroundColor:'#fff', pointBorderColor:C.primary,
                pointBorderWidth:2, pointRadius:4, pointHoverRadius:7,
                tension:0.4
            }]},
            options:{plugins:{legend:{display:false},tooltip:Object.assign({},TT,{
                bodyColor:C.primary,
                callbacks:{label:function(c){return ' $'+c.parsed.y.toLocaleString('es',{minimumFractionDigits:2});}}
            })},scales:{x:sX,y:Object.assign({},sY,{ticks:{font:{size:11},
                callback:function(v){return '$'+(v>=1000?(v/1000).toFixed(1)+'k':v);}
            }})}}
        });

        function syncIncomeYear(newYear) {
            var s = toMonthSeries(newYear);
            incomeChart.data.labels = monthLabels;
            incomeChart.data.datasets[0].data = s;
            incomeChart.update();
        }

        if (yearSelect) {
            yearSelect.addEventListener('change', function () {
                var y = parseInt(yearSelect.value, 10);
                if (!isFinite(y)) return;
                syncIncomeYear(y);
                if (window.__compareChart && typeof window.__compareChartSync === 'function') {
                    window.__compareChartSync(y);
                }
            });
        }
    })();

    /* ================================================================
       2. DONUT — estado empresas
       ================================================================ */
    (function(){
        var c=ctx('statusChart'); if(!c)return;
        new Chart(c,{
            type:'doughnut',
            data:{labels:['Activas','Vencidas','Bloqueadas'],datasets:[{
                data:[d.kpiActivas||0,d.kpiVencidas||0,d.kpiBloqueadas||0],
                backgroundColor:[C.primary,'#3b82f6','#60a5fa'],
                hoverBackgroundColor:['#0b5ed7','#2563eb','#3b82f6'],
                borderWidth:3, borderColor:'#fff', hoverOffset:4
            }]},
            options:{cutout:'72%',plugins:{legend:{display:false},tooltip:TT}}
        });
    })();

    /* ================================================================
       3. CRECIMIENTO — barras verticales
       ================================================================ */
    (function(){
        var c=ctx('growthChart'); if(!c)return;
        new Chart(c,{
            type:'bar',
            data:{labels:d.growthLabels||[],datasets:[{
                data:d.growthTotals||[],
                backgroundColor:'rgba(13,110,253,.13)',
                hoverBackgroundColor:C.primary,
                borderColor:C.primary, borderWidth:1.5,
                borderRadius:6, borderSkipped:false
            }]},
            options:{plugins:{legend:{display:false},tooltip:Object.assign({},TT,{bodyColor:C.primary})},
                scales:{x:sX,y:Object.assign({},sY,{ticks:{font:{size:11},stepSize:1}})}}
        });
    })();

    /* ================================================================
       4. COMPARATIVO — barras agrupadas
       ================================================================ */
    (function(){
        var c=ctx('compareChart'); if(!c)return;
        var yearSelect = document.getElementById('incomeYearSelect');
        var year = (yearSelect && yearSelect.value) ? parseInt(yearSelect.value, 10) : (d.incomeYearDefault || (new Date()).getFullYear());
        var curr = toMonthSeries(year);
        var prev=[0].concat(curr.slice(0,-1));
        var compareChart = new Chart(c,{
            type:'bar',
            data:{labels:monthLabels,datasets:[
                {label:'Mes actual',   data:curr, backgroundColor:C.primary,             borderRadius:5, borderSkipped:false},
                {label:'Mes anterior', data:prev, backgroundColor:'rgba(13,110,253,.22)', borderRadius:5, borderSkipped:false}
            ]},
            options:{plugins:{
                legend:{position:'bottom',labels:{boxWidth:10,font:{size:11}}},
                tooltip:Object.assign({},TT,{callbacks:{label:function(c){
                    return ' $'+c.parsed.y.toLocaleString('es',{minimumFractionDigits:2});
                }}})
            },scales:{x:sX,y:Object.assign({},sY,{ticks:{font:{size:11},
                callback:function(v){return '$'+(v>=1000?(v/1000).toFixed(0)+'k':v);}
            }})}}
        });

        window.__compareChart = compareChart;
        window.__compareChartSync = function (newYear) {
            var s = toMonthSeries(newYear);
            compareChart.data.labels = monthLabels;
            compareChart.data.datasets[0].data = s;
            compareChart.data.datasets[1].data = [0].concat(s.slice(0,-1));
            compareChart.update();
        };
    })();

    /* ================================================================
       5. DISTRIBUCIÓN PAGOS — barra horizontal
       ================================================================ */
    (function(){
        var c=ctx('pagosDistChart'); if(!c)return;
        new Chart(c,{
            type:'bar',
            data:{labels:['Al día','Vencido','Suspendido'],datasets:[{
                data:[d.pagosAlDia||0,d.pagosVencidos||0,d.pagosSuspendidos||0],
                backgroundColor:[C.primary,'#3b82f6','#60a5fa'],
                borderRadius:6, borderSkipped:false
            }]},
            options:{indexAxis:'y',
                plugins:{legend:{display:false},tooltip:TT},
                scales:{x:Object.assign({},sY,{ticks:{font:{size:11},stepSize:1}}),
                        y:Object.assign({},sX,{ticks:{font:{size:11}}})}}
        });
    })();

    /* ================================================================
       6. TOP TICKETS — barra horizontal
       ================================================================ */
    (function(){
        var c=ctx('ticketsChart'); if(!c)return;
        var labels=d.ticketLabels||[]; if(!labels.length)return;
        new Chart(c,{
            type:'bar',
            data:{labels:labels,datasets:[{
                data:d.ticketCounts||[],
                backgroundColor:[C.primary,C.info,C.success,C.warning,C.danger].slice(0,labels.length),
                borderRadius:7, borderSkipped:false
            }]},
            options:{indexAxis:'y',
                plugins:{legend:{display:false},tooltip:TT},
                scales:{x:Object.assign({},sY,{ticks:{font:{size:11},stepSize:1}}),
                        y:Object.assign({},sX,{ticks:{font:{size:11}}})}}
        });
    })();
})();