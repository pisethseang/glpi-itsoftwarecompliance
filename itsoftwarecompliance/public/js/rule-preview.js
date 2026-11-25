/* IT Software Compliance: bounded, read-only rule title search and on-demand preview. */
(() => {
  const form = document.getElementById('itc-rule-form');
  const panel = document.getElementById('itc-rule-preview');
  const output = document.getElementById('itc-preview-content');
  const trigger = document.getElementById('itc-preview-trigger');
  const input = document.getElementById('rule-pattern');
  const suggestionBox = document.getElementById('itc-rule-suggestions');
  if (!form || !panel || !output || !trigger || !input || !suggestionBox) return;
  const endpoint = panel.dataset.url;
  const mode = document.getElementById('rule-mode');
  const version = document.getElementById('rule-version');
  const department = document.getElementById('rule-dept');
  let typingTimer = null;
  let titlesRequest = null;
  let previewRequest = null;
  let currentTitlesId = 0;

  function node(tag, cls, txt) {
    const el = document.createElement(tag);
    if (cls) el.className = cls;
    if (txt !== undefined) el.textContent = String(txt);
    return el;
  }
  function params(operation) {
    return new URLSearchParams({
      operation,
      software_pattern: input.value.trim(),
      match_mode: mode.value,
      required_version: version.value.trim(),
      usercategories_id: department.value
    });
  }
  async function request(operation, signal) {
    const sep = endpoint.includes('?') ? '&' : '?';
    const response = await fetch(endpoint + sep + params(operation), {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' },
      signal
    });
    const data = await response.json();
    if (!response.ok || data.error) throw new Error(data.error || `HTTP ${response.status}`);
    return data;
  }
  function clearSuggestions() {
    suggestionBox.replaceChildren();
    suggestionBox.hidden = true;
  }
  async function searchTitles() {
    titlesRequest?.abort();
    const id = ++currentTitlesId;
    if (input.value.trim().length < 3) { clearSuggestions(); return; }
    titlesRequest = new AbortController();
    try {
      const data = await request('titles', titlesRequest.signal);
      if (id !== currentTitlesId || document.activeElement !== input) return;
      clearSuggestions();
      (data.titles || []).forEach(name => {
        const button = node('button', 'itc-v4-suggestion', name);
        button.type = 'button';
        button.setAttribute('role', 'option');
        button.addEventListener('mousedown', event => event.preventDefault());
        button.addEventListener('click', () => {
          input.value = name;
          mode.value = 'exact';
          clearSuggestions();
          input.focus();
        });
        suggestionBox.append(button);
      });
      if (data.truncated) suggestionBox.append(node('div', 'itc-v4-suggestion-foot', 'Showing first 12 matches · refine your search'));
      suggestionBox.hidden = suggestionBox.childElementCount === 0;
    } catch (e) {
      if (e.name !== 'AbortError') clearSuggestions();
    }
  }
  input.addEventListener('input', () => {
    window.clearTimeout(typingTimer);
    titlesRequest?.abort();
    clearSuggestions();
    typingTimer = window.setTimeout(searchTitles, 350);
  });
  mode.addEventListener('change', () => { if (document.activeElement === input) searchTitles(); });
  input.addEventListener('keydown', event => { if (event.key === 'Escape') clearSuggestions(); });
  input.addEventListener('blur', () => window.setTimeout(clearSuggestions, 130));

  function message(icon, heading, body) {
    output.replaceChildren();
    output.className = 'itc-v4-preview-placeholder';
    const ico = node('i', `ti ti-${icon}`);
    const strong = node('strong', '', heading);
    const para = node('p', '', body);
    output.append(ico, strong, para);
  }
  function stat(label, value, cls) {
    const box = node('div', `itc-v4-preview-stat ${cls}`);
    box.append(node('strong', '', Number(value).toLocaleString()), node('small', '', label));
    return box;
  }
  function present(data) {
    output.replaceChildren();
    output.className = 'itc-v4-preview-results';
    const st = data.stats || {};
    const tiles = node('div', 'itc-v4-preview-stats');
    tiles.append(stat('Checked', st.checked || 0, ''), stat('Passing', st.compliant || 0, 'good'), stat('Not detected', st.missing || 0, 'bad'), stat('Wrong version', st.wrong_version || 0, 'warn'));
    output.append(tiles);
    output.append(node('h4', '', 'Matching software titles'));
    if (!data.titles?.length) {
      output.append(node('p', 'itc-v4-preview-empty', 'No installed software titles matched in your active GLPI entities. Check the pattern and inventory data.'));
    } else {
      const list = node('ul', 'itc-v4-preview-titles');
      data.titles.forEach(name => {
        const li = node('li');
        li.append(node('i', 'ti ti-package'), node('span', '', name));
        list.append(li);
      });
      output.append(list);
      if (data.truncated) output.append(node('p', 'itc-v4-preview-note', 'Only the first 12 matching titles are shown. The rule evaluates all matching names.'));
    }
    if (mode.value === 'contains') {
      output.append(node('p', 'itc-v4-preview-note', 'Contains-text rules can match unrelated products. Choose an exact title when possible.'));
    }
  }
  trigger.addEventListener('click', async () => {
    clearSuggestions();
    if (input.value.trim().length < 2) {
      message('alert-circle', 'Enter a software name', 'Use at least 2 characters to preview a rule.');
      input.focus();
      return;
    }
    previewRequest?.abort();
    previewRequest = new AbortController();
    trigger.disabled = true;
    message('loader', 'Evaluating this policy...', 'Checking your current GLPI inventory. Nothing will be saved.');
    try {
      const result = await request('preview', previewRequest.signal);
      present(result);
    } catch (err) {
      if (err.name !== 'AbortError') message('alert-circle', 'Preview could not be loaded', err.message || 'Check the GLPI PHP log.');
    } finally {
      trigger.disabled = false;
    }
  });
})();
