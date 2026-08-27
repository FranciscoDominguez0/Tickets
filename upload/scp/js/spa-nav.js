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

    var navInFlight = false;
    var loadedStyles = {};
    var lastAssetsHtml = '';
    var sidebar = document.querySelector('.sidebar');

    // Estilos ya presentes en la página (carga completa inicial)
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
        var seen = {};
        list.forEach(function (src) {
            // Sin dedup entre navegaciones: cada vez que se vuelve a una ruta, sus
            // scripts se re-ejecutan para inicializar el contenido nuevo (p. ej.
            // dashboard.js debe volver a dibujar la gráfica en el canvas nuevo).
            // El navegador sirve el archivo desde caché; solo se evita duplicar
            // el MISMO src dentro de una misma navegación.
            if (!src || seen[src]) return;
            seen[src] = true;
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

    function navigate(url, fromPop) {
        if (navInFlight) return;
        navInFlight = true;

        fetch(url, {
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
        })
        .then(function (data) {
            if (!data || !data.ok) throw new Error('bad-response');
            lastAssetsHtml = data.assets || '';
            mainContent.innerHTML = data.html || '';
            injectStyles(lastAssetsHtml);
            return loadExternalScripts().then(function () {
                runInlineScripts(mainContent);
            });
        })
        .then(function () {
            setActiveSidebar(url);
            if (!fromPop) {
                try { history.pushState({ scpUrl: url }, '', url); } catch (e) {}
            }
            // Cerrar el sidebar móvil tras navegar (solo si estaba abierto)
            var wasMobileOpen = document.body.classList.contains('sidebar-mobile-open');
            document.body.classList.remove('sidebar-mobile-open');
            if (wasMobileOpen) {
                var toggleBtn = document.getElementById('scpSidebarToggle');
                if (toggleBtn) toggleBtn.setAttribute('aria-expanded', 'false');
            }
            window.scrollTo(0, 0);
            navInFlight = false;
        })
        .catch(function () {
            navInFlight = false;
            // Fallback seguro: navegación completa (mismo comportamiento de siempre)
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