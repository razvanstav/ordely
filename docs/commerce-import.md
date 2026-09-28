# Import comenzi și catalog — modul 08

Implementarea este în verificare. Catalogul real este confirmat pe Ordely Shop. Fixture-ul ORDELY-TEST-M08 există: 30 linii/36,00 RON, citire/paginare/normalizare/criptare verificate. Importul și reimportul său persistat așteaptă legarea App Bridge pe PC-ul curent; acordurile sunt primite. [Starea exactă și reluarea](testing/08-commerce-import.md). Nu folosi ANATOMIK live.

## Operare

Aplică migrațiile cu `php bin/migrate.php`, păstrează keyring-ul existent și pornește serverul conform [Shopify](shopify.md). Scopes aplicației: `read_orders,read_customers,read_products,read_inventory,read_locations`. read_customers este necesar pentru order.customer.id, inclusiv asocierea cererilor privacy cu comenzile; lipsa lui a produs ACCESS_DENIED în proba reală. După schimbarea scopes în preview, reînnoiește accesul din Ordely. Cele cinci scope-uri au fost verificate real pe dev, fără write_orders. Nu sunt permisiuni de scriere.

În panoul Ordely, selectează magazinul în **Comenzi și catalog**, apoi **Sincronizează Shopify**. Rulează `php bin/worker.php 150`; procesul poate fi relansat până când coada este goală. Workerul trebuie să ruleze pentru ca butoanele să proceseze datele. În producție este necesar un supervisor, decis înainte de pilot.

Prima rulare citește comenzile disponibile și întregul catalog. Rulările următoare folosesc pentru comenzi watermark-ul ultimei rulări reușite, cu suprapunere de cinci minute. Catalogul, variantele și stocul se reconciliază complet. **Reimportă comenzile disponibile** ignoră watermark-ul. **Repornește importul incomplet** invalidează vechile lucrări și pornește o scanare completă. Nu dublează datele deja publicate.

Actualizarea este explicită, la cerere; nu există încă un scheduler de reconciliere sau webhookuri comerciale. Un job face un singur request cu maximum 25 de elemente. Ordinele cu mai mult de 25 de linii, variantele și locațiile continuă prin cursori salvați în DB. Întreruperea/retry nu publică o comandă parțială. Dacă sursa modifică o comandă între paginile liniilor, rularea cere restart, în loc să amestece două versiuni.

Erorile 429/5xx/transport și THROTTLED intră în retry cu backoff; Retry-After numeric este respectat. Răspunsurile GraphQL cu errors nu sunt importate parțial. Lipsa permisiunilor ori o monedă nesuportată rămân vizibile ca lucrări care necesită atenție. 401 revocă accesul; 403/ACCESS_DENIED nu distrug tokenul unei instalări valide, dar cer verificarea scopes/PCD.

## Date și limite

`Core\Contracts\ImportSource` și `Core\Data\ImportPage` separă proiecțiile citite de comenzile validate pentru facturare/fulfillment. Contractele din 04 rămân utile pentru acele operații; nu prezentăm toate metodele `CommerceConnector` ca disponibile. Adaptorul Shopify traduce explicit răspunsul în câmpuri neutre. Nu păstrăm JSON brut, note, carduri sau custom attributes ale clienților.

Migrația 006 păstrează identitatea unică merchant/store/provider/kind/external ID, ID intern stabil și versiune. `commerce_records.document` conține snapshot normalizat: order cu linii și ID-uri interne stabile, product, variant sau inventory. Coloanele indexate controlează izolarea, identitatea, părintele, versiunea și momentul observării; detaliile variabile sunt JSON. Catalogul nu rescrie snapshotul liniilor istorice.

