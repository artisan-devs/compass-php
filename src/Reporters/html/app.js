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

  // Code highlight theme (independent of light/dark mode).
  // Stored as one CSS class on <html>, e.g. "highlight-monokai". "default" stores no class
  // so the theme-aware --tok-* variables from :root / :root.theme-dark stay in effect.
  const HIGHLIGHT_KEYS = ['default', 'github-light', 'monokai', 'monokai-dimmed', 'dracula', 'solarized-light', 'nord'];
  const applyHighlight = (key) => {
    HIGHLIGHT_KEYS.forEach((k) => html.classList.remove('highlight-' + k));
    if (HIGHLIGHT_KEYS.includes(key) && key !== 'default') {
      html.classList.add('highlight-' + key);
    }
  };
  const storedHighlight = localStorage.getItem('compass-highlight') || 'default';
  applyHighlight(storedHighlight);
  document.querySelectorAll('select[data-highlight-theme]').forEach((sel) => {
    sel.value = HIGHLIGHT_KEYS.includes(storedHighlight) ? storedHighlight : 'default';
    sel.addEventListener('change', () => {
      const next = sel.value;
      applyHighlight(next);
      localStorage.setItem('compass-highlight', next);
    });
  });

  // Combined search-input + category-chip filter.
  // A row is visible only when it passes BOTH filters. We track per-table search
  // queries plus a global active-category set, and recompute on every change.
  const filterableTables = new Set();
  const tableQueries = new Map();
  document.querySelectorAll('input[data-filter-target]').forEach((input) => {
    const target = document.querySelector(input.dataset.filterTarget);
    if (!target) return;
    filterableTables.add(target);
    tableQueries.set(target, '');
  });

  const categoryCheckboxes = Array.from(document.querySelectorAll('[data-category-filter]'));
  const allCategories = [...new Set(categoryCheckboxes.map((cb) => cb.dataset.categoryFilter))];
  const CATEGORY_KEY = 'compass-categories';
  const restoredCategories = (() => {
    try {
      const raw = localStorage.getItem(CATEGORY_KEY);
      if (!raw) return null;
      const parsed = JSON.parse(raw);
      if (!Array.isArray(parsed)) return null;
      const filtered = parsed.filter((c) => allCategories.includes(c));
      return filtered.length > 0 ? filtered : null;
    } catch (_e) {
      return null;
    }
  })();
  const activeCategories = new Set(restoredCategories ?? allCategories);
  const categoryFilterActive = categoryCheckboxes.length > 0;

  // "Hide empty" toggles — buttons with data-hide-empty + aria-pressed.
  // When pressed, rows with data-violations="0" are hidden.
  const HIDE_EMPTY_KEY = 'compass-hide-empty';
  const hideEmptyButtons = Array.from(document.querySelectorAll('[data-hide-empty]'));
  let hideEmpty = (() => {
    try { return localStorage.getItem(HIDE_EMPTY_KEY) === '1'; } catch (_e) { return false; }
  })();

  const recomputeVisibility = () => {
    filterableTables.forEach((table) => {
      const q = (tableQueries.get(table) || '').toLowerCase();
      table.querySelectorAll('tbody tr').forEach((tr) => {
        const text = tr.textContent.toLowerCase();
        const passesQuery = q === '' || text.includes(q);

        let passesCategory = true;
        if (categoryFilterActive) {
          if (tr.dataset.category !== undefined) {
            passesCategory = activeCategories.has(tr.dataset.category);
          } else if (tr.dataset.categories !== undefined) {
            const cats = tr.dataset.categories.split(/\s+/).filter(Boolean);
            passesCategory = cats.some((c) => activeCategories.has(c));
          }
        }

        let passesEmpty = true;
        if (hideEmpty && tr.dataset.violations !== undefined) {
          passesEmpty = parseInt(tr.dataset.violations, 10) > 0;
        }

        tr.hidden = !(passesQuery && passesCategory && passesEmpty);
      });
    });
  };

  document.querySelectorAll('input[data-filter-target]').forEach((input) => {
    const target = document.querySelector(input.dataset.filterTarget);
    if (!target) return;
    input.addEventListener('input', () => {
      tableQueries.set(target, input.value.trim());
      recomputeVisibility();
    });
  });

  if (categoryFilterActive) {
    const syncChip = (cb) => {
      const chip = cb.closest('[data-category-chip]');
      if (chip) chip.classList.toggle('category-chip--off', !cb.checked);
    };
    categoryCheckboxes.forEach((cb) => {
      cb.checked = activeCategories.has(cb.dataset.categoryFilter);
      syncChip(cb);
      cb.addEventListener('change', () => {
        const cat = cb.dataset.categoryFilter;
        if (cb.checked) activeCategories.add(cat);
        else activeCategories.delete(cat);
        syncChip(cb);
        try {
          localStorage.setItem(CATEGORY_KEY, JSON.stringify([...activeCategories]));
        } catch (_e) { /* storage full / blocked — ignore */ }
        recomputeVisibility();
      });
    });
  }

  if (hideEmptyButtons.length > 0) {
    hideEmptyButtons.forEach((btn) => {
      btn.setAttribute('aria-pressed', hideEmpty ? 'true' : 'false');
      btn.addEventListener('click', () => {
        hideEmpty = !hideEmpty;
        hideEmptyButtons.forEach((b) => b.setAttribute('aria-pressed', hideEmpty ? 'true' : 'false'));
        try { localStorage.setItem(HIDE_EMPTY_KEY, hideEmpty ? '1' : '0'); } catch (_e) { /* ignore */ }
        recomputeVisibility();
      });
    });
  }

  // Apply initial filtering once at the end so all dimensions are accounted for.
  if (categoryFilterActive || hideEmptyButtons.length > 0 || filterableTables.size > 0) {
    recomputeVisibility();
  }

  // Tabs (e.g. By rule / By file on the index page).
  // Multiple tab groups can coexist on a page if each tablist has its own data-tab-group.
  const tabGroups = new Map();
  document.querySelectorAll('[data-tab-trigger]').forEach((tab) => {
    const group = tab.closest('[role="tablist"]') || document;
    if (!tabGroups.has(group)) tabGroups.set(group, []);
    tabGroups.get(group).push(tab);
  });
  if (tabGroups.size > 0) {
    const STORAGE_KEY = 'compass-tab';
    const stored = localStorage.getItem(STORAGE_KEY);
    tabGroups.forEach((tabs) => {
      const activate = (key) => {
        let matched = false;
        tabs.forEach((t) => {
          const on = t.dataset.tabTrigger === key;
          if (on) matched = true;
          t.setAttribute('aria-selected', on ? 'true' : 'false');
          t.tabIndex = on ? 0 : -1;
          const panel = document.getElementById(t.getAttribute('aria-controls'));
          if (panel) panel.hidden = !on;
        });
        return matched;
      };
      const initial = stored && activate(stored) ? stored : tabs[0].dataset.tabTrigger;
      activate(initial);
      tabs.forEach((t) => {
        t.addEventListener('click', () => {
          const key = t.dataset.tabTrigger;
          activate(key);
          localStorage.setItem(STORAGE_KEY, key);
        });
      });
    });
  }

  document.querySelectorAll('[data-copy-prompt]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const text = btn.dataset.copyPrompt || '';
      const label = btn.dataset.copyLabel || btn.textContent;
      const flash = (next, cls) => {
        btn.textContent = next;
        btn.classList.toggle('copy-btn--success', cls === 'success');
        btn.classList.toggle('copy-btn--error', cls === 'error');
        setTimeout(() => {
          btn.textContent = label;
          btn.classList.remove('copy-btn--success', 'copy-btn--error');
        }, 1600);
      };
      try {
        if (navigator.clipboard && window.isSecureContext) {
          await navigator.clipboard.writeText(text);
        } else {
          const ta = document.createElement('textarea');
          ta.value = text;
          ta.setAttribute('readonly', '');
          ta.style.position = 'fixed';
          ta.style.opacity = '0';
          document.body.appendChild(ta);
          ta.select();
          const ok = document.execCommand('copy');
          document.body.removeChild(ta);
          if (!ok) throw new Error('execCommand copy returned false');
        }
        flash('Copied!', 'success');
      } catch (_e) {
        flash('Copy failed', 'error');
      }
    });
  });
})();
