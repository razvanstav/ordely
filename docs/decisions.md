# Registru de decizii

Actualizat: 2026-09-28. `CERINȚĂ` provine din brief/utilizator; `PROPUS` nu înseamnă aprobat. Pentru acceptare se notează data și decizia utilizatorului, fără a presupune aprobarea din lipsa unui răspuns.

## Stabilite prin cerințe

| ID | Decizie | Motiv și consecință |
| --- | --- | --- |
| R01 | CERINȚĂ — PHP + MySQL; HTML/CSS/JS nativ | Nu adoptăm React/Vue/Angular |
| R02 | CERINȚĂ — ONE CORE + multiple connectors/providers | Tipurile furnizorilor rămân în adaptoare |
| R03 | CERINȚĂ — V1 Shopify, Sameday, FAN, Oblio | Celelalte integrări sunt backlog |
| R04 | CERINȚĂ — Un singur portal și launcher | CMS-urile nu duplică UI-ul de retur |
| R05 | CERINȚĂ — Un modul activ, teste și documentare | Nicio dezvoltare paralelă a trei module; progres în Git |
| R06 | CERINȚĂ — Arhitectura înainte de aplicație | Brief §51 cere oprire după plan până la aprobare |
| R07 | CERINȚĂ — Credentials/IBAN criptate, tenant isolation și audit | Intră de la fundație, nu ca remediere la final |
| R08 | CERINȚĂ — Outbound: selecție business și COD automat | Nimic = fără factură/COD 0; produse/transport se calculează |

## Propuneri structurale ale planului

Direcția A01–A06 a fost acceptată pentru continuare prin mesajul „CONTINUA”; alegerea detaliilor de implementare se consemnează separat. Tabelul păstrează propunerile de arhitectură ca referință, iar D01–D08 au stările de mai jos.

| ID | Stare | Alegere și consecință |
| --- | --- | --- |
| A01 | PROPUS | Monolit modular, module cu Domain/Application/Infrastructure/Presentation; deployment comun inițial |
| A02 | PROPUS | MySQL comun cu merchant/store context și FK-uri compuse; nu DB separată per merchant în V1 |
| A03 | PROPUS | Outbox + inbox + jobs MySQL, Operation pentru pași externi; fără event sourcing |
| A04 | PROPUS | ReturnCase gestionează inbound; ExchangeCase îl referă; OutboundPlan pregătește Shipment fără duplicarea AWB-ului |
| A05 | PROPUS | Bani în unități minore, snapshots imutabile după emitere și quotes versionate |
| A06 | PROPUS | Provider timeout ambiguu cere reconciliere; nu promitem exactly-once la un API care nu o permite |

## D01 — Politică de facturare/storno pentru outbound

Stare: DESCHIS. Termen: înainte de 09/19. Responsabil: proprietarul produsului, cu validarea responsabilului fiscal.

Păstrăm regula de produs din brief: Nimic → fără factură nouă/COD 0; produse și/sau transport → factură și COD calculat. Trebuie stabilit tratamentul liniilor alese cu total zero, al diferenței de preț dintre produsul returnat și înlocuitor, al documentului original emis în afara Ordely și momentul storno. Propunere: BillingPolicy/FiscalPolicy separată, fără compensare automată în V1 până la definirea ei. Acestea sunt reguli de produs propuse, nu concluzii de conformitate fiscală.

## D02 — Executarea rambursării

Stare: DESCHIS. Termen: înainte de 17.

Propunere V1: Ordely pregătește coada și datele; comerciantul plătește extern; finance confirmă suma, referința și data. Nu există integrare bancară cerută explicit în brief. Dacă se dorește plata automată, se adaugă Payment/RefundProvider și un modul dedicat. Nu folosim „REFUNDED” doar fiindcă operatorul a deschis ecranul sau s-a emis storno.

## D03 — Momentul outbound și colet la schimb

Stare: DESCHIS. Termen: înainte de 13/18/19.

Brief-ul cere READY_FOR_OUTBOUND la recepție. Confirmăm dacă inspecția este obligatorie înainte de expediere. „Colet la schimb” poate colecta inbound odată cu livrarea outbound; este o rută alternativă care trebuie definită cu providerul. Nu colectăm din nou un produs deja recepționat. Capabilitățile reale FAN/Sameday se confirmă pe cont/serviciu.

## D04 — Stoc și rezervări

Stare: DESCHIS. Termen: înainte de 08/18.

Propunere: citire per locație, variante fără stoc dezactivate, revalidare la confirmare. Stabilim dacă rezervăm în CMS, când scădem/repunem stocul și ce face operatorul dacă între timp stocul dispare. Snapshot-ul de stoc nu este rezervare. Stocul returnat nu se repune automat fără verdict/policy.

