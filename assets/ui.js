// Small UI helpers: mobile nav & dropdowns
document.addEventListener('DOMContentLoaded', () => {
  const navToggle = document.getElementById('navToggle');
  const panel = document.getElementById('mobilePanel');
  if (navToggle && panel) {
    navToggle.addEventListener('click', () => panel.classList.toggle('hidden'));
  }

  const menuBtn = document.getElementById('userMenuBtn');
  const menu = document.getElementById('userMenu');
  if (menuBtn && menu) {
    menuBtn.addEventListener('click', () => menu.classList.toggle('hidden'));
    document.addEventListener('click', (e) => {
      if (!menu.contains(e.target) && !menuBtn.contains(e.target)) menu.classList.add('hidden');
    });
  }
});
