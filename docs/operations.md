# Operațiuni durabile — modulul 05

Aplică schema cu `php bin/migrate.php` (001–003). Worker: `php bin/worker.php 100`, opțional `php bin/worker.php 100 MERCHANT_ID`. În Compose: `docker compose exec -T app php bin/worker.php 100`. Primul argument limitează iterațiile (1–10.000); procesul se oprește și când nu mai există lucru eligibil. Se poate relansa fără resetarea tabelelor.

## Flux

1. Crearea/redenunmirea magazinului scrie starea, versiunea, auditul și outbox-ul în aceeași tranzacție. Numele nu se copiază în payload.
2. Dispatcher-ul creează atomic jobul și livrarea per consumer. Replay găsește același job; evenimentele fără consumer rămân în outbox.
3. Claim folosește `jobs_claim(status,available_at,id)` și `FOR UPDATE OF j SKIP LOCKED`. Tokenul de lease și fencing version se verifică la heartbeat/confirmare/eșec. [MySQL locking reads](https://dev.mysql.com/doc/refman/8.4/en/innodb-locking-reads.html).
4. Consumer-ul local salvează efectul și processed_at atomic. Crash înainte de ACK permite reluare fără repetarea efectului. Apelurile externe folosesc coordonatorul separat, în afara tranzacției.
5. Inbox-ul intern primește sursă/livrare, digest SHA-256 și referințe numai după autentificarea sursei în adaptor. Livrarea duplicată are un job; conținut schimbat → Conflict. Endpoint-ul public de webhook și payload-ul criptat apar în 07, nu sunt simulate ca implementate aici.

Coada: READY → RUNNING → SUCCEEDED/READY/DEAD. Lease implicit 60 secunde, interval permis 1–3600. Scope-ul merchant/store activ se verifică înainte de handler. Recuperarea unui lease expirat invalidează owner/version vechi și păstrează istoricul încercărilor. Retry cert temporar are backoff exponențial, jitter și Retry-After. Implicit 5 încercări, maximum 25; requeue auditat adaugă până la 5 în aceeași limită.

Worker-ul acceptă numai handlere înregistrate în cod, nu clase din payload. Acum există `store.observe`, care verifică scope-ul și auditează consumarea evenimentului. Cron/supervisor, priorități/quotas, retenție și dimensionare de producție rămân pentru pilot.

## Efecte externe și idempotency

`ExternalOperations::execute` persistă digestul request-ului și intenția unică pe merchant/store/tip, independent de HTTP key/correlation ID. Conținutul/conexiunea schimbate pentru aceeași intenție produc Conflict. Cheia provider rămâne stabilă între încercări.

Rezervarea IN_FLIGHT se comite înaintea apelului, fără lock DB ținut pe rețea. CONFIRMED se returnează la replay. Eșecul cert temporar devine RETRYABLE, cu următorul moment permis și maximum 5 apeluri. Timeout ambiguu, excepție neclasificată după intrarea în callback sau proces oprit cu IN_FLIGHT expirat → UNKNOWN. Providerul nu este reapelat automat. Un răspuns întârziat nu suprascrie o versiune de fencing mai nouă.

Reconcilierea confirmă o referință externă deja verificată, cu versiune așteptată și digestul dovezii; produce audit. Nu există deblocare oarbă/ștergere de istoric pentru UNKNOWN. Demonstrarea absenței efectului și o procedură sigură de retry se stabilesc cu adaptorul real. Nu garantăm exactly-once pentru un API care nu îl susține.

În 05 se verifică merchant/store; verificarea conexiunii/revocării se leagă în 06 înainte de integrarea reală. Testele folosesc exclusiv simulatorul din DB `_test`.

## API și interfață

Owner/admin inspectează numai magazinele permise. Un admin cu granturi limitate nu poate relua joburi la nivel de merchant. Se aplică login/CSRF/Origin din [identity](identity.md).

| Rută | Comportament |
| --- | --- |
| POST `/api/stores` | `Idempotency-Key` obligatorie; același request normalizat întoarce același ID, conținut schimbat → 409 |
| GET `/api/operations` | Ultimele 100 jobs, operații și audit per listă; fără lease token, request brut sau credentials |
| POST `/api/jobs/{id}/retry` | Requeue pentru DEAD, cu drepturi curente și CSRF; intenția externă este păstrată |
| POST `/api/operations/{id}/confirm` | UNKNOWN: `{version,reference,evidenceHash}`; referință verificată extern, digest SHA-256 al dovezii, owner/admin și CSRF |

UI-ul arată stările/încercările/referința și permite reluarea joburilor temporare/lease expirat. Nu oferă confirmare imaginară pentru UNKNOWN. Formularul de magazin păstrează cheia la retry după eroare; o schimbă după confirmare sau editarea datelor.

Auditul este append-only prin API-ul aplicației, fără promisiune de imutabilitate împotriva administratorului DB. SafePayload acceptă numai ID-uri, contoare și digestul dovezii. Mesajele brute de excepție, parolele, emailurile, adresele și IBAN-ul nu sunt payload-uri valide. Retenția nu șterge automat dovezile idempotency.

`composer check` include două procese PHP concurente și crash deliberat înainte/după efectul extern simulat. Cleanup-ul elimină numai fixture-urile proprii din `_test`. Vezi [raportul 05](testing/05-durable-operations.md).
