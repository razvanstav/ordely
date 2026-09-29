'use strict';
let csrf = '';
let storeKey = null;
let connectionId = null;
let visibleStores = [];
let connectionProviders = [];
let integrationGeneration = 0;
let currentUser = null;
let currentConnections = null;
let operationItems = [];
let selectedConnectionProvider = null;
const $ = selector => document.querySelector(selector);
const pages = {
  overview: {name:'Acasă', title:'Totul la locul lui.', description:'Un loc pentru activitățile de zi cu zi și configurarea magazinului.', eyebrow:'SPAȚIUL TĂU DE LUCRU'},
  commerce: {name:'Comenzi și catalog', title:'Comenzile și produsele tale.', description:'Consultă datele importate și sincronizează magazinul când ai nevoie.', eyebrow:'ACTIVITATE ZILNICĂ'},
  invoicing: {name:'Facturare', title:'Facturare, pas cu pas.', description:'Pregătește ciornele și revino la ele atunci când ai nevoie.', eyebrow:'ACTIVITATE ZILNICĂ'},
  stores: {name:'Magazine', title:'Magazinele tale.', description:'Aici alegi magazinele pe care le gestionezi în Ordely.', eyebrow:'CONFIGURARE'},
  integrations: {name:'Integrări', title:'Serviciile tale, conectate.', description:'Gestionează Shopify și Oblio dintr-un singur loc.', eyebrow:'CONFIGURARE'},
  activity: {name:'Activitate sistem', title:'Ce se întâmplă în fundal.', description:'Verifică sincronizările și înregistrările care au nevoie de atenție.', eyebrow:'DIAGNOSTIC ȘI SUPORT'}
};
function pageAllowed(name) {
  if (!currentUser || !Object.hasOwn(pages, name)) return false;
  if (name === 'invoicing') return currentUser.role !== 'viewer';
  if (name === 'activity') return ['owner', 'admin'].includes(currentUser.role);
  if (name === 'integrations') return ['owner', 'admin'].includes(currentUser.role) && currentUser.allStores;
  return true;
}
function showPage(name, focus = false) {
  if (!currentUser) return;
  if (!pageAllowed(name)) { name = 'overview'; history.replaceState(null, '', '#overview'); }
  const page = pages[name];
  document.querySelectorAll('[data-page]').forEach(section => {section.hidden = section.dataset.page !== name;});
  document.querySelectorAll('[data-route]').forEach(link => {
    link.hidden = !pageAllowed(link.dataset.route);
    if (link.dataset.route === name) link.setAttribute('aria-current', 'page'); else link.removeAttribute('aria-current');
  });
  $('#page-name').textContent = page.name; $('#page-title').textContent = page.title;
  $('#page-description').textContent = page.description; $('#page-eyebrow').textContent = page.eyebrow;
  document.title = `Ordely · ${page.name}`;
  if (focus) {$('#page-title').focus({preventScroll:true}); window.scrollTo({top:0, behavior:'instant'});}
}
function navigate(name) {
  if (!pageAllowed(name)) return;
  if (location.hash === `#${name}`) showPage(name, true); else location.hash = name;
}
document.addEventListener('click', event => {
  const link = event.target.closest('[data-route]');
  if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
  event.preventDefault(); navigate(link.dataset.route);
});
window.addEventListener('hashchange', () => {if (location.hash !== '#page-title') showPage(location.hash.slice(1) || 'overview', true);});
function makeIcon(name) {
  const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg'); svg.classList.add('icon'); svg.setAttribute('aria-hidden', 'true');
  const use = document.createElementNS('http://www.w3.org/2000/svg', 'use'); use.setAttribute('href', `#icon-${name}`); svg.append(use); return svg;
}
function emptyState(title, description) {
  const panel = document.createElement('div'); panel.className = 'empty-state';
  panel.append(makeIcon('check'), commerceText('h2', title), commerceText('p', description)); return panel;
}
function disclosure(label) {const details = document.createElement('details'); details.className = 'panel disclosure'; details.append(commerceText('summary', label)); return details;}
function renderSetupGuide() {
  $('#overview-stores').textContent = String(visibleStores.length);
  $('#setup-guide').hidden = !pageAllowed('integrations');
  if (!pageAllowed('integrations') || currentConnections === null) return;
  const connected = provider => currentConnections.some(connection => connection.provider === provider && connection.status === 'active' && connection.bindings.length);
  const steps = [
    {title:'Adaugă magazinul', detail:'Locul din care îți organizezi activitatea.', done:visibleStores.length > 0, route:'stores', action:'Adaugă magazin'},
    {title:'Conectează Shopify', detail:'Importă comenzile, produsele și stocurile.', done:connected('shopify'), provider:'shopify', action:'Conectează'},
    {title:'Conectează Oblio', detail:'Pregătește accesul la contul de facturare.', done:connected('oblio'), provider:'oblio', action:'Configurează →'}
  ];
  $('#setup-count').textContent = `${steps.filter(step => step.done).length} / 3`;
  $('#setup-steps').replaceChildren(...steps.map((step, index) => {
    const row = document.createElement('div'); row.className = `setup-step${step.done ? ' done' : ''}`;
    const number = commerceText('span', step.done ? '' : String(index + 1)); number.className = 'step-number'; if (step.done) number.append(makeIcon('check'));
    const copy = document.createElement('div'); copy.className = 'step-copy'; copy.append(commerceText('strong', step.title), commerceText('p', step.detail)); row.append(number, copy);
    if (step.done) {const state = commerceText('span', index ? 'Conexiune salvată' : 'Adăugat'); state.className = 'step-state'; row.append(state);}
    else {const button = commerceText('button', step.action); button.className = 'text-button'; button.addEventListener('click', () => {if (step.provider) showConnectionSetup(step.provider); else {navigate(step.route); $('#store-create').open = true;}}); row.append(button);}
    return row;
  }));
}
function showConnectionSetup(provider) {
  if (!pageAllowed('integrations')) return;
  // Update the URL without a later hashchange stealing focus from the setup form.
  if (location.hash !== '#integrations') history.pushState(null, '', '#integrations');
  showPage('integrations'); selectedConnectionProvider = provider;
  $('#connection-setup').hidden = false; $('#shopify-setup').hidden = provider !== 'shopify'; $('#connection-form').hidden = provider === 'shopify';
  $('#connection-setup-title').textContent = provider === 'shopify' ? 'Conectează Shopify' : 'Conectează Oblio';
  if (provider !== 'shopify') {
    $('#connection-provider').value = provider; renderConnectionFields();
    if (!$('#connection-form input[name="label"]').value) $('#connection-form input[name="label"]').value = 'Oblio local';
  }
  $('#connection-setup-title').focus({preventScroll:true}); $('#connection-setup').scrollIntoView({block:'nearest'});
}
document.querySelectorAll('[data-connect]').forEach(button => button.addEventListener('click', () => showConnectionSetup(button.dataset.connect)));
$('#connection-setup-close').addEventListener('click', () => {
  const trigger = $(`[data-connect="${selectedConnectionProvider || 'oblio'}"]`);
  $('#connection-setup').hidden = true; selectedConnectionProvider = null; clearSecretInputs(); $('#shopify-code-label').hidden = true;
  trigger?.focus();
});
async function api(path, method = 'GET', data, idempotencyKey) {
  const headers = {'Content-Type': 'application/json', 'X-CSRF-Token': csrf};
  if (idempotencyKey) headers['Idempotency-Key'] = idempotencyKey;
  const response = await fetch(path, {method, credentials: 'same-origin', headers, body: data === undefined ? undefined : JSON.stringify(data)});
  const result = await response.json();
  if (!response.ok) {
    if (response.status === 401) { $('#login').hidden = false; $('#workspace').hidden = true; csrf = ''; clearSecretInputs(); document.dispatchEvent(new Event('ordely:logout')); }
    if (response.status === 409) throw new Error('Datele nu mai corespund versiunii salvate. Redeschide elementul înainte de a încerca din nou.');
    const messages = {invalid_credentials: 'Email sau parolă incorectă.', unauthenticated: 'Conectează-te pentru a continua.', forbidden: 'Nu ai permisiune pentru această acțiune.', invalid_csrf: 'Sesiunea s-a schimbat. Reîncarcă pagina.', too_many_attempts: 'Prea multe încercări. Reîncearcă peste 15 minute.', shopify_reauthorization_required: 'Accesul Shopify a fost revocat. Generează un cod nou și reconectează aplicația din Shopify.', shopify_unavailable: 'Shopify nu răspunde momentan. Reîncearcă.', shopify_not_configured: 'Integrarea Shopify trebuie configurată pe server.'};
    const invoiceErrors = {invoice_provider_authentication: 'Oblio a refuzat autentificarea. Verifică emailul și cheia API.', invoice_provider_transient: 'Oblio nu a furnizat un răspuns valid sau limita de cereri a fost atinsă. Reîncearcă mai târziu.', invoice_provider_validation: 'Firma aleasă nu mai este disponibilă în acest cont. Recitește firmele.', invoice_provider_unsupported: 'Această funcție nu este disponibilă pentru conexiune.'};
    throw new Error(messages[result.error] || invoiceErrors[result.error] || 'Acțiunea nu a reușit. Verifică datele și reîncearcă.');
  }
  return result;
}
async function refresh() {
  const me = await api('/api/me'); csrf = me.csrf;
  currentUser = me;
  $('#login').hidden = true; $('#workspace').hidden = false;
  $('#role').textContent = ({owner:'Proprietar',admin:'Administrator',operator:'Operator',finance:'Financiar',viewer:'Vizualizare'})[me.role] || me.role;
  $('#workspace-name').textContent = me.merchants.find(merchant => merchant.id === me.merchantId)?.name || '';
  $('#merchants').replaceChildren(...me.merchants.map(merchant => {
    const option = document.createElement('option'); option.value = merchant.id; option.textContent = merchant.name; option.selected = merchant.id === me.merchantId; return option;
  }));
  const {stores} = await api('/api/stores');
  visibleStores = stores;
  showPage(location.hash.slice(1) || 'overview'); renderSetupGuide();
  document.dispatchEvent(new CustomEvent('ordely:context', {detail: {me, stores}}));
  $('#stores').replaceChildren(...stores.map(store => {
    const card = document.createElement('article'); card.className = 'card';
    const title = document.createElement('h2'); title.textContent = store.name;
    const platform = document.createElement('p'); platform.textContent = store.platform === 'shopify' ? 'Magazin Shopify' : 'Magazin manual / test';
    card.append(title, platform);
    if (pageAllowed('integrations')) {const manage = commerceText('button', 'Gestionează integrările'); manage.className = 'secondary'; manage.addEventListener('click', () => navigate('integrations')); card.append(manage);}
    return card;
  }));
  if (!stores.length) { const empty = document.createElement('p'); empty.textContent = 'Nu există magazine disponibile pentru acest cont.'; $('#stores').append(empty); }
  $('#store-form').hidden = !['owner', 'admin'].includes(me.role);
  $('#store-create').hidden = $('#store-form').hidden;
  $('#operations-panel').hidden = !['owner', 'admin'].includes(me.role);
  if (!$('#operations-panel').hidden) await refreshOperations();
  $('#integrations-panel').hidden = !(['owner', 'admin'].includes(me.role) && me.allStores);
  if (!$('#integrations-panel').hidden) await refreshIntegrations();
  await prepareCommerce(me);
  renderSetupGuide();
}
async function refreshIntegrations() {
  const generation = ++integrationGeneration;
  const {providers, connections} = await api('/api/integrations');
  if (generation !== integrationGeneration) return;
  connectionProviders = providers;
  currentConnections = connections; renderSetupGuide();
  $('#provider-roadmap').textContent = `Shopify: conectare și import comenzi/catalog. Alte integrări planificate: ${providers.filter(provider => !provider.available && provider.key !== 'shopify').map(provider => provider.label).join(', ')}.`;
  const shopifyStores = visibleStores.filter(store => store.platform === 'shopify');
  $('#shopify-store').replaceChildren(...shopifyStores.map(store => {const option = document.createElement('option'); option.value = store.id; option.textContent = store.name; return option;}));
  $('#shopify-intent-form').hidden = !shopifyStores.length;
  $('#shopify-needs-store').hidden = !!shopifyStores.length;
  const available = providers.filter(provider => provider.available);
  $('#connection-form').hidden = !available.length || selectedConnectionProvider !== 'oblio';
  const selectedProvider = $('#connection-provider').value;
  $('#connection-provider').replaceChildren(...available.map(provider => { const option = document.createElement('option'); option.value = provider.key; option.textContent = provider.label; option.selected = provider.key === selectedProvider; return option; }));
  $('#connection-provider-label').hidden = available.length <= 1;
  renderConnectionFields();
  const cards = connections.map(connection => {
    const card = document.createElement('article'); card.className = 'card';
    const heading = document.createElement('div'); heading.className = 'connection-header';
    const logo = commerceText('span', connection.provider === 'shopify' ? 'S' : connection.provider === 'oblio' ? 'o' : '↔'); logo.className = `provider-logo ${connection.provider === 'shopify' ? 'shopify-logo' : 'oblio-logo'}`;
    const headingText = document.createElement('div');
    const title = document.createElement('h3'); title.textContent = connection.label;
    const status = document.createElement('p'); status.textContent = connection.status === 'active' ? (connection.provider === 'shopify' ? 'Conexiune activă' : connection.provider === 'oblio' ? 'Salvată · verifică accesul mai jos' : 'Conexiune de test') : 'Conexiune dezactivată';
    const key = document.createElement('p'); key.className = 'hint'; key.textContent = `Cheie de criptare: ${connection.keyId} · versiunea ${connection.version}`;
    headingText.append(title, status); heading.append(logo, headingText); card.append(heading);
    const advanced = disclosure('Setări avansate'); advanced.append(key); card.append(advanced);
    const primary = element => card.insertBefore(element, advanced);
    const command = async (name, values = {}) => { await api(`/api/integrations/${connection.id}/${name}`, 'POST', {version: connection.version, ...values}); await refreshIntegrations(); };
    const maintenance = document.createElement('div'); maintenance.className = 'button-row'; advanced.append(maintenance);
    const rotate = document.createElement('button'); rotate.className = 'secondary'; rotate.textContent = 'Recriptează cu cheia activă'; rotate.addEventListener('click', () => action(() => command('rotate')));
    maintenance.append(rotate);
    if (connection.status !== 'active') return card;
    const revoke = document.createElement('button'); revoke.className = 'secondary danger'; revoke.textContent = 'Dezactivează conexiunea'; revoke.addEventListener('click', () => action(() => command('revoke')));
    maintenance.append(revoke);
    if (connection.provider === 'shopify') {
      for (const association of connection.bindings) {
        const name = document.createElement('p'); name.className = 'connection-store'; name.textContent = `Magazin: ${visibleStores.find(store => store.id === association.storeId)?.name || 'indisponibil'}`; primary(name);
        for (const [label, refresh] of [['Verifică accesul', false], ['Reînnoiește accesul', true]]) {
          const button = document.createElement('button'); button.className = 'secondary'; button.textContent = label;
          button.addEventListener('click', () => action(async () => {
            try { await api('/api/shopify/check', 'POST', {storeId: association.storeId, connectionId: connection.id, refresh}); $('#message').textContent = refresh ? 'Accesul Shopify a fost reînnoit.' : 'Accesul Shopify este valid.'; }
            finally { await refreshIntegrations(); }
          })); if (refresh) advanced.append(button); else primary(button);
        }
      }
      return card;
    }
    const replacement = document.createElement('form'); replacement.autocomplete = 'off';
    const definition = providers.find(provider => provider.key === connection.provider);
    credentialFields(replacement, definition);
    const replace = document.createElement('button'); replace.textContent = 'Actualizează datele de acces'; replacement.append(replace);
    replacement.addEventListener('submit', event => { event.preventDefault(); const credentials = Object.fromEntries(new FormData(replacement)); replacement.reset(); action(() => command('credentials', {credentials})); }); advanced.append(replacement);
    const binding = document.createElement('form'); const storeLabel = document.createElement('label'); storeLabel.textContent = 'Magazin'; const select = document.createElement('select');
    select.replaceChildren(...visibleStores.map(store => { const option = document.createElement('option'); option.value = store.id; option.textContent = store.name; return option; })); storeLabel.append(select);
    const bind = document.createElement('button'); bind.textContent = 'Folosește pentru acest magazin'; bind.disabled = !visibleStores.length; binding.append(storeLabel, bind);
    binding.addEventListener('submit', event => { event.preventDefault(); action(() => command('bind', {storeId: select.value, isDefault: true})); });
    if (connection.bindings.length) advanced.append(binding); else {primary(commerceText('p', 'Pasul următor: alege magazinul pentru această conexiune.')); primary(binding);}
    for (const association of connection.bindings) {
      const row = document.createElement('div'); row.className = 'button-row'; const name = visibleStores.find(store => store.id === association.storeId)?.name || 'Magazin indisponibil';
      const storeName = commerceText('p', `Magazin: ${name}${Number(association.isDefault) ? ' · conexiune principală' : ''}`); storeName.className = 'connection-store'; primary(storeName);
      const unbind = document.createElement('button'); unbind.className = 'secondary'; unbind.textContent = 'Dezasociază'; unbind.addEventListener('click', () => action(() => command('unbind', {storeId: association.storeId})));
      const capabilities = document.createElement('button'); capabilities.className = 'secondary'; capabilities.textContent = 'Verifică funcțiile'; capabilities.addEventListener('click', () => action(async () => { const result = await api(`/api/integrations/${connection.id}/capabilities`, 'POST', {storeId: association.storeId, kind: connection.kind}); if (card.isConnected) $('#message').textContent = connection.provider === 'oblio' ? 'Oblio permite momentan citirea firmelor, seriilor și cotelor TVA.' : `Funcții disponibile: ${result.features.join(', ')}.`; })); row.append(unbind, capabilities); advanced.append(commerceText('p', name), row);
      if (connection.provider === 'oblio') primary(invoiceConfigurationPanel(connection, association.storeId, name));
    }
    return card;
  });
  $('#connections').replaceChildren(...cards.filter((card, index) => connections[index].status === 'active'));
  $('#archived-connections').replaceChildren(...cards.filter((card, index) => connections[index].status !== 'active'));
  $('#previous-connections').hidden = !$('#archived-connections').children.length;
  $('#previous-connections-count').textContent = String($('#archived-connections').children.length);
  if (!$('#connections').children.length) $('#connections').append(emptyState('Conectează primul serviciu', 'Alege Shopify sau Oblio de mai jos. Te ghidăm prin pașii de conectare.'));
  try {const {events} = await api('/api/shopify/events'); if (generation !== integrationGeneration) return; const pending = events.filter(event => event.status === 'needs_review').length; $('#shopify-events').textContent = `Evenimente Shopify recente: ${events.length}. Solicitări de verificat: ${pending}.`; renderPrivacy(events);}
  catch {if (generation === integrationGeneration) $('#shopify-events').textContent = 'Configurează integrarea Shopify pe server pentru conectare.';}
}
function credentialFields(container, provider) {
  container.replaceChildren();
  for (const field of provider?.credentialFields || []) {
    const label = document.createElement('label'); label.textContent = ({clientId:'Email cont Oblio', clientSecret:'Cheie API Oblio', apiToken:'Token de test'})[field] || field;
    const input = document.createElement('input'); input.name = field; input.dataset.credential = 'true'; input.required = true; input.type = field === 'clientId' ? 'email' : 'password'; input.maxLength = field === 'clientId' ? 254 : 4096; input.autocomplete = field === 'clientId' ? 'off' : 'new-password'; label.append(input); container.append(label);
  }
}
function renderConnectionFields() {
  const provider = connectionProviders.find(item => item.key === $('#connection-provider').value);
  credentialFields($('#connection-credentials'), provider);
  $('#connection-help').textContent = provider?.key === 'oblio' ? 'Găsești emailul și cheia API în Oblio → Setări → Date cont. După salvare, alegi magazinul și verifici firma, seriile și cotele TVA.' : 'Simulatoarele nu trimit date furnizorilor. Folosește un token inventat.';
}
function invoiceConfigurationPanel(connection, storeId, storeName) {
  const panel = document.createElement('section'); panel.append(commerceText('h4', `Configurare Oblio · ${storeName}`));
  const button = commerceText('button', 'Citește firmele Oblio'); button.type = 'button';
  const result = document.createElement('div'); result.setAttribute('aria-live', 'polite'); panel.append(button, result);
  const read = companyId => api('/api/invoice-configuration', 'POST', {connectionId:connection.id, storeId, version:connection.version, ...(companyId ? {companyId} : {})});
  button.addEventListener('click', () => action(async () => {
    result.replaceChildren(); const data = await read(); if (!panel.isConnected) return;
    result.append(commerceText('p', `Acces verificat acum · ${data.companies.length} firme disponibile.`));
    if (!data.companies.length) return;
    const form = document.createElement('form'), label = commerceText('label', 'Firmă de verificat'), select = document.createElement('select'); select.required = true;
    const placeholder = document.createElement('option'); placeholder.value = ''; placeholder.textContent = 'Alege firma'; select.append(placeholder);
    for (const company of data.companies) {const option = document.createElement('option'); option.value = company.id; option.textContent = `${company.name} · ${company.id}`; select.append(option);}
    const show = commerceText('button', 'Citește seriile și TVA'); label.append(select); form.append(label, show);
    const details = document.createElement('div'); select.addEventListener('change', () => details.replaceChildren());
    form.addEventListener('submit', event => {event.preventDefault(); const company = select.value; action(async () => {
      details.replaceChildren(); const catalog = await read(company); if (!panel.isConnected || company !== select.value) return;
      details.append(commerceText('p', 'Citire reușită. Datele afișate nu sunt încă un profil de emitere salvat.'), commerceText('h4', 'Serii de factură'));
      for (const series of catalog.series) details.append(commerceText('p', `${series.name}${series.default ? ' · implicită în Oblio' : ''}`));
      if (!catalog.series.length) details.append(commerceText('p', 'Nu există serii de factură disponibile.'));
      details.append(commerceText('h4', 'Cote TVA din cont'));
      for (const rate of catalog.taxRates) details.append(commerceText('p', `${rate.name} · ${rate.percent}%${rate.default ? ' · implicită în Oblio' : ''}`));
      if (!catalog.taxRates.length) details.append(commerceText('p', 'Nu există cote TVA disponibile.'));
    });}); result.append(form, details);
  })); return panel;
}
async function refreshOperations() {
  const merchant = currentUser?.merchantId;
  const data = await api('/api/operations');
  if (merchant !== currentUser?.merchantId) return;
  operationItems = [...data.jobs.map(job => ({...job, job:true})), ...data.operations];
  renderOperations();
}
function renderOperations() {
  const statuses = {READY: 'În așteptare', RUNNING: 'În lucru', SUCCEEDED: 'Finalizat', DEAD: 'Necesită atenție', PENDING: 'În așteptare', IN_FLIGHT: 'În curs', CONFIRMED: 'Confirmat', RETRYABLE: 'Reluare programată', FAILED: 'Eșuat', UNKNOWN: 'Rezultat de verificat'};
  const needsAttention = item => ['DEAD', 'FAILED', 'UNKNOWN'].includes(item.status);
  const count = operationItems.filter(needsAttention).length;
  $('#activity-summary').textContent = `${operationItems.length} înregistrări recente · ${count} de verificat`;
  const filtered = $('#activity-filter').value === 'attention' ? operationItems.filter(needsAttention) : operationItems;
  const cards = filtered.map(item => {
    const card = document.createElement('article'); card.className = 'card';
    const title = document.createElement('h3'); title.textContent = statuses[item.status] || item.status;
    const label = commerceText('p', item.type === 'commerce.import' ? 'Sincronizare comenzi și catalog' : item.type.startsWith('shopify.') ? 'Actualizare Shopify' : 'Operațiune automată');
    const details = disclosure('Detalii tehnice');
    details.append(commerceText('p', `${item.type} · încercări: ${item.attempt_count}`));
    const reference = document.createElement('p'); reference.className = 'hint'; reference.textContent = `Referință: ${item.id}`;
    details.append(reference); card.append(title, label, details);
    if (item.job && item.status === 'DEAD' && ['transient', 'lease_expired'].includes(item.last_error)) {
      const button = document.createElement('button'); button.textContent = 'Reia lucrarea';
      button.addEventListener('click', () => action(async () => { await api(`/api/jobs/${item.id}/retry`, 'POST', {}); await refreshOperations(); })); card.append(button);
    }
    return card;
  });
  $('#operations').replaceChildren(...cards);
  if (!cards.length) $('#operations').append(emptyState($('#activity-filter').value === 'attention' ? 'Totul este în ordine aici.' : 'Încă nu există activitate.', $('#activity-filter').value === 'attention' ? 'Nu sunt înregistrări care necesită atenție în activitatea recentă. Poți deschide istoricul complet din filtrul de mai sus.' : 'Sincronizările și operațiunile automate vor apărea în acest loc.'));
}
async function action(work) {
  $('#message').textContent = '';
  const buttons = [...document.querySelectorAll('button')]; buttons.forEach(button => button.disabled = true);
  try { await work(); } catch (error) { $('#message').textContent = error.message; }
  finally { buttons.forEach(button => button.disabled = false); }
}
function clearSecretInputs() { document.querySelectorAll('input[type="password"], input[data-credential]').forEach(input => { input.value = ''; }); }
document.addEventListener('ordely:logout', () => {
  integrationGeneration++; currentUser = null; currentConnections = null; operationItems = []; selectedConnectionProvider = null;
  $('#connections').replaceChildren(); $('#archived-connections').replaceChildren(); $('#operations').replaceChildren(); $('#setup-steps').replaceChildren();
  $('#connection-setup').hidden = true; $('#shopify-code-label').hidden = true;
  $('#overview-orders').textContent = '—'; $('#overview-products').textContent = '—';
  clearSecretInputs();
});
$('#shopify-intent-form').addEventListener('submit', event => {event.preventDefault(); action(async () => {const result = await api('/api/shopify/intents', 'POST', Object.fromEntries(new FormData(event.target))); $('#shopify-code').value = result.code; $('#shopify-code-label').hidden = false; $('#message').textContent = 'Cod pregătit. Copiază-l în aplicația Ordely din Shopify.';});});
$('#shopify-copy').addEventListener('click', () => action(async () => {await navigator.clipboard.writeText($('#shopify-code').value); $('#message').textContent = 'Cod copiat.';}));
$('#login-form').addEventListener('submit', event => { event.preventDefault(); action(async () => { const data = Object.fromEntries(new FormData(event.target)); const result = await api('/api/auth/login', 'POST', data); csrf = result.csrf; event.target.reset(); await refresh(); }); });
$('#store-form').addEventListener('input', () => { storeKey = null; });
$('#store-form').addEventListener('submit', event => { event.preventDefault(); action(async () => { storeKey ??= crypto.randomUUID(); await api('/api/stores', 'POST', Object.fromEntries(new FormData(event.target)), storeKey); storeKey = null; event.target.reset(); await refresh(); }); });
$('#merchants').addEventListener('change', event => action(async () => { clearSecretInputs(); document.dispatchEvent(new Event('ordely:logout')); const result = await api('/api/auth/merchant', 'POST', {merchantId: event.target.value}); csrf = result.csrf; connectionId = null; storeKey = null; $('#connection-form').reset(); await refresh(); }));
$('#logout').addEventListener('click', () => action(async () => { await api('/api/auth/logout', 'POST', {}); document.dispatchEvent(new Event('ordely:logout')); clearSecretInputs(); csrf = ''; $('#login').hidden = false; $('#workspace').hidden = true; }));
$('#refresh-operations').addEventListener('click', () => action(refreshOperations));
$('#activity-filter').addEventListener('change', renderOperations);
$('#refresh-integrations').addEventListener('click', () => action(refreshIntegrations));
$('#connection-form').addEventListener('input', () => { connectionId = null; });
$('#connection-provider').addEventListener('change', renderConnectionFields);
$('#connection-form').addEventListener('submit', event => { event.preventDefault(); action(async () => { connectionId ??= crypto.randomUUID().replaceAll('-', ''); const data = Object.fromEntries(new FormData(event.target)); const credentials = Object.fromEntries((connectionProviders.find(provider => provider.key === data.provider)?.credentialFields || []).map(field => [field, data[field]])); clearSecretInputs(); await api('/api/integrations', 'POST', {id: connectionId, provider: data.provider, label: data.label, credentials}); connectionId = null; event.target.reset(); await refreshIntegrations(); }); });
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
  $('#commerce-empty').hidden = !!stores.length;
  $('#commerce-actions').hidden = !['owner', 'admin'].includes(me.role);
  $('#commerce-advanced-actions').hidden = $('#commerce-actions').hidden;
  $('#commerce-store').replaceChildren(...stores.map(store => {const option = document.createElement('option'); option.value = store.id; option.textContent = store.name; return option;}));
  if (stores.length) await refreshCommerce();
  else document.querySelectorAll('.overview-source').forEach(element => {element.textContent = 'Niciun magazin Shopify adăugat';});
}
function commerceText(tag, text) {const element = document.createElement(tag); element.textContent = text; return element;}
function moneyLabel(money) {
  const negative = money.minor.startsWith('-');
  const digits = money.minor.replace('-', '').padStart(money.exponent + 1, '0');
  const value = money.exponent ? `${digits.slice(0, -money.exponent)},${digits.slice(-money.exponent)}` : digits;
  return `${negative ? '-' : ''}${value} ${money.currency}`;
}
function displayTime(value) {if (!value) return ''; const date = new Date(value.replace(' ', 'T') + (/Z$|[+-]\d{2}:\d{2}$/.test(value) ? '' : 'Z')); return Number.isNaN(date.valueOf()) ? value : new Intl.DateTimeFormat('ro-RO', {day:'numeric',month:'short',hour:'2-digit',minute:'2-digit'}).format(date);}
function commerceStatus(value) {return ({PENDING:'Plată în așteptare',PAID:'Plătită',PARTIALLY_PAID:'Plătită parțial',REFUNDED:'Rambursată',PARTIALLY_REFUNDED:'Rambursată parțial',AUTHORIZED:'Plată autorizată',VOIDED:'Plată anulată',UNFULFILLED:'Neexpediată',FULFILLED:'Expediată',PARTIAL:'Expediată parțial',PARTIALLY_FULFILLED:'Expediată parțial',ON_HOLD:'În așteptare',RESTOCKED:'Reintroduse în stoc',ACTIVE:'Activ',DRAFT:'Ciornă',ARCHIVED:'Arhivat'})[value?.toUpperCase()] || value;}
async function refreshCommerce(after = null) {
  clearTimeout(commerceTimer);
  const generation = ++commerceGeneration, store = $('#commerce-store').value, kind = $('#commerce-kind').value;
  if (!store || $('#workspace').hidden) return;
  const data = await api(`/api/commerce?storeId=${store}&kind=${kind}${after ? `&after=${after}` : ''}`);
  if (generation !== commerceGeneration || store !== $('#commerce-store').value) return;
  commerceCursor = data.nextCursor;
  $('#commerce-next').hidden = !commerceCursor;
  const run = data.run;
  $('#commerce-progress').textContent = !run ? 'Nu există încă un import. Pornește sincronizarea pentru a aduce datele din Shopify.' : run.status === 'completed' ? `Ultima sincronizare: ${displayTime(run.completed_at)}` : run.status === 'cancelled' ? 'Import oprit.' : Number(run.failed) ? `Import incomplet · ${run.failed} lucrări necesită atenție. Verifică accesul Shopify și Activitate sistem.` : `Sincronizare în curs · ${run.done}/${run.tasks} pagini procesate. Datele apar la final.`;
  $('#commerce-restart').hidden = !run || run.status !== 'running';
  const labels = {order:'comenzi', product:'produse', variant:'variante', inventory:'poziții de stoc'};
  $('#commerce-counts').textContent = data.counts.map(item => `${item.count} ${labels[item.kind]}`).join(' · ');
  $('#overview-orders').textContent = String(data.counts.find(item => item.kind === 'order')?.count || 0);
  $('#overview-products').textContent = String(data.counts.find(item => item.kind === 'product')?.count || 0);
  document.querySelectorAll('.overview-source').forEach(element => {element.textContent = visibleStores.find(item => item.id === store)?.name || 'Magazin selectat';});
  $('#commerce-records').replaceChildren(...data.records.map(record => {
    const card = document.createElement('article'); card.className = 'card'; const item = record.data;
    card.append(commerceText('h3', (record.parentTitle ? `${record.parentTitle} · ` : '') + (item.number || item.title || item.locationName)));
    if (kind === 'order') {
      card.append(commerceText('p', `${moneyLabel(item.totals.current)} · ${commerceStatus(item.paymentStatus)} · ${commerceStatus(item.fulfillmentStatus)}${item.cancelledAt ? ' · Anulată' : ''}`));
      const button = commerceText('button', 'Detalii comandă'); button.className = 'secondary';
      button.addEventListener('click', () => action(async () => {
        const result = await api(`/api/commerce/orders/${record.id}?storeId=${store}`);
        if (store !== $('#commerce-store').value) return;
        const order = result.order, detail = $('#commerce-detail'); const heading = document.createElement('div'); heading.className = 'button-row';
        const title = commerceText('h3', order.number); title.tabIndex = -1;
        const close = commerceText('button', 'Închide detaliile'); close.className = 'secondary'; close.addEventListener('click', () => {detail.replaceChildren(); button.focus();});
        heading.append(title, close); detail.replaceChildren(heading);
        for (const line of order.lines) detail.append(commerceText('p', `${line.title} ${line.variantTitle} · SKU ${line.sku || '—'} · ${line.quantity} buc. comandate / ${line.currentQuantity} curente`));
        detail.append(commerceText('p', `Total curent: ${moneyLabel(order.totals.current)} · Încasat: ${moneyLabel(order.totals.received)} · Rambursat: ${moneyLabel(order.totals.refunded)}`));
        detail.append(commerceText('p', `Contact: ${order.email || 'indisponibil'} · ${order.phone || 'indisponibil'}`));
        for (const [label, address] of [['Livrare', order.shippingAddress], ['Facturare', order.billingAddress]]) detail.append(commerceText('p', `${label}: ${address ? Object.values(address).filter(Boolean).join(', ') : 'adresă indisponibilă'}`));
        title.focus({preventScroll:true}); detail.scrollIntoView({block:'start'});
      })); if (commerceCanRead) card.append(button);
    } else if (kind === 'variant') card.append(commerceText('p', `${item.sku || 'Fără SKU'} · ${item.options.map(option => `${option.name}: ${option.value}`).join(', ')} · ${moneyLabel(item.price)}`));
    else if (kind === 'inventory') card.append(commerceText('p', `${item.available === null ? 'Stoc neurmărit' : `${item.available} disponibile`}${!item.locationActive ? ' · Locație inactivă' : ''}`));
    else card.append(commerceText('p', commerceStatus(item.status)));
    card.append(commerceText('small', `Actualizat: ${displayTime(record.observedAt)}`)); return card;
  }));
  if (!data.records.length) $('#commerce-records').append(emptyState('Încă nu există date în această categorie.', 'Datele vor apărea după finalizarea sincronizării magazinului.'));
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
