'use strict';
(() => {
  const el = id => document.getElementById(id);
  let merchant = null, canWrite = false, draft = null, nextCursor = null, epoch = 0, listSequence = 0, editorSequence = 0;
  const freshId = () => crypto.randomUUID().replaceAll('-', '');
  let creationId = freshId();
  const fields = ['reference', 'customerName', 'customerAddress', 'customerTaxId', 'currency'];
  const sameContext = token => token === epoch;

  function resetEditor() {
    ++editorSequence;
    draft = null; creationId = freshId(); el('draft-form').reset(); el('draft-lines').replaceChildren();
    el('draft-editor').hidden = true; el('draft-totals').textContent = ''; el('draft-revision').textContent = '';
    el('draft-archive').hidden = true;
  }
  function lineInput(labelText, name, value, options = {}) {
    const label = document.createElement('label'); label.textContent = labelText;
    const input = document.createElement('input'); input.name = name; input.value = value; input.required = true;
    input.type = options.type || 'text'; input.maxLength = options.maxLength || 32;
    if (options.type === 'number') { input.min = '1'; input.max = '1000000'; input.step = '1'; }
    if (options.money) { input.inputMode = 'decimal'; input.pattern = '(0|[1-9][0-9]*)([.,][0-9]{1,2})?'; }
    label.append(input); return label;
  }
  function addLine(line = {id: freshId(), description: '', quantity: 1, unitNet: '', discountNet: '0.00', tax: '0.00'}) {
    const row = document.createElement('fieldset'); row.className = 'draft-line'; row.dataset.lineId = line.id;
    const legend = document.createElement('legend'); legend.textContent = 'Produs / serviciu'; row.append(legend);
    row.append(lineInput('Descriere', 'description', line.description, {maxLength: 255}), lineInput('Cantitate', 'quantity', line.quantity, {type: 'number'}), lineInput('Preț unitar fără taxe', 'unitNet', line.unitNet, {money: true}), lineInput('Reducere pe linie, fără taxe', 'discountNet', line.discountNet, {money: true}), lineInput('Taxă totală pe linie', 'tax', line.tax, {money: true}));
    const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'secondary'; remove.textContent = 'Elimină linia';
    remove.hidden = !canWrite || draft?.status === 'ARCHIVED';
    remove.addEventListener('click', () => { if (el('draft-lines').children.length > 1) { row.remove(); invalidate(); } }); row.append(remove); el('draft-lines').append(row);
  }
  function invalidate() { ++editorSequence; creationId = freshId(); el('draft-totals').textContent = 'Date modificate — recalculează totalul înainte de salvare.'; }
  function documentData() {
    const data = Object.fromEntries(fields.map(name => [name, el(`draft-${name}`).value]));
    data.lines = [...el('draft-lines').children].map(row => {
      const value = name => row.querySelector(`[name="${name}"]`).value;
      return {id: row.dataset.lineId, description: value('description'), quantity: Number(value('quantity')), unitNet: value('unitNet').replace(',', '.'), discountNet: value('discountNet').replace(',', '.'), tax: value('tax').replace(',', '.')};
    }); return data;
  }
  function showTotals(totals) { el('draft-totals').textContent = `Net: ${totals.net} ${totals.currency} · Taxe: ${totals.tax} ${totals.currency} · Total: ${totals.total} ${totals.currency}`; }
  function showEditor(result) {
    ++editorSequence;
    draft = result; el('draft-editor').hidden = false;
    const editable = canWrite && (!draft || draft.status === 'DRAFT');
    el('draft-fields').disabled = !editable; el('draft-edit-actions').hidden = !editable; el('draft-add-line').hidden = !editable;
    el('draft-archive').hidden = !editable || !draft;
    el('draft-revision').textContent = draft ? `${draft.status === 'ARCHIVED' ? 'Arhivată' : 'Ciornă'} · revizia ${draft.version}` : 'Ciornă nouă';
    el('draft-lines').replaceChildren();
    if (draft) { fields.forEach(name => { el(`draft-${name}`).value = draft.document[name]; }); draft.document.lines.forEach(addLine); showTotals(draft.totals); }
    else { el('draft-form').reset(); addLine(); el('draft-totals').textContent = ''; }
  }
  async function loadList(append = false) {
    const store = el('draft-store').value; const token = epoch, sequence = ++listSequence;
    if (!store) { el('draft-list').replaceChildren(); el('draft-next').hidden = true; return; }
    const query = new URLSearchParams({storeId: store, status: el('draft-status').value});
    if (append && nextCursor) query.set('after', nextCursor);
    const data = await api(`/api/invoice-drafts?${query}`); if (!sameContext(token) || sequence !== listSequence) return;
    if (!append) el('draft-list').replaceChildren();
    for (const item of data.drafts) {
      const card = document.createElement('article'); card.className = 'card';
      const title = document.createElement('h3'); title.textContent = item.reference;
      const total = document.createElement('p'); total.textContent = `${item.totals.total} ${item.totals.currency} · ${item.lineCount} linii`;
      const version = document.createElement('p'); version.className = 'hint'; version.textContent = `Revizia ${item.version} · ${item.status === 'ARCHIVED' ? 'Arhivată' : 'Ciornă'}`;
      const view = document.createElement('button'); view.type = 'button'; view.textContent = 'Deschide';
      view.addEventListener('click', () => action(async () => { const current = epoch; const result = await api(`/api/invoice-drafts/${item.id}?storeId=${store}`); if (sameContext(current)) showEditor(result); }));
      card.append(title, total, version, view); el('draft-list').append(card);
    }
    if (!el('draft-list').children.length) { const empty = document.createElement('p'); empty.textContent = 'Nicio ciornă pentru acest magazin și filtru.'; el('draft-list').append(empty); }
    nextCursor = data.nextCursor; el('draft-next').hidden = !nextCursor;
  }
  document.addEventListener('ordely:context', event => {
    const {me, stores} = event.detail; const previous = el('draft-store').value;
    const changed = merchant !== me.merchantId; merchant = me.merchantId;
    canWrite = ['owner', 'admin', 'finance'].includes(me.role);
    ++epoch; resetEditor();
    el('invoicing-panel').hidden = me.role === 'viewer'; el('draft-new').hidden = !canWrite;
    el('draft-store').replaceChildren(...stores.map(store => { const option = document.createElement('option'); option.value = store.id; option.textContent = store.name; return option; }));
    if (!changed && stores.some(store => store.id === previous)) el('draft-store').value = previous;
    if (!el('invoicing-panel').hidden) action(() => loadList());
  });
  document.addEventListener('ordely:logout', () => { ++epoch; merchant = null; resetEditor(); el('draft-list').replaceChildren(); el('invoicing-panel').hidden = true; });
  for (const id of ['draft-store', 'draft-status']) el(id).addEventListener('change', () => { ++epoch; nextCursor = null; resetEditor(); action(() => loadList()); });
  el('draft-refresh').addEventListener('click', () => action(() => loadList()));
  el('draft-next').addEventListener('click', () => action(() => loadList(true)));
  el('draft-new').addEventListener('click', () => { if (!el('draft-store').value) { el('message').textContent = 'Adaugă mai întâi un magazin Ordely. Poate fi Manual / test.'; return; } resetEditor(); showEditor(null); });
  el('draft-close').addEventListener('click', resetEditor);
  el('draft-add-line').addEventListener('click', () => { if (el('draft-lines').children.length < 50) { addLine(); invalidate(); } });
  el('draft-form').addEventListener('input', invalidate);
  el('draft-calculate').addEventListener('click', () => { if (!el('draft-form').reportValidity()) return; action(async () => { const token = epoch, sequence = editorSequence; const result = await api('/api/invoice-drafts/preview', 'POST', {storeId: el('draft-store').value, document: documentData()}); if (sameContext(token) && sequence === editorSequence) showTotals(result.totals); }); });
  el('draft-form').addEventListener('submit', event => {
    event.preventDefault(); action(async () => {
      const token = epoch, storeId = el('draft-store').value, document = documentData(), id = draft?.id || creationId;
      el('draft-fields').disabled = true;
      try {
        if (draft) await api(`/api/invoice-drafts/${id}`, 'PUT', {storeId, version: draft.version, document});
        else await api('/api/invoice-drafts', 'POST', {id, storeId, document});
        if (!sameContext(token)) return;
        const result = await api(`/api/invoice-drafts/${id}?storeId=${storeId}`); if (!sameContext(token)) return;
        showEditor(result); await loadList(); if (sameContext(token)) el('message').textContent = 'Ciorna a fost salvată local.';
      } finally { if (sameContext(token)) el('draft-fields').disabled = !canWrite || draft?.status === 'ARCHIVED'; }
    });
  });
  el('draft-archive').addEventListener('click', () => action(async () => {
    if (!draft) return; const token = epoch, current = draft;
    await api(`/api/invoice-drafts/${current.id}/archive`, 'POST', {storeId: current.storeId, version: current.version});
    if (sameContext(token)) { resetEditor(); await loadList(); el('message').textContent = 'Ciorna a fost arhivată.'; }
  }));
})();
