document.addEventListener('DOMContentLoaded', function () {
    var body = document.body;
    var sidebar = document.querySelector('.sidebar');
    var sidebarToggle = document.getElementById('scpSidebarToggle');
    var sidebarFlyout = document.getElementById('scpSidebarFlyout');
    var mobileQuery = window.matchMedia('(max-width: 991px)');
    var SIDEBAR_STATE_KEY = 'scp_sidebar_collapsed_v1';
    var SIDEBAR_STATE_COOKIE = 'scp_sidebar_collapsed';

    function persistSidebarCookie(value) {
        var maxAge = 60 * 60 * 24 * 365;
        document.cookie = SIDEBAR_STATE_COOKIE + '=' + value + '; path=/; max-age=' + maxAge + '; samesite=lax';
    }

    function isMobile() {
        return mobileQuery.matches;
    }

    function updateToggleAria() {
        if (!sidebarToggle) return;
        var isExpanded = true;
        if (isMobile()) {
            isExpanded = body.classList.contains('sidebar-mobile-open');
        } else {
            isExpanded = !body.classList.contains('sidebar-collapsed');
        }
        sidebarToggle.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
    }

    function setDesktopSidebarCollapsed(collapsed, persist) {
        body.classList.toggle('sidebar-collapsed', collapsed);
        if (!collapsed) {
            closeSidebarFlyout();
        }
        if (persist) {
            var nextState = collapsed ? 'collapsed' : 'expanded';
            localStorage.setItem(SIDEBAR_STATE_KEY, nextState);
            persistSidebarCookie(nextState);
        }
        updateToggleAria();
    }

    function closeSidebarFlyout() {
        if (!sidebarFlyout) return;
        sidebarFlyout.classList.remove('open');
        sidebarFlyout.setAttribute('aria-hidden', 'true');
        sidebarFlyout.innerHTML = '';
        delete sidebarFlyout.dataset.owner;
    }

    function openSidebarFlyout(ownerButton, subnav) {
        if (!sidebarFlyout || !ownerButton || !subnav) return;
        var links = subnav.querySelectorAll('a.sidebar-link');
        if (!links.length) return;

        var parentLabel = (ownerButton.textContent || '').replace(/\s+/g, ' ').trim();
        var title = parentLabel.replace(/\s{2,}/g, ' ').trim();
        var html = '';
        if (title) {
            html += '<div class="sidebar-flyout-title">' + title + '</div>';
        }
        html += '<ul class="sidebar-flyout-list">';
        links.forEach(function (link) {
            var href = link.getAttribute('href') || '#';
            var itemLabel = (link.textContent || '').replace(/\s+/g, ' ').trim();
            var icon = '';
            var iconNode = link.querySelector('.icon');
            if (iconNode) {
                icon = iconNode.innerHTML;
            }
            var isActive = link.classList.contains('active') ? ' active' : '';
            html += '<li><a class="sidebar-flyout-link' + isActive + '" href="' + href + '">' +
                '<span class="icon">' + icon + '</span><span>' + itemLabel + '</span></a></li>';
        });
        html += '</ul>';
        sidebarFlyout.innerHTML = html;

        var rect = ownerButton.getBoundingClientRect();
        var left = rect.right + 10;
        var top = rect.top;
        var maxTop = window.innerHeight - 20;
        sidebarFlyout.style.left = left + 'px';
        sidebarFlyout.style.top = Math.max(12, Math.min(top, maxTop - sidebarFlyout.offsetHeight)) + 'px';

        var ownerId = ownerButton.getAttribute('data-subnav') || '';
        sidebarFlyout.dataset.owner = ownerId;
        sidebarFlyout.classList.add('open');
        sidebarFlyout.setAttribute('aria-hidden', 'false');
    }

    function hydrateSidebarState() {
        if (!sidebar) return;
        var savedState = localStorage.getItem(SIDEBAR_STATE_KEY);
        var defaultState = body.getAttribute('data-sidebar-default') || 'expanded';
        var shouldCollapse = (savedState ? savedState === 'collapsed' : defaultState === 'collapsed');
        if (!isMobile()) {
            setDesktopSidebarCollapsed(shouldCollapse, false);
        } else {
            body.classList.remove('sidebar-collapsed');
            body.classList.remove('sidebar-mobile-open');
            closeSidebarFlyout();
            updateToggleAria();
        }
    }

    hydrateSidebarState();
    body.classList.add('sidebar-ready');

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function () {
            if (isMobile()) {
                body.classList.toggle('sidebar-mobile-open');
                closeSidebarFlyout();
                updateToggleAria();
                return;
            }
            var willCollapse = !body.classList.contains('sidebar-collapsed');
            setDesktopSidebarCollapsed(willCollapse, true);
        });
    }

    mobileQuery.addEventListener('change', function () {
        if (isMobile()) {
            body.classList.remove('sidebar-collapsed');
            body.classList.remove('sidebar-mobile-open');
            closeSidebarFlyout();
            updateToggleAria();
            return;
        }
        body.classList.remove('sidebar-mobile-open');
        var savedState = localStorage.getItem(SIDEBAR_STATE_KEY);
        setDesktopSidebarCollapsed(savedState === 'collapsed', false);
    });

    var toggles = document.querySelectorAll('.sidebar-toggle');
    toggles.forEach(function (btn) {
        var plainText = (btn.textContent || '').replace(/\s+/g, ' ').trim();
        if (plainText !== '') {
            btn.setAttribute('title', plainText);
        }
        btn.addEventListener('click', function () {
            if (body.classList.contains('sidebar-collapsed') && !isMobile()) {
                var targetIdCollapsed = btn.getAttribute('data-subnav');
                var subnavCollapsed = targetIdCollapsed ? document.getElementById(targetIdCollapsed) : null;
                if (!subnavCollapsed) return;
                var ownerId = sidebarFlyout ? (sidebarFlyout.dataset.owner || '') : '';
                if (sidebarFlyout && sidebarFlyout.classList.contains('open') && ownerId === targetIdCollapsed) {
                    closeSidebarFlyout();
                } else {
                    openSidebarFlyout(btn, subnavCollapsed);
                }
                return;
            }
            closeSidebarFlyout();
            var targetId = btn.getAttribute('data-subnav');
            if (!targetId) return;
            var subnav = document.getElementById(targetId);
            if (!subnav) return;
            var isOpen = subnav.classList.toggle('open');
            btn.classList.toggle('expanded', isOpen);
        });
    });

    var sidebarLinks = document.querySelectorAll('.sidebar a.sidebar-link');
    sidebarLinks.forEach(function (link) {
        var label = (link.textContent || '').replace(/\s+/g, ' ').trim();
        if (label !== '') {
            link.setAttribute('title', label);
        }
    });

    document.addEventListener('click', function (event) {
        if (!sidebarFlyout || !sidebarFlyout.classList.contains('open')) return;
        var clickedToggle = event.target.closest('.sidebar-toggle');
        if (clickedToggle) return;
        if (event.target.closest('#scpSidebarFlyout')) {
            if (event.target.closest('a.sidebar-flyout-link')) {
                closeSidebarFlyout();
            }
            return;
        }
        closeSidebarFlyout();
    });

    window.addEventListener('scroll', closeSidebarFlyout, true);
    window.addEventListener('resize', function () {
        closeSidebarFlyout();
        updateToggleAria();
    });

    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function (tooltipTriggerEl) {
            new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
});
