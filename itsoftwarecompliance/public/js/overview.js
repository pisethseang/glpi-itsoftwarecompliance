/* Local filter: all active rule aggregates are already on the page; no additional SQL. */
(() => {
  const field = document.getElementById('itc-v4-coverage-search');
  const rows = Array.from(document.querySelectorAll('#itc-v4-coverage-results .itc-v4-coverage-row'));
  const empty = document.getElementById('itc-v4-no-results');
  if (!field || !rows.length) return;
  field.addEventListener('input', () => {
    const q = field.value.trim().toLocaleLowerCase();
    let shown = 0;
    rows.forEach(row => {
      const visible = (row.dataset.ruleName || '').includes(q);
      row.hidden = !visible;
      if (visible) shown++;
    });
    if (empty) empty.hidden = shown > 0;
  });
})();
