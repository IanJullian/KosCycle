document.addEventListener('DOMContentLoaded', () => {
  const preloader = document.querySelector('#site-preloader');

  const hidePreloader = () => {
    if (!preloader) return;
    preloader.classList.add('is-hidden');
    window.setTimeout(() => preloader.remove(), 650);
  };

  // DOMContentLoaded is enough when the script is loaded normally; the load
  // and timeout fallbacks prevent the screen from being stuck behind the
  // preloader when an external resource is slow or unavailable.
  hidePreloader();
  window.addEventListener('load', hidePreloader, { once: true });
  window.setTimeout(hidePreloader, 1800);

  document.querySelectorAll('a[href]').forEach((link) => {
    link.addEventListener('click', (event) => {
      /* Never intercept Bootstrap's mobile nav links/dropdowns. */
      if (link.closest('.navbar-collapse')) return;
      if (link.matches('[data-bs-toggle="dropdown"]')) return;

      let destination;
      try {
        destination = new URL(link.href, window.location.href);
      } catch (_) {
        return;
      }

      const isPageNavigation = destination.origin === window.location.origin
        && destination.pathname === window.location.pathname
        && destination.search !== window.location.search;

      if (isPageNavigation && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey) {
        event.preventDefault();
        document.body.classList.add('page-leaving');
        if (preloader) preloader.classList.remove('is-hidden');
        window.setTimeout(() => { window.location.href = link.href; }, 160);
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

  // Role-aware navigation state: setiap halaman menunjukkan tab aktif dan
  // admin otomatis menyorot menu "Kelola" ketika membuka submenu admin.
  const navLinks = document.querySelectorAll('.navbar-collapse a.nav-link, .navbar-collapse a.dropdown-item');
  const pageParam = new URLSearchParams(window.location.search).get('page') || 'home';
  let detectedRole = 'guest';

  navLinks.forEach((link) => {
    try {
      const target = new URL(link.href, window.location.href);
      const targetPage = target.searchParams.get('page') || (target.pathname === window.location.pathname ? 'home' : '');
      const isSamePage = targetPage === pageParam;

      if (isSamePage) {
        link.classList.add('nav-page-active');
        const parentDropdown = link.closest('.dropdown');
        if (parentDropdown) parentDropdown.classList.add('nav-parent-active');
      }

      if (targetPage === 'admin-dashboard') detectedRole = 'admin';
      if (targetPage === 'seller-dashboard' && detectedRole !== 'admin') detectedRole = 'seller';
      if (targetPage === 'customer-dashboard' && detectedRole === 'guest') detectedRole = 'customer';
    } catch (_) {
      // Aba navigasi yang URL-nya tidak valid tidak boleh mengganggu UI lain.
    }
  });

  document.body.classList.add('nav-role-' + detectedRole);

  // Global nav styling is injected here so it works on every role/page
  // without duplicating CSS links in the header.
  const navStyle = document.createElement('style');
  navStyle.textContent = `
    .navbar .nav-link, .navbar .dropdown-item {
      transition: color .18s ease, background .18s ease, transform .18s ease, box-shadow .18s ease;
    }
    .navbar .nav-link:hover { transform: translateY(-1px); }
    .navbar .nav-link.nav-page-active { color: var(--teal); font-weight: 700; }
    .navbar .nav-link.nav-page-active::after {
      content: ''; display: block; height: 2px; margin: 5px 4px 0;
      border-radius: 99px; background: currentColor;
    }
    .navbar .nav-parent-active > .nav-link { color: var(--teal); font-weight: 700; }
    .navbar .dropdown-item.nav-page-active {
      background: rgba(168,216,200,.22); color: var(--teal); font-weight: 700;
    }
    .nav-role-admin .nav-parent-active > .nav-link { box-shadow: 0 6px 18px rgba(40,125,108,.08); }
    .nav-role-seller .nav-page-active { text-shadow: 0 0 16px rgba(239,128,95,.12); }
    .nav-role-customer .nav-page-active { text-shadow: 0 0 16px rgba(40,125,108,.12); }
    @media (max-width: 991px) {
      .navbar .nav-link.nav-page-active::after { margin-top: 3px; }
      .navbar .nav-link.nav-page-active { background: rgba(168,216,200,.18); }
    }
  `;
  document.head.appendChild(navStyle);

  const roleChoices = document.querySelectorAll('.role-choice');
  if (roleChoices.length) {
    const syncRoleVisual = () => {
      roleChoices.forEach((choice) => {
        const input = choice.querySelector('input[name="role"]');
        choice.classList.toggle('is-selected', input?.checked === true);
      });
    };
    roleChoices.forEach((choice) => {
      const input = choice.querySelector('input[name="role"]');
      if (!input) return;
      choice.addEventListener('click', () => {
        input.checked = true;
        input.dispatchEvent(new Event('change', { bubbles: true }));
      });
      input.addEventListener('change', syncRoleVisual);
    });
    syncRoleVisual();
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
