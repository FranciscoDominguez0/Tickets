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
     'pagosDistChart','ticketsChart','staffChart'].forEach(function (id) {
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
        new Chart(c,{
            type:'line',
            data:{labels:d.incomeLabels||[],datasets:[{
                data:d.incomeTotals||[], fill:true, backgroundColor:g,
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
                backgroundColor:[C.primary,C.warning,C.danger],
                hoverBackgroundColor:['#0b5ed7','#e0a800','#b02a37'],
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
        var curr=d.incomeTotals||[];
        var prev=[0].concat(curr.slice(0,-1));
        new Chart(c,{
            type:'bar',
            data:{labels:d.incomeLabels||[],datasets:[
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
                backgroundColor:[C.success,C.warning,C.danger],
                borderRadius:6, borderSkipped:false
            }]},
            options:{indexAxis:'y',
                plugins:{legend:{display:false},tooltip:TT},
                scales:{x:Object.assign({},sY,{ticks:{font:{size:11},stepSize:1}}),
                        y:Object.assign({},sX,{ticks:{font:{size:12,weight:'600'}}})}}
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

    /* ================================================================
       7. STAFF — Polar area
       ================================================================ */
    (function(){
        var c=ctx('staffChart'); if(!c)return;
        var labels=d.staffLabels||[]; if(!labels.length)return;
        new Chart(c,{
            type:'polarArea',
            data:{labels:labels,datasets:[{
                data:d.staffCounts||[],
                backgroundColor:[
                    'rgba(13,110,253,.55)','rgba(25,135,84,.55)',
                    'rgba(255,193,7,.55)', 'rgba(220,53,69,.55)',
                    'rgba(13,202,240,.55)'
                ],
                borderWidth:2, borderColor:'#fff'
            }]},
            options:{plugins:{
                legend:{position:'bottom',labels:{boxWidth:10,font:{size:11}}},
                tooltip:TT
            },scales:{r:{ticks:{display:false},grid:{color:'rgba(0,0,0,.06)'}}}}
        });
    })();

})();