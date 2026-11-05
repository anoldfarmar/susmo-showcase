(() => {
  const toggle = document.querySelector('.menu-toggle');
  const nav = document.querySelector('#primary-navigation');
  function closeMenu() { toggle?.setAttribute('aria-expanded', 'false'); nav?.classList.remove('is-open'); }
  toggle?.addEventListener('click', () => {
    const open = toggle.getAttribute('aria-expanded') !== 'true';
    toggle.setAttribute('aria-expanded', String(open)); nav.classList.toggle('is-open', open);
  });
  document.addEventListener('keydown', event => { if (event.key === 'Escape' && toggle?.getAttribute('aria-expanded') === 'true') { closeMenu(); toggle.focus(); } });
  nav?.addEventListener('click', event => { if (event.target.closest('a')) closeMenu(); });
  document.addEventListener('click', event => { if (!event.target.closest('.header-inner')) closeMenu(); });
  const image = document.querySelector('#gallery-image');
  const thumbs = [...document.querySelectorAll('.gallery-thumb')];
  thumbs.forEach((button, index) => {
    button.addEventListener('click', () => {
      image.src = button.dataset.image; image.alt = button.dataset.alt; image.removeAttribute('srcset'); image.removeAttribute('sizes');
      thumbs.forEach(other => { other.classList.toggle('is-active', other === button); other.setAttribute('aria-pressed', String(other === button)); });
    });
    button.addEventListener('keydown', event => {
      if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
      event.preventDefault();
      const next = event.key === 'Home' ? 0 : event.key === 'End' ? thumbs.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + thumbs.length) % thumbs.length;
      thumbs[next].focus(); thumbs[next].click();
    });
  });
})();
