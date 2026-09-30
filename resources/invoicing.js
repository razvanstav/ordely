'use strict';
(() => {
  const el = id => document.getElementById(id);
  let merchant = null, canWrite = false, draft = null, nextCursor = null, epoch = 0, listSequence = 0, editorSequence = 0;
  const freshId = () => crypto.randomUUID().replaceAll('-', '');
  let creationId = freshId();
  let preparationSequence = 0, preparationReturnTo = null, preparation = null, savedVersion = 0, orderCursor = null, orderListSequence = 0;
  const fields = ['reference', 'customerName', 'customerAddress', 'customerTaxId', 'currency'];
  const sameContext = token => token === epoch;

  function clearPreparation() {
    ++preparationSequence;
    el('invoice-preparation').hidden = true;
    el('invoice-preparation-content').replaceChildren();
    preparationReturnTo = null;
    preparation = null; savedVersion = 0;
    el('invoice-preparation-save').hidden = true;
    el('invoice-preparation-refresh').hidden = true;
    el('invoice-preparation-state').textContent = '';
  }
  function clearLists() {
    ++listSequence; ++orderListSequence; nextCursor = null; orderCursor = null;
    el('draft-list').replaceChildren(); el('order-draft-list').replaceChildren();
    el('draft-next').hidden = true; el('order-draft-next').hidden = true;
  }
  const textNode = (tag, value) => {const node = document.createElement(tag); node.textContent = value; return node;};
  const amountLabel = value => value ? `${value.decimal} ${value.currency}` : 'Lipsește din CMS';
  function renderPreparation(data) {
    const content = el('invoice-preparation-content');
    content.replaceChildren(textNode('p', `${data.source.reference} · versiunea importată ${data.source.version}`));
    const groups = document.createElement('div'); groups.className = 'form-grid';
    const customer = document.createElement('section'); customer.append(textNode('h3', 'Datele clientului'));
    customer.append(textNode('p', data.customer.name || 'Nume lipsă'), textNode('p', `Contact: ${data.customer.contactName || '—'} · ${data.customer.email || '—'}`));
    customer.append(textNode('p', `Adresă de facturare: ${Object.values(data.customer.address).filter(Boolean).join(', ') || 'Lipsește din CMS'}`));
    customer.append(textNode('p', 'Tip client și statut TVA: de confirmat'), textNode('p', 'Identificare fiscală: indisponibilă în importul actual'));
    const seller = document.createElement('section'); seller.append(textNode('h3', 'Emitent și serie'));
    seller.append(textNode('p', data.seller ? `${data.seller.companyName} · seria ${data.seller.series}` : 'Configurează firma și seria în Oblio.'));
    seller.append(textNode('p', 'Adresa și profilul fiscal complet urmează să fie pregătite.'));
    groups.append(customer, seller); content.append(groups);
    content.append(textNode('h3', 'Sumele din CMS'), textNode('p', `Prețuri: ${data.priceBasis === 'tax_inclusive' ? 'cu taxe incluse' : data.priceBasis === 'tax_exclusive' ? 'fără taxe incluse' : 'bază neprecizată'}`));
    const labels = {original:'Total inițial', current:'Total curent', discount:'Reduceri curente', tax:'Taxe curente', shipping:'Transportul comenzii', received:'Încasat', refunded:'Rambursat', outstanding:'Rest de încasat'};
    const totals = document.createElement('dl'); totals.className = 'preparation-totals';
    for (const [key, label] of Object.entries(labels)) totals.append(textNode('dt', label), textNode('dd', amountLabel(data.totals[key])));
    content.append(totals, textNode('h3', 'Ce mai trebuie înainte de emitere'));
    const issues = document.createElement('ul'), grouped = new Map();
    for (const issue of data.issues) {
      const match = /^lines\.(\d+)\./.exec(issue.path);
      if (!match) {issues.append(textNode('li', issue.message)); continue;}
      const key = `${issue.code}:${issue.message}`;
      if (!grouped.has(key)) grouped.set(key, {message:issue.message, lines:new Set()});
      grouped.get(key).lines.add(Number(match[1]) + 1);
    }
    for (const group of grouped.values()) issues.append(textNode('li', `${group.message} (${group.lines.size} ${group.lines.size === 1 ? 'linie' : 'linii'})`));
    content.append(issues);
    const lineDetails = document.createElement('details'); lineDetails.className = 'disclosure preparation-items';
    lineDetails.append(textNode('summary', `Produse și taxe importate · ${data.lines.length} linii`));
    for (const [index, line] of data.lines.entries()) {
      const card = document.createElement('article'); card.className = 'card';
      card.append(textNode('h4', `Linia ${index + 1}: ${line.description || 'Descriere lipsă'}${line.variant ? ` · ${line.variant}` : ''}`));
      card.append(textNode('p', `Cantitate: ${line.quantity} inițial / ${line.currentQuantity} curent · SKU: ${line.sku || '—'}`));
      card.append(textNode('p', `Preț unitar inițial: ${amountLabel(line.prices.originalUnitPrice)} · Total inițial: ${amountLabel(line.prices.originalTotal)} · Total după reduceri: ${amountLabel(line.prices.lineDiscountedTotal)}`));
      card.append(textNode('p', `Reduceri alocate: ${line.discounts === null ? 'Lipsesc din CMS' : line.discounts.length ? line.discounts.map(amountLabel).join(' + ') : 'Niciuna'}`));
      card.append(textNode('p', `Taxe importate: ${line.taxes.length ? line.taxes.map(tax => `${tax.title || 'Taxă'}: ${amountLabel(tax.amount)}`).join(' · ') : 'Nicio taxă în lista importată'}. Tratamentul TVA trebuie ales explicit.`));
      lineDetails.append(card);
    }
    content.append(lineDetails);
    el('invoice-preparation').hidden = false;
    el('invoice-preparation-title').focus({preventScroll:true});
    el('invoice-preparation').scrollIntoView({block:'start'});
  }
  document.addEventListener('ordely:prepare-invoice', event => {
    const {storeId, orderId, returnTo} = event.detail;
    if (!merchant || el('invoicing-panel').hidden || ![...el('draft-store').options].some(option => option.value === storeId)) return;
    ++epoch; resetEditor(); clearPreparation(); el('draft-store').value = storeId;
    preparationReturnTo = returnTo;
    const token = epoch, sequence = preparationSequence;
    window.location.hash = 'invoicing';
    action(async () => {
      const data = await api(`/api/invoice-preparation/${orderId}?storeId=${storeId}`);
      if (!sameContext(token) || sequence !== preparationSequence) return;
      const saved = await api(`/api/invoice-order-drafts/${orderId}?storeId=${storeId}`);
      if (!sameContext(token) || sequence !== preparationSequence) return;
      showPreparation(data, saved.draft?.version || 0, false); await loadList();
    });
  });
  function showPreparation(data, version, frozen, changed = false) {
    preparation = data; savedVersion = version; renderPreparation(data);
    el('invoice-preparation-state').textContent = frozen ? `Ciornă salvată · revizia ${version}${changed ? ' · Comanda sau profilul emitentului s-a schimbat. Actualizează explicit din CMS.' : ''}` : 'Date curente din CMS · pot fi salvate chiar dacă sunt incomplete.';
    el('invoice-preparation-save').hidden = !canWrite || frozen;
    el('invoice-preparation-save').textContent = version ? 'Salvează actualizarea din CMS' : 'Salvează ciorna din comandă';
    el('invoice-preparation-refresh').hidden = !canWrite || !frozen;
  }
  el('invoice-preparation-refresh').addEventListener('click', () => action(async () => {
    if (!preparation) return;
    const token = epoch, sequence = ++preparationSequence, source = preparation.source, version = savedVersion;
    const data = await api(`/api/invoice-preparation/${source.orderId}?storeId=${source.storeId}`);
    if (sameContext(token) && sequence === preparationSequence) showPreparation(data, version, false);
  }));
  el('invoice-preparation-save').addEventListener('click', () => action(async () => {
    if (!preparation || !canWrite) return;
    const token = epoch, sequence = preparationSequence, source = preparation.source;
    await api(`/api/invoice-order-drafts/${source.orderId}`, 'POST', {storeId:source.storeId, expectedVersion:savedVersion, orderVersion:source.version, profileVersion:preparation.seller?.version || 0});
    if (!sameContext(token) || sequence !== preparationSequence) return;
    const result = await api(`/api/invoice-order-drafts/${source.orderId}?storeId=${source.storeId}`);
    if (!sameContext(token) || sequence !== preparationSequence) return;
    showPreparation(result.draft.snapshot, result.draft.version, true, result.draft.sourceChanged);
    await loadOrderList();
    if (sameContext(token)) el('message').textContent = 'Ciorna din comandă a fost salvată local.';
  }));

  async function loadOrderList(append = false) {
    const store = el('draft-store').value, token = epoch, sequence = ++orderListSequence;
    if (!store) {el('order-draft-list').replaceChildren(); el('order-draft-next').hidden = true; return;}
    const query = new URLSearchParams({storeId:store}); if (append && orderCursor) query.set('after', orderCursor);
    const data = await api(`/api/invoice-order-drafts?${query}`);
    if (!sameContext(token) || sequence !== orderListSequence) return;
    if (!append) el('order-draft-list').replaceChildren();
    for (const item of data.drafts) {
      const card = document.createElement('article'); card.className = 'card';
      card.append(textNode('h3', item.reference), textNode('p', `${amountLabel(item.total)} · ${item.lineCount} linii · revizia ${item.version}`));
      const open = textNode('button', 'Deschide ciorna din comandă'); open.type = 'button';
      open.addEventListener('click', () => action(async () => {
        const current = epoch; resetEditor(); clearPreparation(); const request = preparationSequence;
        const result = await api(`/api/invoice-order-drafts/${item.orderId}?storeId=${store}`);
        if (sameContext(current) && request === preparationSequence && result.draft) showPreparation(result.draft.snapshot, result.draft.version, true, result.draft.sourceChanged);
      })); card.append(open); el('order-draft-list').append(card);
    }
    if (!el('order-draft-list').children.length) el('order-draft-list').append(emptyState('Nicio ciornă din comenzi.', 'Deschide o comandă și alege „Pregătește facturarea”.'));
    orderCursor = data.nextCursor; el('order-draft-next').hidden = !orderCursor;
  }
  el('order-draft-next').addEventListener('click', () => action(() => loadOrderList(true)));
  el('invoice-preparation-close').addEventListener('click', () => {
    const returnTo = preparationReturnTo; clearPreparation();
    if (returnTo?.isConnected) {
      if (window.location.hash !== '#commerce') window.addEventListener('hashchange', () => {if (returnTo.isConnected) returnTo.focus({preventScroll:true});}, {once:true});
      window.location.hash = 'commerce';
      returnTo.focus({preventScroll:true});
    }
    else el('draft-store').focus();
  });

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
    if (!store) { el('draft-list').replaceChildren(); el('draft-next').hidden = true; await loadOrderList(); return; }
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
    if (!el('draft-list').children.length) el('draft-list').append(emptyState(el('draft-status').value === 'ARCHIVED' ? 'Nicio ciornă arhivată.' : 'Prima ta ciornă începe aici.', canWrite ? 'Alege magazinul și apasă „Ciornă nouă” pentru a pregăti o factură.' : 'Ciornele create de echipa ta vor apărea aici.'));
    nextCursor = data.nextCursor; el('draft-next').hidden = !nextCursor;
    if (!append) await loadOrderList();
  }
  document.addEventListener('ordely:context', event => {
    const {me, stores} = event.detail; const previous = el('draft-store').value;
    const changed = merchant !== me.merchantId; merchant = me.merchantId;
    canWrite = ['owner', 'admin', 'finance'].includes(me.role);
    ++epoch; resetEditor(); clearLists();
    clearPreparation();
    el('invoicing-panel').hidden = me.role === 'viewer'; el('draft-new').hidden = !canWrite;
    el('draft-store').replaceChildren(...stores.map(store => { const option = document.createElement('option'); option.value = store.id; option.textContent = store.name; return option; }));
    if (!changed && stores.some(store => store.id === previous)) el('draft-store').value = previous;
    if (!el('invoicing-panel').hidden) action(() => loadList());
  });
  document.addEventListener('ordely:logout', () => { ++epoch; merchant = null; resetEditor(); clearPreparation(); el('draft-list').replaceChildren(); el('order-draft-list').replaceChildren(); el('invoicing-panel').hidden = true; });
  for (const id of ['draft-store', 'draft-status']) el(id).addEventListener('change', () => { ++epoch; resetEditor(); clearPreparation(); clearLists(); action(() => loadList()); });
  el('draft-refresh').addEventListener('click', () => action(() => loadList()));
  el('draft-next').addEventListener('click', () => action(() => loadList(true)));
  el('draft-new').addEventListener('click', () => { if (!el('draft-store').value) { el('message').textContent = 'Adaugă mai întâi un magazin din secțiunea Magazine.'; return; } clearPreparation(); resetEditor(); showEditor(null); el('draft-editor').scrollIntoView({block:'start'}); el('draft-reference').focus({preventScroll:true}); });
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
