document.addEventListener('DOMContentLoaded', () => {
  const preloader = document.querySelector('#site-preloader');

  const hidePreloader = () => {
    if (!preloader) return;
    preloader.classList.add('is-hidden');
    document.body.classList.remove('page-leaving');
    window.setTimeout(() => {
      if (preloader.isConnected && preloader.classList.contains('is-hidden')) preloader.remove();
    }, 650);
  };

  // Jangan bergantung pada window.load karena font/CDN/gambar dapat lambat.
  hidePreloader();
  window.addEventListener('pageshow', hidePreloader, { once: true });
  window.setTimeout(hidePreloader, 1800);

  document.querySelectorAll('a[href]').forEach((link) => {
    link.addEventListener('click', (event) => {
      if (link.matches('[data-bs-toggle="dropdown"]')) return;
      if (link.dataset.noPreloader === '1') return;

      let destination;
      try {
        destination = new URL(link.href, window.location.href);
      } catch (_) {
        return;
      }

      // Chat/pengaduan sengaja tidak diberi full-screen overlay. Ini mencegah
      // pengalaman "loading terus" ketika hosting/DB chat sedang lambat.
      if (destination.searchParams.get('page') === 'chat') return;

      const isSameOrigin = destination.origin === window.location.origin;
      const isInternalPage = isSameOrigin && destination.pathname === window.location.pathname;
      const changesPage = destination.search !== window.location.search || destination.hash !== window.location.hash;

      if (isInternalPage && changesPage && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey) {
        event.preventDefault();
        document.body.classList.add('page-leaving');

        const overlay = document.querySelector('#site-preloader');
        if (overlay) overlay.classList.remove('is-hidden');

        // Safety fallback: overlay tidak boleh memblokir browser tanpa batas.
        window.setTimeout(() => document.body.classList.remove('page-leaving'), 2200);
        window.setTimeout(() => { window.location.assign(link.href); }, 100);
      }
    });
  });

  document.querySelectorAll('.toggle-password').forEach((button) => {
    button.addEventListener('click', () => {
      const input = button.parentElement?.querySelector('input');
      if (!input) return;
      input.type = input.type === 'password' ? 'text' : 'password';
      const icon = button.querySelector('i');
      if (icon) {
        icon.classList.toggle('bi-eye');
        icon.classList.toggle('bi-eye-slash');
      }
    });
  });

  document.querySelectorAll('.navbar-collapse .nav-link, .navbar-collapse .dropdown-item').forEach((link) => {
    link.addEventListener('click', () => {
      const menu = link.closest('.navbar-collapse');
      if (menu?.classList.contains('show') && window.bootstrap) {
        window.bootstrap.Collapse.getOrCreateInstance(menu).hide();
      }
    });
  });

  const navLinks = document.querySelectorAll('.navbar-collapse a.nav-link, .navbar-collapse a.dropdown-item');
  const pageParam = new URLSearchParams(window.location.search).get('page') || 'home';
  let detectedRole = 'guest';

  navLinks.forEach((link) => {
    try {
      const target = new URL(link.href, window.location.href);
      const targetPage = target.searchParams.get('page') || (target.pathname === window.location.pathname ? 'home' : '');
      if (targetPage === pageParam) {
        link.classList.add('nav-page-active');
        const parentDropdown = link.closest('.dropdown');
        if (parentDropdown) parentDropdown.classList.add('nav-parent-active');
      }
      if (targetPage === 'admin-dashboard') detectedRole = 'admin';
      if (targetPage === 'seller-dashboard' && detectedRole !== 'admin') detectedRole = 'seller';
      if (targetPage === 'customer-dashboard' && detectedRole === 'guest') detectedRole = 'customer';
    } catch (_) {}
  });
  document.body.classList.add('nav-role-' + detectedRole);

  const navStyle = document.createElement('style');
  navStyle.textContent = `
    .navbar .nav-link, .navbar .dropdown-item { transition: color .18s ease, background .18s ease, transform .18s ease; }
    .navbar .nav-link:hover { transform: translateY(-1px); }
    .navbar .nav-link.nav-page-active { color: var(--teal); font-weight: 700; }
    .navbar .nav-link.nav-page-active::after { content:'';display:block;height:2px;margin:5px 4px 0;border-radius:99px;background:currentColor; }
    .navbar .nav-parent-active > .nav-link { color: var(--teal); font-weight:700; }
    .navbar .dropdown-item.nav-page-active { background:rgba(168,216,200,.22);color:var(--teal);font-weight:700; }
    @media(max-width:991px){.navbar .nav-link.nav-page-active::after{margin-top:3px}.navbar .nav-link.nav-page-active{background:rgba(168,216,200,.18)}}
  `;
  document.head.appendChild(navStyle);

  const roleChoices = document.querySelectorAll('.role-choice');
  if (roleChoices.length) {
    const sync = () => roleChoices.forEach((choice) => {
      const input = choice.querySelector('input[name="role"]');
      choice.classList.toggle('is-selected', input?.checked === true);
    });
    roleChoices.forEach((choice) => {
      const input = choice.querySelector('input[name="role"]');
      if (!input) return;
      choice.addEventListener('click', () => { input.checked = true; input.dispatchEvent(new Event('change', { bubbles: true })); });
      input.addEventListener('change', sync);
    });
    sync();
  }

  document.querySelectorAll('a[href^="#"]').forEach((link) => {
    link.addEventListener('click', (event) => {
      const selector = link.getAttribute('href');
      if (!selector || selector === '#') return;
      const target = document.querySelector(selector);
      if (target) {
        event.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });
});