Comenzile păstrează distinct totalul original/curent, reducerile, taxele, transportul, sumele încasate/rambursate/restante; liniile păstrează cantitățile originale/curente/rambursabile, alocările reducerilor și taxele. `discountedTotalSet` este documentat ca total după reducerile de linie, nu substitut al tuturor reducerilor comenzii. Nu impunem formula simplificată a unei facturi asupra faptelor istorice Shopify. Banii sunt integer în unități minore, serializați ca string, în moneda de prezentare a comenzii; catalogul folosește moneda magazinului. Conversia de monedă nu este implementată. Lista explicită suportată: RON/EUR/USD/GBP/CHF/CAD/AUD/NZD/PLN/CZK/HUF/BGN/SEK/NOK/DKK (2 zecimale), JPY/KRW (0), KWD/BHD/JOD (3). Monedele necunoscute sunt respinse, fără presupunerea exponentului.

Stocul păstrează cantitatea negativă, `null` pentru neurmărit, locația activă și observedAt. Nu înseamnă rezervare sau promisiune de disponibilitate la expediere. O scanare completă dezactivează elementele de catalog absente. Absența unei comenzi din fereastra Shopify nu o șterge automat. API-ul permite implicit ultimele 60 de zile; `read_all_orders` nu este cerut. Limite defensive: 2 MiB răspuns/request și document/comandă, 10.000 pagini/task; depășirea oprește importul explicit.

## Acces, criptare și privacy

Owner/admin cu acces la store pornesc importuri. Listele sunt filtrate după dreptul de citire a magazinului; detaliile personale ale comenzilor sunt permise owner/admin/operator/finance și auditate, nu viewer. Jobul verifică din nou store/conexiunea și lease-ul după request, înainte de orice commit. Scopes și dev allowlist din 07 rămân obligatorii.

Comenzile sunt criptate integral, inclusiv în staging. Documentele mari folosesc bucăți AES-GCM cu AAD care leagă tenant/store/ID/numărul și ordinea bucăților. Outbox/audit/job payloads conțin numai ID-uri și contoare. `php bin/key-status.php` include `orderKeyUsage`, pe lângă conexiuni și webhookuri; **nu retrage o cheie încă referită**. Recriptarea conexiunilor nu recriptează automat comenzile sau receipt-urile; păstrează cheile vechi până la o migrare explicită și expirarea backupurilor.

Cererile Shopify semnate sunt păstrate criptat în inbox. Owner/admin le pot procesa din **Conexiuni și integrări**:

- `customers/data_request`: exportul include comenzile asociate, dar rămâne `needs_review`. Comerciantul transmite răspunsul prin canalul său verificat și confirmă explicit transmiterea. Ordely nu trimite emailuri automat.
- `customers/redact`: șterge proiecțiile și copiile temporare ale comenzilor asociate și blochează reimportul prin ID-urile comenzilor/clientului.
- `shop/redact`: șterge proiecțiile magazinului și oprește importurile sale numai dacă instalația vizată este revocată; un eveniment vechi nu șterge datele unei reinstalări active.

La procesare, payloadul receipt-ului este golit, iar auditul reține numai referința. Staging se șterge după publicare/restart; workerul anulează importurile abandonate de peste șapte zile și șterge staging-ul lor. Proiecțiile publicate se păstrează până la cerere explicită de ștergere; retenția comercială/fiscală, backupurile și termenele de răspuns trebuie stabilite înainte de date personale reale/pilot. Aceasta este implementarea de dezvoltare și procedura locală, nu o declarație de conformitate sau aprobare App Store.

## Surse oficiale

[Order și fereastra de acces](https://shopify.dev/docs/api/admin-graphql/2026-07/objects/Order), [LineItem și semantica reducerilor/taxelor](https://shopify.dev/docs/api/admin-graphql/2026-07/objects/LineItem), [ProductVariant](https://shopify.dev/docs/api/admin-graphql/2026-07/objects/ProductVariant), [InventoryLevel](https://shopify.dev/docs/api/admin-graphql/2026-07/objects/InventoryLevel), [protected customer data](https://shopify.dev/docs/apps/launch/protected-customer-data), [privacy webhooks](https://shopify.dev/docs/apps/build/compliance/privacy-law-compliance). Query-urile din resources/shopify sunt validate cu Shopify Admin validator. Raportul include probele reale separat de cele simulate.
