'use strict';
const form = document.querySelector('#shopify-connect');
const status = document.querySelector('#shopify-status');
const relink = document.querySelector('#shopify-relink');
async function callShopify(path, body, retry = true) {
  const token = await shopify.idToken();
  const response = await fetch(path, {method: body ? 'POST' : 'GET', credentials: 'omit', headers: {'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json'}, body: body ? JSON.stringify(body) : undefined});
  if (response.status === 401 && retry) return callShopify(path, body, false);
  const result = await response.json();
  if (!response.ok) {
    const messages = {invalid_shopify_identity: 'Sesiunea Shopify a expirat. Redeschide aplicația.', forbidden: 'Codul a expirat sau nu corespunde magazinului. Generează un cod nou în Ordely.', conflict: 'Acest cod a fost deja folosit. Generează unul nou.', shopify_unavailable: 'Shopify nu răspunde momentan. Reîncearcă.', shopify_reauthorization_required: 'Accesul trebuie reautorizat. Redeschide aplicația și folosește un cod Ordely nou.'};
    throw new Error(messages[result.error] || 'Conectarea nu a reușit. Verifică magazinul și codul Ordely.');
  }
  return result;
}
function show(result) {
  document.querySelector('#shop-name').textContent = result.shop;
  status.textContent = result.connected ? 'Magazin conectat la Ordely.' : 'Magazinul nu este încă legat la un cont Ordely.';
  form.hidden = result.connected; relink.hidden = !result.connected;
}
form.addEventListener('submit', async event => {
  event.preventDefault(); const button = form.querySelector('button'); button.disabled = true;
  const code = form.elements.code.value; form.reset();
  try { show(await callShopify('/shopify/connect', {code})); } catch (error) { status.textContent = error.message; }
  finally { button.disabled = false; }
});
relink.addEventListener('click', () => {form.hidden = false; relink.hidden = true;});
callShopify('/shopify/status').then(show).catch(error => {status.textContent = error.message;});
