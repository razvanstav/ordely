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
    if (response.status === 401) { $('#login').hidden = false; $('#workspace').hidden = true; csrf = ''; clearSecretInputs(); }
    const messages = {invalid_credentials: 'Email sau parolă incorectă.', unauthenticated: 'Conectează-te pentru a continua.', forbidden: 'Nu ai permisiune pentru această acțiune.', invalid_csrf: 'Sesiunea s-a schimbat. Reîncarcă pagina.', too_many_attempts: 'Prea multe încercări. Reîncearcă peste 15 minute.'};
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
}
async function refreshIntegrations() {
  const {providers, connections} = await api('/api/integrations');
  $('#provider-roadmap').textContent = `Planificate: ${providers.filter(provider => !provider.available).map(provider => provider.label).join(', ')}.`;
  const available = providers.filter(provider => provider.available);
  $('#connection-form').hidden = !available.length;
  $('#connection-provider').replaceChildren(...available.map(provider => { const option = document.createElement('option'); option.value = provider.key; option.textContent = provider.label; return option; }));
  $('#connections').replaceChildren(...connections.map(connection => {
    const card = document.createElement('article'); card.className = 'card';
    const title = document.createElement('h3'); title.textContent = connection.label;
    const status = document.createElement('p'); status.textContent = `${connection.provider} · ${connection.status === 'active' ? 'Disponibilă în simulator' : 'Revocată'}`;
    const key = document.createElement('p'); key.className = 'hint'; key.textContent = `Cheie de criptare: ${connection.keyId} · versiunea ${connection.version}`;
    card.append(title, status, key);
    const command = async (name, values = {}) => { await api(`/api/integrations/${connection.id}/${name}`, 'POST', {version: connection.version, ...values}); await refreshIntegrations(); };
    const rotate = document.createElement('button'); rotate.textContent = 'Recriptează cu cheia activă'; rotate.addEventListener('click', () => action(() => command('rotate')));
    card.append(rotate);
    if (connection.status !== 'active') return card;
    const revoke = document.createElement('button'); revoke.className = 'secondary'; revoke.textContent = 'Revocă accesul'; revoke.addEventListener('click', () => action(() => command('revoke')));
    card.append(revoke);
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
$('#login-form').addEventListener('submit', event => { event.preventDefault(); action(async () => { const data = Object.fromEntries(new FormData(event.target)); const result = await api('/api/auth/login', 'POST', data); csrf = result.csrf; event.target.reset(); await refresh(); }); });
$('#store-form').addEventListener('input', () => { storeKey = null; });
$('#store-form').addEventListener('submit', event => { event.preventDefault(); action(async () => { storeKey ??= crypto.randomUUID(); await api('/api/stores', 'POST', Object.fromEntries(new FormData(event.target)), storeKey); storeKey = null; event.target.reset(); await refresh(); }); });
$('#merchants').addEventListener('change', event => action(async () => { clearSecretInputs(); const result = await api('/api/auth/merchant', 'POST', {merchantId: event.target.value}); csrf = result.csrf; connectionId = null; storeKey = null; $('#connection-form').reset(); await refresh(); }));
$('#logout').addEventListener('click', () => action(async () => { await api('/api/auth/logout', 'POST', {}); clearSecretInputs(); csrf = ''; $('#login').hidden = false; $('#workspace').hidden = true; }));
$('#refresh-operations').addEventListener('click', () => action(refreshOperations));
$('#refresh-integrations').addEventListener('click', () => action(refreshIntegrations));
$('#connection-form').addEventListener('input', () => { connectionId = null; });
$('#connection-form').addEventListener('submit', event => { event.preventDefault(); action(async () => { connectionId ??= crypto.randomUUID().replaceAll('-', ''); const data = Object.fromEntries(new FormData(event.target)); await api('/api/integrations', 'POST', {id: connectionId, provider: data.provider, label: data.label, credentials: {apiToken: data.apiToken}}); connectionId = null; event.target.reset(); await refreshIntegrations(); }); });
refresh().catch(error => { if ($('#login').hidden) $('#message').textContent = error.message; });
