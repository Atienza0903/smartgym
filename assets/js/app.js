// Live capacity polling for the topbar pill and any [data-gauge] element.
(function () {
    const pills = document.querySelectorAll('[data-capacity-pill]');
    const gauges = document.querySelectorAll('[data-gauge]');
    const colors = { success: '#198754', info: '#0dcaf0', warning: '#ffc107', danger: '#d71920' };

    function render(d) {
        document.querySelectorAll('[data-cap-inside]').forEach(el => el.textContent = d.inside);
        document.querySelectorAll('[data-cap-max]').forEach(el => el.textContent = d.capacity);
        document.querySelectorAll('[data-cap-label]').forEach(el => {
            el.textContent = d.label;
            el.className = 'badge bg-' + d.color + (['warning', 'info'].includes(d.color) ? ' text-dark' : '');
        });
        document.querySelectorAll('[data-cap-percent]').forEach(el => el.textContent = d.percent + '%');
        pills.forEach(p => p.classList.toggle('full', d.percent >= 100));
        gauges.forEach(g => {
            const fg = g.querySelector('.gauge-fg');
            const r = fg.r.baseVal.value, c = 2 * Math.PI * r;
            fg.style.strokeDasharray = c;
            fg.style.strokeDashoffset = c * (1 - d.percent / 100);
            fg.style.stroke = colors[d.color] || colors.success;
        });
    }

    function poll() {
        fetch('api/capacity.php', { cache: 'no-store' }).then(r => r.json()).then(render).catch(() => {});
    }

    if (pills.length || gauges.length) {
        poll();
        setInterval(poll, 5000);
    }
    window.SmartGym = { refreshCapacity: poll };
})();

// Confirm dialogs for destructive actions: <form data-confirm="Are you sure?">
document.addEventListener('submit', function (e) {
    const msg = e.target.getAttribute('data-confirm');
    if (msg && !confirm(msg)) e.preventDefault();
});