## D05 — Identificare client și OTP

Stare: DESCHIS. Termen: înainte de 14.

Brief-ul cere ID comandă + email/telefon și suport OTP configurabil. Alegem default-ul, TTL-ul și acțiunile care cer verificare mai puternică. Recomandare: OTP înainte de expunerea datelor sensibile; gateway-ul și costul se aleg la implementarea portalului. Public store token nu este secret și nu autorizează comenzi.

## D06 — Runtime și infrastructură

Stare: ALES PENTRU FUNDAȚIA DE DEZVOLTARE, 2026-09-27. Hosting-ul producției și worker supervisor se decid înainte de pilot.

Alegere tehnică în continuarea autorizată: PHP 8.4.24, MySQL 8.4.11, Composer 2.10.3, PSR-4, Symfony 7.4 HttpFoundation/Routing/Dotenv, PHPUnit 12.5, PHPStan 2 level 8. Fără framework frontend sau dependențe Symfony în Domain. Patch-urile sunt fixate în composer.lock/runtime images. Docker Compose este mediul comun; helper-ul Windows pornește MySQL separat de XAMPP. CI verifică Linux cu MySQL/HTTP și PHP pe Windows. Comenzi și surse în [setup](setup.md).

## D07 — Limite V1 și operare

Stare: DESCHIS. Termen: valorile care influențează setup-ul înainte de 02; restul înainte de pilot.

De stabilit: volume zilnice și vârfuri, număr stores/tenant, țări, RON-only versus multimonedă, produse fracționare, SLO-uri, backup/restore, retenție pentru date/payload/audit și limite fișiere. Propuneri: RON pentru operațiunile V1, cantități întregi și păstrarea monedei originale la import, fără conversie implicită. Billing abonament SaaS este separat de facturarea comenzilor.

Notă D07 pentru modulul 02: mediul este local, pe loopback, fără dimensionare de producție sau promisiuni de performanță. Porturi implicite 8080/33060. Reproductibilitatea se verifică într-un checkout CI independent; accesul la al doilea PC personal nu este presupus.

## D08 — Conturi, furnizori și distribuție

Stare: DESCHIS. Termen: înainte de integrarea fiecărui provider și înainte de lansare.

Sunt necesare contul developer/dev store Shopify, scopes/PCD, credențiale de test, documentația și serviciile contractate Sameday/FAN, profilul/seriile Oblio, operațiile suportate pentru storno și lookup. Strategia de distribuție/pilot și monetizare poate cere un modul de SaaS billing; nu confundăm InvoiceProvider pentru comercianți cu taxarea abonamentului Ordely. Disponibilitatea sandbox-urilor și a funcțiilor nu a fost verificată în conturi reale.

## D09 — Identitate și sesiuni (03)

