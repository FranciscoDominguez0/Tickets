window.addEventListener('load', function () {
    var all = document.getElementById('checkAll');
    if (all) {
        all.addEventListener('change', function () {
            document.querySelectorAll('.row-check').forEach(function (cb) {
                cb.checked = all.checked;
            });
        });
    }

    function b64decode(v) {
        try {
            var s = atob(v || '');
            try {
                return decodeURIComponent(escape(s));
            } catch (e2) {
                return s;
            }
        } catch (e) {
            return '';
        }
    }

    var tip = document.createElement('div');
    tip.id = 'log-tip';
    tip.style.position = 'fixed';
    tip.style.zIndex = '2000';
    tip.style.maxWidth = '520px';
    tip.style.background = '#fff';
    tip.style.border = '1px solid rgba(148,163,184,0.7)';
    tip.style.borderRadius = '10px';
    tip.style.boxShadow = '0 14px 34px rgba(15,23,42,0.18)';
    tip.style.padding = '10px 12px';
    tip.style.display = 'none';
    tip.style.pointerEvents = 'none';
    tip.innerHTML = '<div id="log-tip-title" style="font-weight:900; color:#0f172a; margin-bottom:6px;"></div>'
        + '<div id="log-tip-body" style="white-space:pre-wrap; color:#334155; font-size:0.92rem; line-height:1.35;"></div>';
    document.body.appendChild(tip);

    function showTip(el) {
        var t = b64decode(el.getAttribute('data-pop-title'));
        var b = b64decode(el.getAttribute('data-pop-body'));
        var tEl = document.getElementById('log-tip-title');
        var bEl = document.getElementById('log-tip-body');
        if (tEl) tEl.textContent = t || 'Registro';
        if (bEl) bEl.textContent = b || '';
        tip.style.display = 'block';
    }

    function hideTip() {
        tip.style.display = 'none';
    }

    function placeTipNear(el) {
        if (tip.style.display === 'none') return;
        var pad = 12;
        var r = el.getBoundingClientRect();
        var rect = tip.getBoundingClientRect();
        var x = r.right + pad;
        var y = r.top;
        if (x + rect.width > window.innerWidth - 10) x = Math.max(10, r.left - rect.width - pad);
        if (y + rect.height > window.innerHeight - 10) y = Math.max(10, window.innerHeight - rect.height - 10);
        tip.style.left = x + 'px';
        tip.style.top = y + 'px';
    }

    document.querySelectorAll('a.log-pop').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
        });
        el.addEventListener('mouseenter', function () {
            showTip(el);
            placeTipNear(el);
        });
        el.addEventListener('mouseleave', function () {
            hideTip();
        });
        el.addEventListener('focus', function () {
            showTip(el);
            placeTipNear(el);
        });
        el.addEventListener('blur', function () {
            hideTip();
        });
    });

    window.addEventListener('scroll', function () {
        var active = document.activeElement;
        if (active && active.classList && active.classList.contains('log-pop') && tip.style.display !== 'none') {
            placeTipNear(active);
        }
    }, { passive: true });
});
