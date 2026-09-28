# Starea curentă — Ordely

2026-09-28. Registrul: plan.md. **01–07 DONE; 08 DEFERRED_EXTERNAL la cererea utilizatorului. Urmează partea locală 09.**

Utilizatorul nu se poate autentifica în Shopify și a cerut explicit să continuăm fără aceste probe. Proba comenzii reale cu 30 de linii/PCD/reimport rămâne amânată, nu PASS. Nu mai cerem login Shopify pentru progresul local și nu reîncercăm instalarea CLI acum. Istoricul aprobării și al refuzului anterior rămâne în [raportul 08](docs/testing/08-commerce-import.md).

## Continuare

Modulul 09 începe cu ciorne de facturi independente de CMS: salvare locală, editare, linii și sume exacte, criptare și audit. Emiterea/storno prin Oblio și deciziile D01/D08 rămân pașii următori ai aceluiași modul. Nu începem 10.

## Dovezi existente și mediu local

Codul 08 și predarea de pe celălalt PC au fost aduse la 807e0da, urmate de predarea acestui PC 52aa623. Local: 204 lint, PHPStan 8, 101 unit/677 assertions, 89 integration/512 assertions, JS și 6 probe HTTP PASS. CI pentru codul e8a4133: [Windows/Linux PASS](https://github.com/razvanstav/ordely/actions/runs/36420254697). Catalogul real testat anterior: 17 produse, 26 variante, 28 poziții de stoc, trei importuri fără dubluri; proba comenzii reale nu s-a executat.

PHP 8.4.24, MySQL 8.4.11 pe 33060, migrații 001–006. Preview standalone http://127.0.0.1:8080/, PID/log în var/pc-resume. Nu este tunel Shopify. `.env` și keyring-ul local sunt păstrate; secretele/tokenurile/DB de pe celălalt PC nu au fost transferate prin Git. Lipsa configurării Shopify nu blochează pasul local 09.

Remote https://github.com/razvanstav/ordely.git; branch codex/modul-01-arhitectura. Înainte de lucru pe alt PC: fetch/pull, apoi AGENTS, STATUS, plan, fișa modulului activ și deciziile. Un singur modul activ; testele automate offline rămân obligatorii.
