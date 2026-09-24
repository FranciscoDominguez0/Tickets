/**
 * Navegación tipo SPA para el panel de agente.
 * Al hacer clic en opciones del sidebar (o del flyout cuando está colapsado),
 * SOLO se reemplaza el contenido (#scpMainContent). El sidebar, el header y el
 * layout permanecen completamente estáticos: no se re-renderizan, no hay flash.
 *
 * El servidor responde JSON {ok, html, assets, route} cuando la petición trae
 * los encabezados X-Requested-With: XMLHttpRequest y X-SCP-AJAX: 1 (ver
 * partials/ajax-response.inc.php).
 */
(function () {
    var mainContent = document.getElementById('scpMainContent');
    if (!mainContent || !window.fetch) return;

    var globalSeenScripts = {};
    // Pre-populate deduplication list with scripts that are already in the DOM on initial load
    document.querySelectorAll('script[src]').forEach(function (s) {
        var src = s.getAttribute('src');
        if (!src) return;
        var a = document.createElement('a');
        a.href = src;
        var baseSrc = a.href.split('?')[0];
        if (baseSrc) globalSeenScripts[baseSrc] = true;
    });

    var navInFlight = false;
    var loadedStyles = {};
    var lastAssetsHtml = '';
    var sidebar = document.querySelector('.sidebar');

    var progressBar;
    function startProgress() {
        if (!progressBar) {
            progressBar = document.createElement('div');
            progressBar.id = 'scp-spa-progress';
            // Estilos para una barra azul suave en la parte superior
            progressBar.style.cssText = 'position:fixed; top:0; left:0; height:3px; background:#3b82f6; z-index:99999; transition: width 0.3s ease, opacity 0.3s ease; width:0%; opacity:1; pointer-events:none;';
            document.body.appendChild(progressBar);
        }
        progressBar.style.opacity = '1';
        progressBar.style.width = '15%';
        // Simular avance
        setTimeout(function() { if (navInFlight) progressBar.style.width = '40%'; }, 150);
        setTimeout(function() { if (navInFlight) progressBar.style.width = '70%'; }, 400);
    }
    
    function completeProgress() {
        if (progressBar) {
            progressBar.style.width = '100%';
            setTimeout(function() {
                progressBar.style.opacity = '0';
                setTimeout(function() { progressBar.style.width = '0%'; }, 300);
            }, 300);
        }
    }

    document.querySelectorAll('link[rel="stylesheet"]').forEach(function (l) {
        loadedStyles[resolveUrl(l.getAttribute('href'))] = true;
    });

    function resolveUrl(u) {
        if (!u) return '';
        var a = document.createElement('a');
        a.href = u;
        return a.href;
    }

    function normPath(url) {
        var p = String(url).split('?')[0];
        var idx = p.indexOf('/upload/scp/');
        if (idx !== -1) {
            p = p.slice(idx + '/upload/scp/'.length);
        } else {
            p = p.replace(/^.*\/([^/]+)$/, '$1');
        }
        return p.replace(/^\/+/, '');
    }

    function getParam(qs, k) {
        var m = String(qs || '').match(new RegExp('(?:^|[?&])' + k + '=([^&]*)'));
        return m ? decodeURIComponent(m[1]) : '';
    }

    function matchLink(link, url) {
        var href = link.getAttribute('href') || '';
        if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) return false;
        if (/logout\.php/i.test(href)) return false;
        if (normPath(href) !== normPath(url)) return false;
        // Tickets: el enlace "Detalles" cubre cualquier filtro excepto "Por facturar"
        if (normPath(href) === 'tickets.php') {
            var linkFilter = getParam(href.split('?')[1] || '', 'filter') || '';
            var urlFilter = getParam(url.split('?')[1] || '', 'filter') || '';
            if (linkFilter === 'billing_pending') {
                if (urlFilter !== 'billing_pending') return false;
            } else if (urlFilter === 'billing_pending') {
                return false;
            }
        }
        return true;
    }

    // Marca el enlace activo (exclusivo) y deja abierta SOLO la sección de la ruta
    // activa (comportamiento acordeón): al cambiar de sección, la sección anterior
    // se cierra porque ya no se usa. El toggle activo también se actualiza.
    function setActiveSidebar(url) {
        if (!sidebar) return;
        sidebar.querySelectorAll('a.sidebar-link').forEach(function (link) {
            link.classList.toggle('active', matchLink(link, url));
        });
        var activeLink = sidebar.querySelector('.sidebar-subnav a.sidebar-link.active');
        var activeGroup = activeLink ? activeLink.closest('li.sidebar-group') : null;
        sidebar.querySelectorAll('li.sidebar-group').forEach(function (group) {
            var toggle = group.querySelector(':scope > .sidebar-toggle');
            var subnav = group.querySelector(':scope > .sidebar-subnav');
            var isActiveGroup = (group === activeGroup);
            if (toggle) {
                toggle.classList.toggle('active', isActiveGroup);
                if (isActiveGroup) {
                    toggle.classList.add('expanded');
                    toggle.setAttribute('aria-expanded', 'true');
                } else {
                    toggle.classList.remove('expanded');
                    toggle.setAttribute('aria-expanded', 'false');
                }
            }
            if (subnav) subnav.classList.toggle('open', isActiveGroup);
        });
        // Sincronizar el estado persistido con el nuevo estado (acordeón)
        if (window.__scpPersistSubnavState) window.__scpPersistSubnavState();
    }

    function injectStyles(html) {
        var wrap = document.createElement('div');
        wrap.innerHTML = html;
        var added = [];
        wrap.querySelectorAll('link[rel="stylesheet"]').forEach(function (old) {
            var href = resolveUrl(old.getAttribute('href'));
            if (!href || loadedStyles[href]) {
                if (old.parentNode) old.parentNode.removeChild(old);
                return;
            }
            loadedStyles[href] = true;
            var link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = href;
            document.head.appendChild(link);
            added.push(link);
        });
        return added;
    }

    // Espera a que los CSS recién inyectados terminen de cargar (o expira el
    // timeout). Evita el "flash" de contenido sin estilos al navegar: el HTML
    // nuevo solo se muestra cuando su hoja de estilos ya está aplicada.
    function waitForStyles(links, timeout) {
        if (!links.length) return Promise.resolve();
        return new Promise(function (resolve) {
            var done = false;
            var timer = setTimeout(finish, timeout);
            function finish() {
                if (done) return;
                done = true;
                clearTimeout(timer);
                links.forEach(function (l) {
                    l.removeEventListener('load', finish);
                    l.removeEventListener('error', finish);
                });
                resolve();
            }
            links.forEach(function (l) {
                l.addEventListener('load', finish);
                l.addEventListener('error', finish);
                // Si el navegador ya lo tenía en caché, .sheet está disponible de
                // inmediato y el evento load pudo dispararse antes del listener.
                try { if (l.sheet) finish(); } catch (e) {}
            });
        });
    }
    function loadExternalScripts() {
        // Scripts externos del contenido y de los assets de la ruta
        var list = [];
        mainContent.querySelectorAll('script[src]').forEach(function (s) {
            var src = resolveUrl(s.getAttribute('src'));
            if (src) list.push(src);
        });
        var wrap = document.createElement('div');
        wrap.innerHTML = lastAssetsHtml;
        wrap.querySelectorAll('script[src]').forEach(function (s) {
            var src = resolveUrl(s.getAttribute('src'));
            if (src) list.push(src);
        });

        var chain = Promise.resolve();
        list.forEach(function (src) {
            // Dedup GLOBAL: Evitamos re-ejecutar scripts como jQuery o Chart.js.
            // Para lógica por página, los scripts deben escuchar el evento 'spaContentUpdated'.
            var baseSrc = src ? src.split('?')[0] : '';
            if (!baseSrc || globalSeenScripts[baseSrc]) return;
            globalSeenScripts[baseSrc] = true;
            chain = chain.then(function () {
                return new Promise(function (resolve) {
                    // Mismo shim que para los inline: si el script registra
                    // listeners de DOMContentLoaded, se capturan y ejecutan al
                    // terminar, porque DOMContentLoaded ya ocurrió.
                    var pendingReady = [];
                    var origAdd = document.addEventListener;
                    document.addEventListener = function (type, fn) {
                        if (type === 'DOMContentLoaded' && typeof fn === 'function') {
                            pendingReady.push(fn);
                            return;
                        }
                        return origAdd.call(document, type, fn);
                    };
                    var s = document.createElement('script');
                    s.src = src;
                    s.onload = done;
                    s.onerror = done; // un asset fallido no debe bloquear la navegación
                    function done() {
                        document.addEventListener = origAdd;
                        pendingReady.forEach(function (fn) {
                            try { fn.call(document); } catch (e) {}
                        });
                        resolve();
                    }
                    document.head.appendChild(s);
                });
            });
        });
        return chain;
    }

    // Re-ejecuta los scripts inline del contenido nuevo. Los listeners registrados
    // con document.addEventListener('DOMContentLoaded', ...) se capturan y ejecutan
    // de inmediato, porque DOMContentLoaded ya ocurrió para el documento.
    function runInlineScripts(root) {
        var scripts = [].slice.call(root.querySelectorAll('script:not([src])')).filter(function (s) {
            var t = (s.getAttribute('type') || '').trim().toLowerCase();
            return t === '' || t === 'text/javascript' || t === 'application/javascript' || t === 'module';
        });
        scripts.forEach(function (old) {
            var code = old.textContent || '';
            var pendingReady = [];
            var origAdd = document.addEventListener;
            document.addEventListener = function (type, fn) {
                if (type === 'DOMContentLoaded' && typeof fn === 'function') {
                    pendingReady.push(fn);
                    return;
                }
                return origAdd.call(document, type, fn);
            };
            try {
                (0, eval)(code);
            } catch (e) {
                try { console.warn('Error ejecutando script del contenido:', e); } catch (e2) {}
            } finally {
                document.addEventListener = origAdd;
                pendingReady.forEach(function (fn) {
                    try { fn.call(document); } catch (e) {}
                });
            }
        });
    }

    function delay(ms) {
        return new Promise(function(resolve) { setTimeout(resolve, ms); });
    }

    function navigate(url, fromPop) {
        if (navInFlight) return;
        navInFlight = true;

        var startTime = Date.now();
        
        startProgress();

        // 1. Iniciar petición
        var fetchPromise = fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-SCP-AJAX': '1',
                'Accept': 'application/json'
            },
            credentials: 'same-origin'
        })
        .then(function (r) {
            var ct = (r.headers.get('content-type') || '').toLowerCase();
            if (ct.indexOf('application/json') === -1) throw new Error('not-json');
            return r.json();
        });

        // No añadimos delays artificiales ni animaciones de fade para mantener máxima velocidad

        // 2. Transición suave sin skeleton
        // Se elimina el skeleton porque destruía el DOM prematuramente y causaba bugs con popovers
        
        fetchPromise.then(function(data) {
            if (!data || !data.ok) throw new Error('bad-response');
            
            completeProgress();
            
            // Inyectar nuevo contenido inmediatamente
            lastAssetsHtml = data.assets || '';
            var pendingStyles = injectStyles(lastAssetsHtml);
            
            return waitForStyles(pendingStyles, 2000).then(function () {
                mainContent.innerHTML = data.html || '';
                if (typeof window.scrollTo === 'function' && 'scrollBehavior' in document.documentElement.style) {
                    window.scrollTo({ top: 0, left: 0, behavior: 'instant' });
                } else {
                    window.scrollTo(0, 0);
                }
                
                return loadExternalScripts().then(function () {
                    runInlineScripts(mainContent);
                    
                    // Notificar a scripts globales (como dashboard.js) que el contenido cambió
                    window.dispatchEvent(new Event('spaContentUpdated'));
                });
            });
        })
        .then(function () {
            setActiveSidebar(url);
            try { sessionStorage.setItem('scpCurrentUrl', url); } catch(e) {}
            if (!fromPop) {
                var displayUrl = url;
                if (window.HIDE_URLS) {
                    displayUrl = url.split('?')[0] + '#';
                }
                try { history.pushState({ scpUrl: url }, '', displayUrl); } catch (e) {}
            }
            var wasMobileOpen = document.body.classList.contains('sidebar-mobile-open');
            document.body.classList.remove('sidebar-mobile-open');
            if (wasMobileOpen) {
                var toggleBtn = document.getElementById('scpSidebarToggle');
                if (toggleBtn) toggleBtn.setAttribute('aria-expanded', 'false');
            }
            navInFlight = false;
        })
        .catch(function (e) {
            completeProgress();
            mainContent.style.transition = '';
            mainContent.style.opacity = '';
            navInFlight = false;
            window.location.href = url;
        });
    }

    document.addEventListener('click', function (e) {
        var link = e.target.closest('a.sidebar-link, a.sidebar-flyout-link');
        if (!link) return;
        var url = link.getAttribute('href');
        if (!url || url.charAt(0) === '#' || url.indexOf('javascript:') === 0) return;
        if (/logout\.php/i.test(url)) return; // logout siempre navega completo
        if (resolveUrl(url) === window.location.href) return;
        e.preventDefault();
        navigate(url, false);
    });

    // Back/forward del navegador
    window.addEventListener('popstate', function (e) {
        if (e.state && e.state.scpUrl) {
            navigate(e.state.scpUrl, true);
        }
    });
})();