Ales 2026-09-27: token aleator de 256 biți, hash SHA-256 în DB, TTL 8 ore, membership verificat la fiecare cerere, cookie HttpOnly/SameSite=Lax/Secure pe HTTPS și CSRF pentru scrieri. Parole bcrypt prin PASSWORD_DEFAULT pe PHP fixat, cu limită explicită 12–72 bytes. Rate limit atomic 20/cont și 100/IP în 15 minute. Provisionare CLI de încredere; onboarding/resetare prin email în 20. Migrații SQL simple cu checksum și marker `applying`, fiindcă DDL MySQL nu se poate proteja prin rollback tranzacțional obișnuit. Surse: [PHP password_hash](https://www.php.net/manual/en/function.password-hash.php), [MySQL implicit commits](https://dev.mysql.com/doc/refman/8.4/en/implicit-commit.html), [Symfony HttpFoundation](https://symfony.com/doc/7.4/components/http_foundation.html).

## D10 — Money și contracte (04)

Ales 2026-09-27: integer 64-bit cu limită ±9e15 unități minore, exponent monedă explicit, reprezentare JSON string, rotunjire half-away-from-zero și alocare prin resturi maxime cu ordine stabilă. Porturi mici compuse de CommerceConnector, plus CarrierProvider/InvoiceProvider; DTO-uri imutabile fără SDK. ProviderFailure separă Transient (efect cert neprodus) de Unknown (reconciliere). Fakes sunt exclusiv simulatoare în memorie; nu validează API-uri reale sau politici fiscale. D01–D05 rămân deschise.

## O01 — Continuitate Git

Stare: CONFIGURAT ȘI PUBLICAT.

Remote furnizat: `https://github.com/razvanstav/ordely.git`, configurat ca `origin`. Verificarea remote-ului nu a returnat branch-uri. Branch local: `codex/modul-01-arhitectura`.

Inițial identitatea Git nu era configurată, iar utilizatorul a amânat configurarea. Ulterior a cerut explicit push. Contul GitHub autentificat a fost verificat; identitatea `razvanstav` și adresa noreply bazată pe ID-ul contului au fost configurate numai pentru acest repository. Primul commit `3a08834` a fost publicat, iar hash-ul remote a fost verificat identic cu HEAD local. Reluarea pe alt PC folosește același branch și documentele din Git; proba efectivă pe al doilea PC rămâne de făcut.

## D11 — Operațiuni durabile (05)

Ales 2026-09-28: MySQL queue cu index ordonat, SKIP LOCKED, token/fencing și lease 60 secunde; retry cu jitter și Retry-After, implicit 5 încercări, plafon 25 pentru jobs. Coordonator extern cu intenție stabilă, cheie provider stabilă, maximum 5 apeluri certe temporare; UNKNOWN nu se repetă automat. Confirmare după reconciliere cu versiune și digest dovadă, auditată. Payload allowlist numai referințe/counters/digest, fără date personale. Migrarea 003 remediază problema de locking demonstrată cu două procese. Detalii în [operations](operations.md).
## D12 — Conexiuni și criptare (06)

Ales 2026-09-28: AES-256-GCM din OpenSSL deja disponibil, cheie aleatoare 32 bytes/nonce 12/tag 16; keyring separat de DB/Git, AAD cu merchant/conexiune/provider/key ID și versiunea formatului. Rotație prin fișier nou care păstrează cheile vechi, apoi recriptare versionată inclusiv pentru arhive revocate; inventar înainte de retragerea cheilor. Owner/admin cu all_stores gestionează secretele comune merchant-ului. Registry/fabrici în composition root, capabilities din adaptor, simulatoare numai dev/test. Providerii reali, setările specifice și autorizarea lor rămân în modulele respective. Surse și operare: [integrations](integrations.md).

## D13 — Mediu și autentificare Shopify (07)

Ales 2026-09-28: magazin separat **Ordely Shop (dev)** și aplicație **Ordely**, create în Dev Dashboard. Utilizatorul a exclus explicit ANATOMIK live din integrare/teste. Dev allowlist obligatoriu în backend; secretele rămân în fișiere ignorate/criptate, fără chat/Git.

API stabil `2026-07`, confirmat și în Dev Dashboard. Direcție embedded cu App Bridge, instalare gestionată de Shopify, token exchange PHP și offline `expiring=1`, fără framework JS. ID token validează identitatea; drepturile Ordely se verifică separat. Scopes inițiale goale; comenzi/catalog și PCD numai la funcționalitățile care le cer. URL HTTPS temporar furnizat de CLI; instalare, token exchange, refresh, uninstall și reinstalare confirmate exclusiv pe Ordely Shop (dev).

Surse oficiale: [ID tokens](https://shopify.dev/docs/apps/build/authentication-authorization/id-tokens), [token exchange/refresh](https://shopify.dev/docs/apps/build/authentication-authorization/access-tokens), [expirare offline](https://shopify.dev/docs/apps/build/authentication-authorization/migrate-to-expiring-offline-access-tokens), [webhook HMAC](https://shopify.dev/docs/apps/build/webhooks/verify-deliveries). D08 rămâne deschis pentru distribuție/PCD și fluxurile comerciale viitoare.

D13 — completare implementare 07: cod bearer Ordely aleator 256 biți, hash și TTL 10 minute, asociere unică persistentă shop/merchant/store; instalările nu se mută prin binding generic. Expiring offline tokens criptate împreună cu refresh/expiry/scopes; serializare MySQL și commit al tokenului nou chiar dacă proba următoare eșuează temporar. API 2026-07 și zero scopes verificate real. Uninstall real revocă atomic prin inbox lifecycle separat, cu timestamp pentru evenimente întârziate. Cererile privacy rămân needs_review și cer procedură/implementare înainte de date personale/pilot. Operațiile CommerceConnector rămân în 08.

D08 — mediul de test și credențialele aplicației sunt configurate; instalare/refresh/uninstall reale confirmate. Distribuția/PCD/lansarea rămân deschise. Shopify cere și acces standard la datele proprietarului; utilizatorul a finalizat personal Install după explicarea acestui acces. Reinstalarea, noua conexiune și refresh-ul sunt confirmate; blocarea anterioară este rezolvată. [Operare și limite](shopify.md).
