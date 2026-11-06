const sidebar = document.getElementById('sidebar-nav');
const navLinks = sidebar?.querySelector('.nav-links');
const toggle = document.getElementById('sidebar-toggle');

if (sidebar && navLinks && toggle) {
    const mobileQuery = window.matchMedia('(max-width: 768px)');

    function setSidebarHidden(hidden) {
        document.body.classList.toggle('sidebar-hidden', hidden);
        navLinks.inert = hidden;
        navLinks.setAttribute('aria-hidden', String(hidden));
        toggle.setAttribute('aria-expanded', String(!hidden));
        toggle.setAttribute('aria-label', hidden ? 'Show navigation' : 'Hide navigation');
        toggle.querySelector('i').className = hidden ? 'bi bi-list' : 'bi bi-x-lg';
    }

    setSidebarHidden(mobileQuery.matches);

    mobileQuery.addEventListener('change', (event) => {
        setSidebarHidden(event.matches);
    });

    toggle.addEventListener('click', () => {
        setSidebarHidden(!document.body.classList.contains('sidebar-hidden'));
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !document.body.classList.contains('sidebar-hidden')) {
            setSidebarHidden(true);
            toggle.focus();
        }
    });
}

document.querySelectorAll('.toast-notification').forEach((toast) => {
    let hideTimeout;

    function dismissToast() {
        clearTimeout(hideTimeout);
        toast.classList.add('toast-hiding');
        window.setTimeout(() => {
            toast.hidden = true;
        }, 250);
    }

    toast.querySelector('.toast-close')?.addEventListener('click', dismissToast);
    hideTimeout = window.setTimeout(dismissToast, 4500);
});
