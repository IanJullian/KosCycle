document.addEventListener('DOMContentLoaded', () => {
  const preloader = document.querySelector('#site-preloader');

  const hidePreloader = () => {
    if (!preloader) return;
    preloader.classList.add('is-hidden');
    window.setTimeout(() => preloader.remove(), 650);
  };

  if (document.readyState === 'complete') hidePreloader();
  else window.addEventListener('load', hidePreloader, { once: true });

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
      if (!input) return;
      input.type = input.type === 'password' ? 'text' : 'password';
      const icon = button.querySelector('i');
      if (icon) {
        icon.classList.toggle('bi-eye');
        icon.classList.toggle('bi-eye-slash');
      }
    });
  });

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
      choice.addEventListener('click', () => { input.checked = true; input.dispatchEvent(new Event('change', { bubbles: true })); });
      input.addEventListener('change', syncRoleVisual);
    });
    syncRoleVisual();
  }

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
