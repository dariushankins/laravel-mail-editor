(() => {
  const preference = () => { try { return localStorage.getItem('maileclipse-theme') || 'system'; } catch { return 'system'; } };
  const media = window.matchMedia('(prefers-color-scheme: dark)');
  const applyTheme = (value) => {
    document.documentElement.dataset.bsTheme = value === 'system' ? (media.matches ? 'dark' : 'light') : value;
  };
  applyTheme(preference());
  media.addEventListener('change', () => applyTheme(preference()));
  document.addEventListener('DOMContentLoaded', () => {
    const theme = document.querySelector('[data-theme-select]');
    if (theme) {
      theme.value = preference();
      theme.addEventListener('change', () => {
        try { localStorage.setItem('maileclipse-theme', theme.value); } catch {}
        applyTheme(theme.value);
      });
    }
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': token } });
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
    axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
    document.querySelectorAll('[data-bs-toggle="popover"]').forEach(el => new bootstrap.Popover(el));
    document.querySelectorAll('[maileclipse-data-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
    document.querySelectorAll('img').forEach(el => el.loading = 'lazy');
    document.querySelectorAll('.table-action').forEach(el => {
      if (!el.getAttribute('aria-label')) el.setAttribute('aria-label', el.classList.contains('remove-item') ? 'Delete item' : 'Open item');
    });
    const search = document.querySelector('[data-list-search]');
    const rows = [...document.querySelectorAll('#mailables_list tbody tr, #templates_list tbody tr')];
    search?.addEventListener('input', () => {
      const query = search.value.trim().toLocaleLowerCase();
      rows.forEach(row => row.hidden = !row.textContent.toLocaleLowerCase().includes(query));
      const empty = document.querySelector('[data-search-empty]');
      if (empty) empty.hidden = rows.some(row => !row.hidden);
    });
    document.addEventListener('keydown', event => {
      if (event.target.matches('.template-item[role="button"]') && ['Enter', ' '].includes(event.key)) { event.preventDefault(); event.target.click(); }
    });
    document.addEventListener('shown.bs.tab', () => {
      document.querySelectorAll('.CodeMirror').forEach(el => el.CodeMirror?.refresh());
    });
  });
})();
