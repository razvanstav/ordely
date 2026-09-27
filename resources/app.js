'use strict';
let csrf = '';
let storeKey = null;
const $ = selector => document.querySelector(selector);
async function api(path, method = 'GET', data, idempotencyKey) {
  const headers = {'Content-Type': 'application/json', 'X-CSRF-Token': csrf};
  if (idempotencyKey) headers['Idempotency-Key'] = idempotencyKey;
  const response = await fetch(path, {method, credentials: 'same-origin', headers, body: data === undefined ? undefined : JSON.stringify(data)});
  const result = await response.json();
  if (!response.ok) {
    if (response.status === 401) { $('#login').hidden = false; $('#workspace').hidden = true; csrf = ''; }
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
$('#login-form').addEventListener('submit', event => { event.preventDefault(); action(async () => { const data = Object.fromEntries(new FormData(event.target)); const result = await api('/api/auth/login', 'POST', data); csrf = result.csrf; event.target.reset(); await refresh(); }); });
$('#store-form').addEventListener('input', () => { storeKey = null; });
$('#store-form').addEventListener('submit', event => { event.preventDefault(); action(async () => { storeKey ??= crypto.randomUUID(); await api('/api/stores', 'POST', Object.fromEntries(new FormData(event.target)), storeKey); storeKey = null; event.target.reset(); await refresh(); }); });
$('#merchants').addEventListener('change', event => action(async () => { const result = await api('/api/auth/merchant', 'POST', {merchantId: event.target.value}); csrf = result.csrf; await refresh(); }));
$('#logout').addEventListener('click', () => action(async () => { await api('/api/auth/logout', 'POST', {}); csrf = ''; $('#login').hidden = false; $('#workspace').hidden = true; }));
$('#refresh-operations').addEventListener('click', () => action(refreshOperations));
refresh().catch(error => { if ($('#login').hidden) $('#message').textContent = error.message; });
