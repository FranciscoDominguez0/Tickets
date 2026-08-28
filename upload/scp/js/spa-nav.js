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

    // Barra de progreso de navegación
    var navLoader = null;
    function showNavLoader() {
        if (navLoader) return;
        navLoader = document.createElement('div');
        navLoader.id = 'scp-nav-loader';
        navLoader.style.cssText = [
            'position:fixed',
            'top:0',
            'left:0',
            'width:0%',
            'height:3px',
            'background:linear-gradient(90deg,#ef4444,#f87171)',
            'z-index:9999',
            'transition:width .3s ease',
            'border-radius:0 2px 2px 0',
            'box-shadow:0 0 8px rgba(239,68,68,.6)'
        ].join(';');
        document.body.appendChild(navLoader);
        // Animar a 70% rapidamente, la barra llega al 100% cuando termina
        requestAnimationFrame(function() {
            requestAnimationFrame(function() {
                if (navLoader) navLoader.style.width = '70%';
            });
        });
    }
    function hideNavLoader() {
        if (!navLoader) return;
        navLoader.style.transition = 'width .15s ease, opacity .2s ease .1s';
        navLoader.style.width = '100%';
        navLoader.style.opacity = '0';
        var el = navLoader;
        navLoader = null;
        setTimeout(function() { if (el && el.parentNode) el.parentNode.removeChild(el); }, 350);
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
        showNavLoader();

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
            // Inyectar los estilos de la ruta ANTES del contenido: el HTML nuevo
            // se muestra solo cuando su CSS ya esté aplicado (sin flash feo).
            // Timeout reducido a 800ms: CSS locales cargan en <100ms en red local.
            var pendingStyles = injectStyles(lastAssetsHtml);
            mainContent.style.transition = 'opacity .12s ease';
            mainContent.style.opacity = '0.35';
            return waitForStyles(pendingStyles, 800).then(function () {
                mainContent.innerHTML = data.html || '';
                mainContent.style.opacity = '';
                return loadExternalScripts().then(function () {
                    runInlineScripts(mainContent);
                });
            });
        })
        .then(function () {
            hideNavLoader();
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
            if (typeof window.scrollTo === 'function' && 'scrollBehavior' in document.documentElement.style) {
                window.scrollTo({ top: 0, left: 0, behavior: 'instant' });
            } else {
                window.scrollTo(0, 0);
            }
            navInFlight = false;
        })
        .catch(function () {
            hideNavLoader();
            mainContent.style.opacity = '';
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