(() => {
  const html = document.documentElement;

  const apply = (mode) => {
    html.classList.remove('theme-light', 'theme-dark');
    if (mode === 'light' || mode === 'dark') {
      html.classList.add('theme-' + mode);
    }
  };

  apply(localStorage.getItem('compass-theme'));

  const isDark = () => {
    if (html.classList.contains('theme-dark')) return true;
    if (html.classList.contains('theme-light')) return false;
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  };

  document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const next = isDark() ? 'light' : 'dark';
      apply(next);
      localStorage.setItem('compass-theme', next);
    });
  });

  document.querySelectorAll('input[data-filter-target]').forEach((input) => {
    const target = document.querySelector(input.dataset.filterTarget);
    if (!target) return;
    const rows = target.querySelectorAll('tbody tr');
    input.addEventListener('input', () => {
      const q = input.value.trim().toLowerCase();
      rows.forEach((tr) => {
        if (q === '') {
          tr.hidden = false;
          return;
        }
        tr.hidden = !tr.textContent.toLowerCase().includes(q);
      });
    });
  });
})();
