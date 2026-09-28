'use strict';
let csrf = '';
let storeKey = null;
let connectionId = null;
let visibleStores = [];
const $ = selector => document.querySelector(selector);
async function api(path, method = 'GET', data, idempotencyKey) {
  const headers = {'Content-Type': 'application/json', 'X-CSRF-Token': csrf};
  if (idempotencyKey) headers['Idempotency-Key'] = idempotencyKey;
  const response = await fetch(path, {method, credentials: 'same-origin', headers, body: data === undefined ? undefined : JSON.stringify(data)});
  const result = await response.json();
  if (!response.ok) {
    if (response.status === 401) { $('#login').hidden = false; $('#workspace').hidden = true; csrf = ''; clearSecretInputs(); document.dispatchEvent(new Event('ordely:logout')); }
    if (response.status === 409) throw new Error('Datele nu mai corespund versiunii salvate. Redeschide elementul înainte de a încerca din nou.');
    const messages = {invalid_credentials: 'Email sau parolă incorectă.', unauthenticated: 'Conectează-te pentru a continua.', forbidden: 'Nu ai permisiune pentru această acțiune.', invalid_csrf: 'Sesiunea s-a schimbat. Reîncarcă pagina.', too_many_attempts: 'Prea multe încercări. Reîncearcă peste 15 minute.', shopify_reauthorization_required: 'Accesul Shopify a fost revocat. Generează un cod nou și reconectează aplicația din Shopify.', shopify_unavailable: 'Shopify nu răspunde momentan. Reîncearcă.', shopify_not_configured: 'Integrarea Shopify trebuie configurată pe server.'};
    throw new Error(messages[result.error] || 'Acțiunea nu a reușit. Verifică datele și reîncearcă.');
  }
  return result;
}
async function refresh() {
  const me = await api('/api/me'); csrf = me.csrf;
  $('#login').hidden = true; $('#workspace').hidden = false;
  $('#role').textContent = `ROL · ${me.role}`;
  $('#merchants').replaceChildren(...me.merchants.map(merchant => {
    const option = document.createElement('option'); option.value = merchant.id; option.textContent = merchant.name; option.selected = merchant.id === me.merchantId; return option;
  }));
  const {stores} = await api('/api/stores');
  visibleStores = stores;
  document.dispatchEvent(new CustomEvent('ordely:context', {detail: {me, stores}}));
  $('#stores').replaceChildren(...stores.map(store => {
    const card = document.createElement('article'); card.className = 'card';
    const title = document.createElement('h2'); title.textContent = store.name;
    const platform = document.createElement('p'); platform.textContent = store.platform;
    card.append(title, platform); return card;
  }));
  if (!stores.length) { const empty = document.createElement('p'); empty.textContent = 'Nu există magazine disponibile pentru acest cont.'; $('#stores').append(empty); }
  $('#store-form').hidden = !['owner', 'admin'].includes(me.role);
  $('#operations-panel').hidden = !['owner', 'admin'].includes(me.role);
  if (!$('#operations-panel').hidden) await refreshOperations();
  $('#integrations-panel').hidden = !(['owner', 'admin'].includes(me.role) && me.allStores);
  if (!$('#integrations-panel').hidden) await refreshIntegrations();
  await prepareCommerce(me);
}
async function refreshIntegrations() {
  const {providers, connections} = await api('/api/integrations');
  $('#provider-roadmap').textContent = `Shopify: conectare și import comenzi/catalog. Alte integrări planificate: ${providers.filter(provider => !provider.available && provider.key !== 'shopify').map(provider => provider.label).join(', ')}.`;
  const shopifyStores = visibleStores.filter(store => store.platform === 'shopify');
  $('#shopify-store').replaceChildren(...shopifyStores.map(store => {const option = document.createElement('option'); option.value = store.id; option.textContent = store.name; return option;}));
  $('#shopify-intent-form').hidden = !shopifyStores.length;
  const available = providers.filter(provider => provider.available);
  $('#connection-form').hidden = !available.length;
  $('#connection-provider').replaceChildren(...available.map(provider => { const option = document.createElement('option'); option.value = provider.key; option.textContent = provider.label; return option; }));
  $('#connections').replaceChildren(...connections.map(connection => {
    const card = document.createElement('article'); card.className = 'card';
    const title = document.createElement('h3'); title.textContent = connection.label;
    const status = document.createElement('p'); status.textContent = `${connection.provider} · ${connection.status === 'active' ? (connection.provider === 'shopify' ? 'Conectată' : 'Disponibilă în simulator') : 'Revocată'}`;
    const key = document.createElement('p'); key.className = 'hint'; key.textContent = `Cheie de criptare: ${connection.keyId} · versiunea ${connection.version}`;
    card.append(title, status, key);
    const command = async (name, values = {}) => { await api(`/api/integrations/${connection.id}/${name}`, 'POST', {version: connection.version, ...values}); await refreshIntegrations(); };
    const rotate = document.createElement('button'); rotate.textContent = 'Recriptează cu cheia activă'; rotate.addEventListener('click', () => action(() => command('rotate')));
    card.append(rotate);
    if (connection.status !== 'active') return card;
    const revoke = document.createElement('button'); revoke.className = 'secondary'; revoke.textContent = 'Revocă accesul'; revoke.addEventListener('click', () => action(() => command('revoke')));
    card.append(revoke);
    if (connection.provider === 'shopify') {
      for (const association of connection.bindings) {
        const name = document.createElement('p'); name.textContent = visibleStores.find(store => store.id === association.storeId)?.name || 'Magazin indisponibil'; card.append(name);
        for (const [label, refresh] of [['Verifică accesul', false], ['Reînnoiește accesul', true]]) {
          const button = document.createElement('button'); button.className = 'secondary'; button.textContent = label;
          button.addEventListener('click', () => action(async () => {
            try { await api('/api/shopify/check', 'POST', {storeId: association.storeId, connectionId: connection.id, refresh}); $('#message').textContent = refresh ? 'Accesul Shopify a fost reînnoit.' : 'Accesul Shopify este valid.'; }
            finally { await refreshIntegrations(); }
          })); card.append(button);
        }
      }
      return card;
    }
    const replacement = document.createElement('form'); replacement.autocomplete = 'off';
    const label = document.createElement('label'); label.textContent = 'Token nou de test';
    const input = document.createElement('input'); input.type = 'password'; input.required = true; input.maxLength = 4096; input.autocomplete = 'new-password'; label.append(input);
    const replace = document.createElement('button'); replace.textContent = 'Înlocuiește tokenul'; replacement.append(label, replace);
    replacement.addEventListener('submit', event => { event.preventDefault(); const apiToken = input.value; input.value = ''; action(() => command('credentials', {credentials: {apiToken}})); }); card.append(replacement);
    const binding = document.createElement('form'); const storeLabel = document.createElement('label'); storeLabel.textContent = 'Magazin'; const select = document.createElement('select');
    select.replaceChildren(...visibleStores.map(store => { const option = document.createElement('option'); option.value = store.id; option.textContent = store.name; return option; })); storeLabel.append(select);
    const bind = document.createElement('button'); bind.textContent = 'Asociază ca implicită'; bind.disabled = !visibleStores.length; binding.append(storeLabel, bind);
    binding.addEventListener('submit', event => { event.preventDefault(); action(() => command('bind', {storeId: select.value, isDefault: true})); }); card.append(binding);
    for (const association of connection.bindings) {
      const row = document.createElement('p'); const name = visibleStores.find(store => store.id === association.storeId)?.name || 'Magazin indisponibil'; row.textContent = `${name}${Number(association.isDefault) ? ' · implicită' : ''} `;
      const unbind = document.createElement('button'); unbind.className = 'secondary'; unbind.textContent = 'Dezasociază'; unbind.addEventListener('click', () => action(() => command('unbind', {storeId: association.storeId})));
      const capabilities = document.createElement('button'); capabilities.className = 'secondary'; capabilities.textContent = 'Verifică funcțiile'; capabilities.addEventListener('click', () => action(async () => { const result = await api(`/api/integrations/${connection.id}/capabilities`, 'POST', {storeId: association.storeId, kind: connection.kind}); $('#message').textContent = `Funcții simulator: ${result.features.join(', ')}.`; })); row.append(unbind, capabilities); card.append(row);
    }
    return card;
  }));
  if (!connections.length) { const empty = document.createElement('p'); empty.textContent = 'Nu există conexiuni configurate.'; $('#connections').append(empty); }
  try {const {events} = await api('/api/shopify/events'); const pending = events.filter(event => event.status === 'needs_review').length; $('#shopify-events').textContent = `Evenimente Shopify recente: ${events.length}. Solicitări de verificat: ${pending}.`; renderPrivacy(events);}
  catch {$('#shopify-events').textContent = 'Configurează integrarea Shopify pe server pentru conectare.';}
}
async function refreshOperations() {
  const data = await api('/api/operations');
  const statuses = {READY: 'În așteptare', RUNNING: 'În lucru', SUCCEEDED: 'Finalizat', DEAD: 'Necesită atenție', PENDING: 'În așteptare', IN_FLIGHT: 'În curs', CONFIRMED: 'Confirmat', RETRYABLE: 'Reluare programată', FAILED: 'Eșuat', UNKNOWN: 'Rezultat de verificat'};
  const cards = [...data.jobs.map(job => ({...job, job: true})), ...data.operations].map(item => {
    const card = document.createElement('article'); card.className = 'card';
    const title = document.createElement('h3'); title.textContent = statuses[item.status] || item.status;
    const details = document.createElement('p'); details.textContent = `${item.type} · încercări: ${item.attempt_count}`;
    const reference = document.createElement('p'); reference.className = 'hint'; reference.textContent = `Referință: ${item.id}`;
    card.append(title, details, reference);
    if (item.job && item.status === 'DEAD' && ['transient', 'lease_expired'].includes(item.last_error)) {
      const button = document.createElement('button'); button.textContent = 'Reia lucrarea';
      button.addEventListener('click', () => action(async () => { await api(`/api/jobs/${item.id}/retry`, 'POST', {}); await refreshOperations(); })); card.append(button);
    }
    return card;
  });
  $('#operations').replaceChildren(...cards);
  if (!cards.length) { const empty = document.createElement('p'); empty.textContent = 'Nu există lucrări înregistrate.'; $('#operations').append(empty); }
}
async function action(work) {
  $('#message').textContent = '';
  const buttons = [...document.querySelectorAll('button')]; buttons.forEach(button => button.disabled = true);
  try { await work(); } catch (error) { $('#message').textContent = error.message; }
  finally { buttons.forEach(button => button.disabled = false); }
}
function clearSecretInputs() { document.querySelectorAll('input[type="password"]').forEach(input => { input.value = ''; }); }
$('#shopify-intent-form').addEventListener('submit', event => {event.preventDefault(); action(async () => {const result = await api('/api/shopify/intents', 'POST', Object.fromEntries(new FormData(event.target))); $('#shopify-code').value = result.code; $('#shopify-code-label').hidden = false; $('#message').textContent = 'Cod pregătit. Copiază-l în aplicația Ordely din Shopify.';});});
$('#shopify-copy').addEventListener('click', () => action(async () => {await navigator.clipboard.writeText($('#shopify-code').value); $('#message').textContent = 'Cod copiat.';}));
$('#login-form').addEventListener('submit', event => { event.preventDefault(); action(async () => { const data = Object.fromEntries(new FormData(event.target)); const result = await api('/api/auth/login', 'POST', data); csrf = result.csrf; event.target.reset(); await refresh(); }); });
$('#store-form').addEventListener('input', () => { storeKey = null; });
$('#store-form').addEventListener('submit', event => { event.preventDefault(); action(async () => { storeKey ??= crypto.randomUUID(); await api('/api/stores', 'POST', Object.fromEntries(new FormData(event.target)), storeKey); storeKey = null; event.target.reset(); await refresh(); }); });
$('#merchants').addEventListener('change', event => action(async () => { clearSecretInputs(); document.dispatchEvent(new Event('ordely:logout')); const result = await api('/api/auth/merchant', 'POST', {merchantId: event.target.value}); csrf = result.csrf; connectionId = null; storeKey = null; $('#connection-form').reset(); await refresh(); }));
$('#logout').addEventListener('click', () => action(async () => { await api('/api/auth/logout', 'POST', {}); document.dispatchEvent(new Event('ordely:logout')); clearSecretInputs(); csrf = ''; $('#login').hidden = false; $('#workspace').hidden = true; }));
$('#refresh-operations').addEventListener('click', () => action(refreshOperations));
$('#refresh-integrations').addEventListener('click', () => action(refreshIntegrations));
$('#connection-form').addEventListener('input', () => { connectionId = null; });
$('#connection-form').addEventListener('submit', event => { event.preventDefault(); action(async () => { connectionId ??= crypto.randomUUID().replaceAll('-', ''); const data = Object.fromEntries(new FormData(event.target)); await api('/api/integrations', 'POST', {id: connectionId, provider: data.provider, label: data.label, credentials: {apiToken: data.apiToken}}); connectionId = null; event.target.reset(); await refreshIntegrations(); }); });
let commerceCursor = null;
let commerceTimer = null;
let commerceGeneration = 0;
let commerceCanRead = false;
async function prepareCommerce(me) {
  commerceCanRead = me.role !== 'viewer';
  clearTimeout(commerceTimer); commerceGeneration++; commerceCursor = null;
  $('#commerce-detail').replaceChildren();
  const stores = visibleStores.filter(store => store.platform === 'shopify');
  $('#commerce-panel').hidden = !stores.length;
  $('#commerce-actions').hidden = !['owner', 'admin'].includes(me.role);
  $('#commerce-store').replaceChildren(...stores.map(store => {const option = document.createElement('option'); option.value = store.id; option.textContent = store.name; return option;}));
  if (stores.length) await refreshCommerce();
}
function commerceText(tag, text) {const element = document.createElement(tag); element.textContent = text; return element;}
function moneyLabel(money) {
  const negative = money.minor.startsWith('-');
  const digits = money.minor.replace('-', '').padStart(money.exponent + 1, '0');
  const value = money.exponent ? `${digits.slice(0, -money.exponent)},${digits.slice(-money.exponent)}` : digits;
  return `${negative ? '-' : ''}${value} ${money.currency}`;
}
async function refreshCommerce(after = null) {
  clearTimeout(commerceTimer);
  const generation = ++commerceGeneration, store = $('#commerce-store').value, kind = $('#commerce-kind').value;
  if (!store || $('#workspace').hidden) return;
  const data = await api(`/api/commerce?storeId=${store}&kind=${kind}${after ? `&after=${after}` : ''}`);
  if (generation !== commerceGeneration || store !== $('#commerce-store').value) return;
  commerceCursor = data.nextCursor;
  $('#commerce-next').hidden = !commerceCursor;
  const run = data.run;
  $('#commerce-progress').textContent = !run ? 'Nu există încă un import.' : run.status === 'completed' ? `Sincronizare finalizată · ${run.completed_at} UTC` : run.status === 'cancelled' ? 'Import oprit.' : Number(run.failed) ? `Import incomplet · ${run.failed} lucrări necesită atenție. Verifică accesul Shopify și lucrările în fundal.` : `Sincronizare în curs · ${run.done}/${run.tasks} pagini procesate. Datele apar la final.`;
  $('#commerce-restart').hidden = !run || run.status !== 'running';
  const labels = {order:'comenzi', product:'produse', variant:'variante', inventory:'poziții de stoc'};
  $('#commerce-counts').textContent = data.counts.map(item => `${item.count} ${labels[item.kind]}`).join(' · ');
  $('#commerce-records').replaceChildren(...data.records.map(record => {
    const card = document.createElement('article'); card.className = 'card'; const item = record.data;
    card.append(commerceText('h3', (record.parentTitle ? `${record.parentTitle} · ` : '') + (item.number || item.title || item.locationName)));
    if (kind === 'order') {
      card.append(commerceText('p', `${moneyLabel(item.totals.current)} · ${item.paymentStatus} · ${item.fulfillmentStatus}${item.cancelledAt ? ' · Anulată' : ''}`));
      const button = commerceText('button', 'Detalii comandă'); button.className = 'secondary';
      button.addEventListener('click', () => action(async () => {
        const result = await api(`/api/commerce/orders/${record.id}?storeId=${store}`);
        if (store !== $('#commerce-store').value) return;
        const order = result.order, detail = $('#commerce-detail'); detail.replaceChildren(commerceText('h3', order.number));
        for (const line of order.lines) detail.append(commerceText('p', `${line.title} ${line.variantTitle} · SKU ${line.sku || '—'} · ${line.quantity} buc. comandate / ${line.currentQuantity} curente`));
        detail.append(commerceText('p', `Total curent: ${moneyLabel(order.totals.current)} · Încasat: ${moneyLabel(order.totals.received)} · Rambursat: ${moneyLabel(order.totals.refunded)}`));
        detail.append(commerceText('p', `Contact: ${order.email || 'indisponibil'} · ${order.phone || 'indisponibil'}`));
        for (const [label, address] of [['Livrare', order.shippingAddress], ['Facturare', order.billingAddress]]) detail.append(commerceText('p', `${label}: ${address ? Object.values(address).filter(Boolean).join(', ') : 'adresă indisponibilă'}`));
      })); if (commerceCanRead) card.append(button);
    } else if (kind === 'variant') card.append(commerceText('p', `${item.sku || 'Fără SKU'} · ${item.options.map(option => `${option.name}: ${option.value}`).join(', ')} · ${moneyLabel(item.price)}`));
    else if (kind === 'inventory') card.append(commerceText('p', `${item.available === null ? 'Stoc neurmărit' : `${item.available} disponibile`}${!item.locationActive ? ' · Locație inactivă' : ''}`));
    else card.append(commerceText('p', item.status));
    card.append(commerceText('small', `Observat: ${record.observedAt} UTC · versiunea ${record.version}`)); return card;
  }));
  if (!data.records.length) $('#commerce-records').append(commerceText('p', 'Nu există date importate în această categorie.'));
  if (run?.status === 'running' && !Number(run.failed)) commerceTimer = setTimeout(() => refreshCommerce(after).catch(error => {$('#commerce-progress').textContent = error.message;}), 5000);
}
async function startCommerce(full = false, restart = false) {
  const storeId = $('#commerce-store').value;
  const {connectionId: importConnection} = await api(`/api/commerce?storeId=${storeId}`);
  if (!importConnection) throw new Error('Conectează întâi magazinul la Shopify.');
  await api('/api/commerce/import', 'POST', {storeId, connectionId: importConnection, full, restart});
  await refreshCommerce();
}
$('#commerce-store').addEventListener('change', () => {$('#commerce-detail').replaceChildren(); action(() => refreshCommerce());});
$('#commerce-kind').addEventListener('change', () => action(() => refreshCommerce()));
$('#commerce-refresh').addEventListener('click', () => action(() => refreshCommerce()));
$('#commerce-next').addEventListener('click', () => action(() => refreshCommerce(commerceCursor)));
$('#commerce-start').addEventListener('click', () => action(() => startCommerce()));
$('#commerce-full').addEventListener('click', () => action(() => startCommerce(true)));
$('#commerce-restart').addEventListener('click', () => action(() => startCommerce(true, true)));
function renderPrivacy(events) {
  $('#shopify-privacy').replaceChildren(...events.filter(event => event.status === 'needs_review' && ['customers/data_request', 'customers/redact', 'shop/redact'].includes(event.topic)).map(event => {
    const card = document.createElement('article'); card.className = 'card';
    card.append(commerceText('h3', event.topic === 'customers/data_request' ? 'Cerere de acces la date' : 'Cerere de ștergere a datelor'), commerceText('p', `Primită: ${event.received_at} UTC · ${event.id}`));
    const request = name => api(`/api/commerce/privacy/${event.id}`, 'POST', {action: name});
    if (event.topic === 'customers/data_request') {
      const download = commerceText('button', 'Descarcă datele solicitate');
      download.addEventListener('click', () => action(async () => {
        const data = await request('export'); const url = URL.createObjectURL(new Blob([JSON.stringify(data, null, 2)], {type:'application/json'}));
        const link = document.createElement('a'); link.href = url; link.download = `ordely-privacy-${event.id}.json`; link.click(); setTimeout(() => URL.revokeObjectURL(url), 1000);
        $('#message').textContent = 'Export pregătit. Cererea rămâne deschisă până confirmi transmiterea către solicitant.';
      }));
      const delivered = commerceText('button', 'Confirmă că ai transmis răspunsul'); delivered.className = 'secondary';
      delivered.addEventListener('click', () => action(async () => {await request('confirm-delivered'); await refreshIntegrations();})); card.append(download, delivered);
    } else {
      const erase = commerceText('button', 'Șterge datele aferente cererii'); erase.className = 'secondary';
      erase.addEventListener('click', () => {if (window.confirm('Datele solicitate vor fi șterse din Ordely, iar reimportul lor va fi blocat. Confirmi procesarea cererii?')) action(async () => {await request('redact'); await refreshIntegrations(); await refreshCommerce();});});card.append(erase);
    }
    return card;
  }));
}
refresh().catch(error => { if ($('#login').hidden) $('#message').textContent = error.message; });
