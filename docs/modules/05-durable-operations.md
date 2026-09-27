# Modul 05 — Operațiuni durabile

Actualizat: 2026-09-28. Stare: **DONE**. Dependențe: 01–04 DONE; cod 04 și predare publicate, CI verde înainte de acest modul.

## Criterii înainte de implementare

- [x] Schema MySQL pentru audit, outbox, livrări per consumer, inbox, jobs/attempts, idempotency și operații externe, cu scope merchant/store și FK compuse.
- [x] Scriere business + audit + outbox atomică, demonstrată pe magazine; rollback fără eveniment orfan.
- [x] Inbox deduplică sursa/livrarea și respinge același ID cu alt conținut. API-ul public de webhook/semnături rămâne în 07; inbox-ul acceptă numai apeluri interne după autentificarea sursei.
- [x] Dispatcher-ul nu dublează jobul unui consumer. Claim concurent cu SKIP LOCKED, lease/fencing, heartbeat, retry/backoff, buget și dead letters, fără confirmarea unui worker expirat.
- [x] Idempotency HTTP pentru crearea magazinelor și deduplicarea intenției de business pentru efecte externe, inclusiv dacă se schimbă cheia HTTP.
- [x] Efectele externe rulează în afara tranzacției. Confirmarea este durabilă; timeout/crash după efect devine UNKNOWN și nu produce retry orb. Reconcilierea confirmă un rezultat verificat.
- [x] Worker CLI și operare/inspecție documentate; audit și payload-uri conțin numai referințe/date allowlist, fără PII/secrete/mesaje brute de excepție.
- [x] Teste MySQL cu doi workeri/procese, crash și reluare, duplicare inbox/outbox, leases expirate, mismatch idempotency și doi tenants.
- [x] Verificări complete, documente, commit/push și CI înainte de 06.

## Pași secvențiali

1. Schema, scope activ și payload sigur; audit/outbox și integrarea scrierii magazinului.
2. Inbox/dispatcher/coadă/worker, idempotency locală și operații externe.
3. Teste de concurență/crash și documentare, apoi publicare.

## Decizii de implementare

MySQL rămâne sursa durabilă. Fiecare lease are token și fencing version; confirmarea verifică ambele. Rezervarea unui apel extern se comite înaintea apelului. O rezervare IN_FLIGHT expirată este UNKNOWN, fără repetare automată. Un rezultat confirmat se returnează la replay. Cheia intenției este separată de cheia HTTP și include scopul business, nu correlation ID.

Dispatcher-ul creează job + înregistrare de livrare în aceeași tranzacție. Consumatorii au efect local și deduplicare în tranzacție; pentru efecte externe folosesc coordonatorul durabil. Requeue manual se auditează și nu elimină protecția unei operații UNKNOWN.

Conexiunile provider reale și cheile sunt în 06. În 05, contextul verifică merchant/store activ; testele externe folosesc un simulator controlat, fără trafic către furnizori. Verificarea contului/conexiunii și revocării se adaugă în 06 înainte de integrarea reală.

## Predare

Implementare și verificări locale complete: [raport](../testing/05-durable-operations.md), [operare](../operations.md). Cod `f04e71e` publicat; CI Windows/Linux PASS: https://github.com/razvanstav/ordely/actions/runs/36350786805. Urmează 06. Migrațiile 002/003 sunt aplicate local. Branch `codex/modul-01-arhitectura`.
