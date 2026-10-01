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
    el('invoice-fiscal').replaceChildren();
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
    customer.append(textNode('p', `Tip client: ${{individual:'Persoană fizică', company:'Persoană juridică'}[data.customer.type] || 'de confirmat'} · Identificare fiscală: ${data.customer.taxId || '—'}`));
    const seller = document.createElement('section'); seller.append(textNode('h3', 'Emitent și serie'));
    seller.append(textNode('p', data.seller ? `${data.seller.companyName} · seria ${data.seller.series}` : 'Configurează firma și seria în Oblio.'));
    seller.append(textNode('p', data.seller?.address ? `Adresă: ${Object.values(data.seller.address).filter(Boolean).join(', ') || 'de completat'}` : 'Completează adresa și statutul TVA în ciornă.'));
    groups.append(customer, seller); content.append(groups);
    content.append(textNode('h3', 'Sumele din CMS'), textNode('p', `Prețuri: ${data.priceBasis === 'tax_inclusive' ? 'cu taxe incluse' : data.priceBasis === 'tax_exclusive' ? 'fără taxe incluse' : 'bază neprecizată'}`));
    const labels = {original:'Total inițial', current:'Total curent', discount:'Reduceri curente', tax:'Taxe curente', shipping:'Transportul comenzii', received:'Încasat', refunded:'Rambursat', outstanding:'Rest de încasat'};
    const totals = document.createElement('dl'); totals.className = 'preparation-totals';
    for (const [key, label] of Object.entries(labels)) totals.append(textNode('dt', label), textNode('dd', amountLabel(data.totals[key])));
    content.append(totals, textNode('h3', 'Ce mai trebuie în ciornă'));
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
      card.append(textNode('p', `Taxe importate: ${line.taxes.length ? line.taxes.map(tax => `${tax.title || 'Taxă'}: ${amountLabel(tax.amount)}`).join(' · ') : 'Nicio taxă în lista importată'}. Unitate: ${line.unit || '—'} · TVA: ${line.taxTreatment || 'de ales'}${line.taxRate ? ` (${line.taxRate}%)` : ''}${line.taxReason ? ` · ${line.taxReason}` : ''}`));
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
  function showPreparation(data, version, frozen, changed = false, fiscal = null, reconciliation = null) {
    preparation = data; savedVersion = version; renderPreparation(fiscal ? {...data, ...fiscal, issues:[...fiscal.issues,...(reconciliation?.issues || [])]} : data);
    el('invoice-preparation-state').textContent = frozen ? `Ciornă salvată · revizia ${version}${changed ? ' · Comanda sau profilul emitentului s-a schimbat. Actualizează explicit din CMS.' : ''}` : 'Date curente din CMS · pot fi salvate chiar dacă sunt incomplete.';
    el('invoice-preparation-save').hidden = !canWrite || frozen;
    el('invoice-preparation-save').textContent = version ? 'Salvează actualizarea din CMS' : 'Salvează ciorna din comandă';
    el('invoice-preparation-refresh').hidden = !canWrite || !frozen;
    renderFiscal(data, frozen, changed, fiscal, reconciliation);
  }
  function renderFiscal(data, frozen, changed, fiscal, reconciliation) {
    const section = el('invoice-fiscal'); section.replaceChildren();
    if (!frozen) return;
    if (reconciliation) {
      section.append(textNode('h3', 'Verificarea sumelor'), textNode('p', reconciliation.status === 'RECONCILED' ? 'Sumele și datele documentului sunt reconciliate local. Emiterea va fi conectată separat.' : reconciliation.status === 'MISMATCH' ? 'Există diferențe în sume sau date fiscale incompatibile. Corectează problemele afișate în ciornă.' : 'Datele fiscale sunt încă incomplete. Calculul se verifică din sumele importate.'));
      if (reconciliation.totals) {const total = reconciliation.totals; section.append(textNode('p', `Produse: net ${amountLabel(total.net)} · taxe ${amountLabel(total.tax)} · total ${amountLabel(total.gross)} · reduceri ${amountLabel(total.discount)}`));}
    }
    section.append(textNode('h3', 'Completează ciorna'), textNode('p', fiscal?.readyForMapping ? 'Datele fiscale sunt completate. Rezultatul verificării sumelor este afișat mai sus; emiterea se conectează separat.' : 'Poți salva și o completare parțială. Datele importate se completează din CMS; câmpurile lipsă pot fi adăugate aici.'));
    if (changed) section.append(textNode('p', 'Comanda sau configurarea s-a schimbat. Actualizează din CMS înainte de a modifica completările.'));
    const values = data.fiscalDetails || {}, form = document.createElement('form'), fieldset = document.createElement('fieldset');
    form.id = 'invoice-fiscal-form'; fieldset.disabled = !canWrite || changed; fieldset.className = 'fiscal-fields';
    const vat = {'':'Alege', registered:'Înregistrat TVA', not_registered:'Neînregistrat TVA', not_applicable:'Nu se aplică'};
    const treatments = {'':'Alege', standard:'Cotă TVA', exempt:'Scutit', outside_scope:'În afara sferei TVA'};
    const controls = {};
    function input(parent, group, key, labelText, options = {}) {
      const label = textNode('label', labelText), control = document.createElement(options.choices ? 'select' : 'input');
      if (options.choices) for (const [value, title] of Object.entries(options.choices)) {const option = textNode('option', title); option.value = value; control.append(option);}
      else {control.type = options.type || 'text'; control.maxLength = 255;}
      control.name = `${group}.${key}`; control.value = options.imported || options.value || values[group]?.[key] || '';
      if (options.imported) {control.readOnly = true; control.title = 'Date importate din CMS';}
      if (key === 'country') {control.maxLength = 2; control.pattern = '[A-Z]{2}'; control.placeholder = 'RO';}
      if (key === 'rate') {control.inputMode = 'decimal'; control.pattern = '(0|[1-9][0-9]?|100)([.][0-9]{1,4})?'; control.placeholder = 'Cotă procentuală';}
      controls[control.name] = {control, imported:!!options.imported}; label.append(control); parent.append(label);
    }
    function group(title) {const box = document.createElement('section'); box.append(textNode('h4', title)); const grid = document.createElement('div'); grid.className = 'form-grid'; box.append(grid); fieldset.append(box); return grid;}
    const dates = group('Date document'); input(dates, 'document', 'issuedOn', 'Data emiterii', {type:'date'}); input(dates, 'document', 'dueOn', 'Scadența', {type:'date'});
    const labels = {street:'Stradă', streetExtra:'Adresă suplimentară', city:'Localitate', region:'Județ / regiune', postalCode:'Cod poștal', country:'Țară (cod)'};
    for (const part of ['customer','seller']) {
      const grid = group(part === 'customer' ? 'Client · adresa de facturare' : 'Emitent · firma și seria din configurare');
      if (part === 'customer') {
        input(grid, part, 'name', 'Nume', {imported:data.customer.name});
        input(grid, part, 'type', 'Tip client', {choices:{'':'Alege', individual:'Persoană fizică', company:'Persoană juridică'}});
        input(grid, part, 'taxId', 'Identificare fiscală (firmă)');
      }
      for (const [key, label] of Object.entries(labels)) input(grid, part, key, label, {imported:part === 'customer' ? data.customer.address[key] : null});
      input(grid, part, 'vatStatus', 'Statut TVA', {choices:part === 'seller' ? {'':'Alege', registered:vat.registered, not_registered:vat.not_registered} : vat});
    }
    function taxInputs(grid, key, value = null) {
      input(grid, key, 'unit', 'Unitate de măsură', {value:value?.unit});
      input(grid, key, 'treatment', 'Tratament TVA', {choices:treatments, value:value?.treatment});
      input(grid, key, 'rate', 'Cotă TVA (%)', {value:value?.rate});
      input(grid, key, 'reason', 'Motiv scutire / în afara sferei', {value:value?.reason});
    }
    const common = group('Valori comune pentru produse'); taxInputs(common, 'lineDefaults');
    fieldset.append(textNode('p', 'Valorile comune se aplică liniilor fără excepții. Pentru o excepție TVA, completează tratamentul și cota sau motivul împreună.'));
    const overrides = document.createElement('details'); overrides.className = 'disclosure'; overrides.append(textNode('summary', 'Excepții pe produse'));
    for (const [index, line] of data.lines.entries()) {
      const box = document.createElement('section'), grid = document.createElement('div'); grid.className = 'form-grid';
      box.append(textNode('h4', `${index + 1}. ${line.description || 'Produs'}`), grid);
      taxInputs(grid, `line:${line.id}`, values.lines?.find(item => item.id === line.id)); overrides.append(box);
    }
    fieldset.append(overrides);
    const save = textNode('button', 'Salvează completările'); save.type = 'submit'; if (canWrite && !changed) fieldset.append(save);
    form.append(fieldset); section.append(form);
    form.addEventListener('submit', event => {
      event.preventDefault(); if (!canWrite || changed || !form.reportValidity()) return;
      const details = {document:{}, seller:{}, customer:{}, lineDefaults:{}, lines:[]};
      for (const [name, {control, imported}] of Object.entries(controls)) {
        const [part, key] = name.split('.'); if (part.startsWith('line:')) continue;
        details[part][key] = imported ? '' : control.value.trim();
      }
      for (const line of data.lines) {
        const item = {id:line.id}; for (const key of ['unit','treatment','rate','reason']) item[key] = controls[`line:${line.id}.${key}`].control.value.trim();
        if (['unit','treatment','rate','reason'].some(key => item[key])) details.lines.push(item);
      }
      const token = epoch, sequence = ++preparationSequence, version = savedVersion, source = data.source; fieldset.disabled = true;
      action(async () => {
        try {
          await api(`/api/invoice-order-drafts/${source.orderId}`, 'PUT', {storeId:source.storeId, expectedVersion:version, details});
          if (!sameContext(token) || sequence !== preparationSequence) return;
          const result = await api(`/api/invoice-order-drafts/${source.orderId}?storeId=${source.storeId}`);
          if (!sameContext(token) || sequence !== preparationSequence) return;
          showPreparation(result.draft.snapshot, result.draft.version, true, result.draft.sourceChanged, result.draft.fiscal, result.draft.reconciliation);
          await loadOrderList(); if (sameContext(token)) el('message').textContent = 'Completările au fost salvate în ciorna locală.';
        } finally {if (sameContext(token) && sequence === preparationSequence) fieldset.disabled = !canWrite || changed;}
      });
    });
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
    showPreparation(result.draft.snapshot, result.draft.version, true, result.draft.sourceChanged, result.draft.fiscal, result.draft.reconciliation);
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
        if (sameContext(current) && request === preparationSequence && result.draft) showPreparation(result.draft.snapshot, result.draft.version, true, result.draft.sourceChanged, result.draft.fiscal, result.draft.reconciliation);
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
