window.chessCoachCsrfHeaders = function(headers) {
  const nextHeaders = Object.assign({}, headers || {});
  const meta = document.querySelector('meta[name="csrf-token"]');
  const token = window.CHESS_COACH_CSRF || (meta ? meta.getAttribute('content') : '');
  if (token) nextHeaders['X-CSRF-Token'] = token;
  return nextHeaders;
};

function initializeNovaStreakCore() {
  const core = document.querySelector('.nova-streak-core[data-streak-state="active"]');
  if (!core) return;
  const image = core.querySelector('img');
  const date = core.dataset.streakDate || '';
  const storageKey = `chess-coach-nova-streak-active-${date}`;
  let alreadyActivated = false;
  try {
    alreadyActivated = window.localStorage.getItem(storageKey) === '1';
  } catch (error) {
    alreadyActivated = true;
  }
  if (alreadyActivated) {
    image.src = 'assets/nova/core-streak/nova-core-glow-loop.svg';
    return;
  }
  image.src = 'assets/nova/core-streak/nova-core-turn-on.svg';
  window.setTimeout(() => {
    image.src = 'assets/nova/core-streak/nova-core-glow-loop.svg';
    try { window.localStorage.setItem(storageKey, '1'); } catch (error) {}
  }, 1500);
}

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

initializeNovaStreakCore();
