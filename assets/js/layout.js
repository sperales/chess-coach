document.addEventListener('click', (ev) => {
  const btn = document.getElementById('menuBtn');
  const menu = document.getElementById('userMenu');
  if (!btn || !menu) return;
  if (btn.contains(ev.target)) {
    const open = !menu.classList.contains('open');
    menu.classList.toggle('open', open);
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    return;
  }
  if (!menu.contains(ev.target)) {
    menu.classList.remove('open');
    btn.setAttribute('aria-expanded', 'false');
  }
});
