# Modul 03 — Identity & Tenancy

Actualizat: 2026-09-27. Stare: **REVIEW**. Dependență: 02 DONE.

Utilizatorul a autorizat implementarea secvențială până la 06 inclusiv. Fiecare modul se testează, documentează și publică înaintea următorului.

## Criterii stabilite înainte de cod

- [ ] Migrații versionate MySQL, verificarea checksum și detectarea unei migrări incomplete.
- [ ] Merchants, users, memberships, roluri owner/admin/operator/finance/viewer și granturi pe stores; FK compuse resping relații între tenants.
- [ ] Login cu parolă hash, sesiuni server-side revocabile, cookie HttpOnly/SameSite și Secure pe HTTPS; rate limit și CSRF la scrieri.
- [ ] Contextul tenant provine din sesiune; API și repository resping accesul străin, inclusiv ID valid al altui tenant. Membership/merchant/user dezactivat invalidează accesul.
- [ ] Listare/creare/editare stores și schimbare merchant autorizată; interfață HTML/JS minimală pentru verificarea fluxului.
- [ ] Provisionare locală prin CLI, fără parolă în argumente sau loguri. Onboarding public și resetarea prin email rămân în 20.
- [ ] Teste reale MySQL, teste HTTP și analiza statică; documente și push verificate.

## Pași

1. Schema, migrator, context și autorizare.
2. Sesiuni/API/UI și CLI de provisionare.
3. Teste negative, verificări, documentare și publicare.

## Rezultate

Implementarea și testele locale sunt complete; [raport](../testing/03-identity.md), [operare/API](../identity.md). CI/push rămân înainte de închidere. Helper-ul de ID și tranzacții este o dependență necesară identității. Corecția bootstrap-ului pentru prioritatea environment-ului a fost necesară fluxului HTTP real.

## Predare

Continuă doar modulul 03; după închidere începe 04 conform autorizării utilizatorului. Branch: `codex/modul-01-arhitectura`.
