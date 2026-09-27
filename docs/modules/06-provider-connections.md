# Modul 06 — Conexiuni și chei

2026-09-28. **REVIEW**. 05 închis și publicat la `cde58be`, CI verde.

## Criterii înainte de cod

- [ ] ProviderConnection separat de Store, asocieri explicite și FK merchant/type; versiuni pentru modificări concurente, revocare fără ștergerea istoricului.
- [ ] AES-256-GCM cu chei de 32 bytes în afara DB/Git, nonce aleator, tag verificat integral și context autentificat merchant/connection/provider/key/version.
- [ ] Rotația cheii de criptare și înlocuirea credențialelor păstrează istoricul; cheile vechi permit migrarea; lipsa cheii/tamper/swap eșuează închis.
- [ ] Registru cu contractele commerce/carrier/invoice, capabilities și resolver server-side. Fakes disponibile numai dev/test; furnizorii reali sunt vizibili ca planificați, fără conectare pretinsă.
- [ ] Acces owner/admin cu toate magazinele, verificat din DB; CSRF, tenant/store validation; conexiunile revocate sau neasociate blochează operațiunile/joburile vechi înainte de apel.
- [ ] UI/API minimal pentru creare, asociere, rotație și revocare; niciun secret/ciphertext returnat; audit allowlist fără credentials.
- [ ] Teste criptografice, MySQL/FK/rollback/versionare, HTTP, revocare și regresiile modulelor precedente; documente și CI Windows/Linux verzi înainte de DONE.

## Pași

1. Chei/criptare, schema și registrul.
2. Servicii, resolver și interfață minimală.
3. Teste, ghid de rotație, verificare completă, push și CI. Oprire înainte de 07.

Nu implementăm autentificarea Shopify sau API-urile curierilor/facturării în acest modul. Referințele externe și cursorii de import intră în 07/08, când există resursele aferente.

## Predare

Implementarea și suita locală sunt PASS: [raport](../testing/06-provider-connections.md), [operare/rotație](../integrations.md). Migrația 004 este aplicată local. Cheia aplicației există în `var/keys/keyring.json`, ignorată de Git; nu există conexiuni reale. Urmează publicarea codului și verificarea CI înainte de DONE. Oprire înainte de 07.
