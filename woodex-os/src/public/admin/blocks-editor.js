/* Woodex OS — page block editor (vanilla, no build step).
 *
 * Reads the block schema + initial blocks from JSON script tags, renders typed
 * fields (including repeaters for lists), and keeps a hidden textarea in sync
 * so the form works without any client-side framework.
 */
(function () {
  'use strict';

  const schemaEl = document.getElementById('block-schema');
  const dataEl = document.getElementById('blocks-data');
  const host = document.getElementById('blocks-editor');
  if (!schemaEl || !host) return;

  const schema = JSON.parse(schemaEl.textContent || '{}');
  const types = schema.types || {};
  const arrayFields = schema.arrays || {};
  let data = [];
  try { data = JSON.parse(dataEl ? dataEl.textContent : '[]'); } catch { data = []; }
  if (!Array.isArray(data)) data = [];

  const LONG_TEXT = ['title', 'html', 'body', 'text', 'copy', 'summary', 'intro', 'note', 'a', 'excerpt', 'description', 'q'];
  const TEXTAREA_ROWS = { html: 8, body: 8, title: 3 };

  const output = document.createElement('textarea');
  output.name = 'blocks';
  output.id = 'blocks-json';
  output.className = 'sr-only';
  output.setAttribute('aria-hidden', 'true');
  output.tabIndex = -1;
  host.insertAdjacentElement('afterend', output);

  const list = document.createElement('div');
  list.className = 'blocks';
  host.appendChild(list);

  const toolbar = document.createElement('div');
  toolbar.className = 'btn-row';
  toolbar.style.marginTop = '14px';
  const typeSelect = document.createElement('select');
  typeSelect.id = 'block-type-select';
  typeSelect.style.maxWidth = '260px';
  Object.entries(types).forEach(([key, meta]) => {
    const option = document.createElement('option');
    option.value = key;
    option.textContent = meta.label || key;
    typeSelect.appendChild(option);
  });
  const addButton = document.createElement('button');
  addButton.type = 'button';
  addButton.className = 'btn secondary';
  addButton.textContent = '+ Add block';
  addButton.addEventListener('click', () => {
    const type = typeSelect.value;
    const defaults = JSON.parse(JSON.stringify((types[type] || {}).defaults || {}));
    data.push({ type: type, id: Math.random().toString(36).slice(2, 10), ...defaults });
    render();
  });
  toolbar.appendChild(typeSelect);
  toolbar.appendChild(addButton);
  host.appendChild(toolbar);

  function sync() {
    output.value = JSON.stringify(data);
  }

  function labelFor(field) {
    return field
      .replace(/([A-Z])/g, ' $1')
      .replace(/^./, (c) => c.toUpperCase())
      .replace(/\bHtml\b/, 'HTML')
      .replace(/\bUrl\b/, 'URL')
      .replace(/\bHref\b/, 'Link');
  }

  function fieldElement(field, value, onChange) {
    const wrapper = document.createElement('div');
    wrapper.className = 'field';
    const id = 'f-' + Math.random().toString(36).slice(2, 9);
    const label = document.createElement('label');
    label.setAttribute('for', id);
    label.textContent = labelFor(field);
    wrapper.appendChild(label);

    let input;
    if (typeof value === 'boolean') {
      wrapper.className = 'field check';
      input = document.createElement('input');
      input.type = 'checkbox';
      input.checked = Boolean(value);
      input.addEventListener('change', () => onChange(input.checked));
      const span = document.createElement('span');
      span.textContent = labelFor(field);
      wrapper.textContent = '';
      wrapper.appendChild(input);
      wrapper.appendChild(span);
      return wrapper;
    }

    if (typeof value === 'number') {
      input = document.createElement('input');
      input.type = 'number';
      input.value = value;
      input.addEventListener('input', () => onChange(Number(input.value) || 0));
    } else if (LONG_TEXT.indexOf(field) > -1 || String(value || '').length > 90) {
      input = document.createElement('textarea');
      input.value = value == null ? '' : String(value);
      input.rows = TEXTAREA_ROWS[field] || 3;
      if (field === 'html' || field === 'body') input.className = 'mono';
      input.addEventListener('input', () => onChange(input.value));
    } else {
      input = document.createElement('input');
      input.type = 'text';
      input.value = value == null ? '' : String(value);
      input.addEventListener('input', () => onChange(input.value));
    }
    input.id = id;
    wrapper.appendChild(input);
    if (field === 'title' || field === 'html' || field === 'body') {
      const hint = document.createElement('span');
      hint.className = 'hint';
      hint.textContent = field === 'title' ? 'Basic HTML allowed, e.g. <br> for a line break.' : 'HTML allowed — scripts and event handlers are stripped on save.';
      wrapper.appendChild(hint);
    }
    return wrapper;
  }

  function addRepeater(parent, block, blockIndex, field) {
    const itemFields = (arrayFields[block.type] || {})[field];
    if (!itemFields) return;
    if (!Array.isArray(block[field])) block[field] = [];

    const wrap = document.createElement('div');
    wrap.className = 'field';
    const title = document.createElement('span');
    title.className = 'label-text';
    title.textContent = labelFor(field);
    wrap.appendChild(title);

    const items = document.createElement('div');
    items.className = 'repeater';

    block[field].forEach((item, itemIndex) => {
      const card = document.createElement('div');
      card.className = 'repeater-item';
      const head = document.createElement('div');
      head.className = 'repeater-item-head';
      const name = document.createElement('span');
      name.textContent = labelFor(field) + ' ' + (itemIndex + 1);
      const remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'btn danger small';
      remove.textContent = 'Remove';
      remove.addEventListener('click', () => {
        data[blockIndex][field].splice(itemIndex, 1);
        render();
      });
      head.appendChild(name);
      head.appendChild(remove);
      card.appendChild(head);

      itemFields.forEach((itemField) => {
        card.appendChild(fieldElement(itemField, item[itemField], (val) => {
          data[blockIndex][field][itemIndex][itemField] = val;
          sync();
        }));
      });
      items.appendChild(card);
    });

    wrap.appendChild(items);
    const add = document.createElement('button');
    add.type = 'button';
    add.className = 'btn secondary small repeater-add';
    add.textContent = '+ Add ' + labelFor(field).toLowerCase().replace(/s$/, '');
    add.addEventListener('click', () => {
      const blank = {};
      itemFields.forEach((f) => { blank[f] = ''; });
      data[blockIndex][field].push(blank);
      render();
    });
    wrap.appendChild(add);
    parent.appendChild(wrap);
  }

  function move(index, delta) {
    const target = index + delta;
    if (target < 0 || target >= data.length) return;
    const [item] = data.splice(index, 1);
    data.splice(target, 0, item);
    render();
  }

  function render() {
    list.textContent = '';
    if (!data.length) {
      const empty = document.createElement('div');
      empty.className = 'empty-state';
      empty.textContent = 'No blocks yet. Choose a block type below to start building this page.';
      list.appendChild(empty);
    }

    data.forEach((block, index) => {
      const meta = types[block.type] || { label: block.type, fields: Object.keys(block), defaults: {} };
      const card = document.createElement('section');
      card.className = 'block-card';

      const header = document.createElement('header');
      const nameWrap = document.createElement('div');
      const name = document.createElement('div');
      name.className = 'block-name';
      name.textContent = (index + 1) + '. ' + (meta.label || block.type);
      const type = document.createElement('div');
      type.className = 'block-type';
      type.textContent = block.type;
      nameWrap.appendChild(name);
      nameWrap.appendChild(type);

      const tools = document.createElement('div');
      tools.className = 'block-tools';
      [['↑', -1, 'Move up'], ['↓', 1, 'Move down']].forEach(([glyph, delta, title]) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = glyph;
        button.title = title;
        button.setAttribute('aria-label', title);
        button.addEventListener('click', () => move(index, delta));
        tools.appendChild(button);
      });
      const remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'remove';
      remove.textContent = '×';
      remove.title = 'Delete block';
      remove.setAttribute('aria-label', 'Delete block');
      remove.addEventListener('click', () => {
        if (!window.confirm('Delete this block?')) return;
        data.splice(index, 1);
        render();
      });
      tools.appendChild(remove);

      header.appendChild(nameWrap);
      header.appendChild(tools);
      card.appendChild(header);

      const body = document.createElement('div');
      body.className = 'block-body';
      const fields = meta.fields || Object.keys(block).filter((k) => !['type', 'id'].includes(k));
      fields.forEach((field) => {
        if (Array.isArray(block[field])) {
          addRepeater(body, block, index, field);
        } else if (block[field] === undefined) {
          // initialise missing scalar from defaults
          const defaults = meta.defaults || {};
          block[field] = defaults[field] !== undefined ? defaults[field] : '';
          if (Array.isArray(block[field])) { addRepeater(body, block, index, field); return; }
          body.appendChild(fieldElement(field, block[field], (val) => { data[index][field] = val; sync(); }));
        } else {
          body.appendChild(fieldElement(field, block[field], (val) => { data[index][field] = val; sync(); }));
        }
      });
      card.appendChild(body);
      list.appendChild(card);
    });
    sync();
  }

  render();

  /* Keep plain JSON textareas tidy for gallery/facts/deliverables fields. */
  document.querySelectorAll('textarea[data-json]').forEach((area) => {
    area.addEventListener('blur', () => {
      try {
        area.value = JSON.stringify(JSON.parse(area.value || '[]'), null, 2);
      } catch { /* leave invalid JSON for the server to report */ }
    });
  });

  /* Confirm destructive actions. */
  document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (!window.confirm(form.getAttribute('data-confirm'))) event.preventDefault();
    });
  });

  /* Copy buttons (API tokens, media paths). */
  document.querySelectorAll('[data-copy]').forEach((button) => {
    button.addEventListener('click', async () => {
      const value = button.getAttribute('data-copy');
      try {
        await navigator.clipboard.writeText(value);
        const original = button.textContent;
        button.textContent = 'Copied';
        setTimeout(() => { button.textContent = original; }, 1600);
      } catch {
        window.prompt('Copy this value', value);
      }
    });
  });
})();
