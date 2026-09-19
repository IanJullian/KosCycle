document.addEventListener('DOMContentLoaded', () => {
  const preloader = document.querySelector('#site-preloader');

  const hidePreloader = () => {
    if (!preloader) return;
    preloader.classList.add('is-hidden');
    window.setTimeout(() => preloader.remove(), 650);
  };

  if (document.readyState === 'complete') {
    hidePreloader();
  } else {
    window.addEventListener('load', hidePreloader, { once: true });
  }

  document.querySelectorAll('a[href]').forEach((link) => {
    link.addEventListener('click', (event) => {
      const destination = new URL(link.href, window.location.href);
      const isPageNavigation = destination.origin === window.location.origin
        && destination.pathname === window.location.pathname
        && destination.search !== window.location.search;

      if (isPageNavigation && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey) {
        event.preventDefault();
        document.body.classList.add('page-leaving');
        if (preloader) preloader.classList.remove('is-hidden');
        window.setTimeout(() => { window.location.href = link.href; }, 260);
      }
    });
  });

  document.querySelectorAll('.toggle-password').forEach((button) => {
    button.addEventListener('click', () => {
      const input = button.parentElement.querySelector('input');
      input.type = input.type === 'password' ? 'text' : 'password';
      button.querySelector('i').classList.toggle('bi-eye');
      button.querySelector('i').classList.toggle('bi-eye-slash');
    });
  });

  document.querySelectorAll('a[href^="#"]').forEach((link) => {
    link.addEventListener('click', (event) => {
      const target = document.querySelector(link.getAttribute('href'));
      if (target) {
        event.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });
});