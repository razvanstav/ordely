# Starea curentă — Ordely

Actualizat: 2026-09-27.

## Punct de reluare

- **Modul curent:** [01 — Arhitectură](docs/modules/01-arhitectura.md), `REVIEW`.
- **Livrat:** propunerea de arhitectură în 30 de secțiuni și sistemul de lucru pe module.
- **Următorul pas:** discutarea deciziilor D01–D08 din [registru](docs/decisions.md) și aprobarea arhitecturii. Ajustările se fac în modulul 01.
- **Cod aplicație:** nu există; nu se începe înainte de aprobare.

## Sincronizare între PC-uri

- Repository publicat pe branch-ul `codex/modul-01-arhitectura`, cu upstream `origin/codex/modul-01-arhitectura`.
- Remote `origin`: `https://github.com/razvanstav/ordely.git`.
- Primul commit al arhitecturii: `3a08834`. Push reușit; hash-ul local și cel returnat de remote au fost identice.
- Identitate locală configurată din contul GitHub autentificat: `razvanstav`, cu adresa GitHub noreply. Nu a fost modificată configurația globală Git.
- Utilizatorul a cerut explicit push după amânarea inițială; blocajul anterior este rezolvat.
- Pe alt PC: clonează repository-ul, deschide branch-ul de mai sus și citește acest fișier. Vezi [workflow-ul](docs/workflow.md).
- Clonarea și pornirea pe al doilea PC nu au fost încă testate. Aplicația nu este implementată.

## Verificări și mediu

Raport: [modul 01](docs/testing/01-architecture-review.md).

- Au fost inspectate brief-ul, folderul proiectului, starea Git și documentația oficială relevantă.
- Verificare documentară PASS: 12 fișiere Markdown, 32 linkuri locale, 30 secțiuni în ordine, numai modulul 01 activ, 20 module planificate, brief identic prin SHA256 și fără fișiere de aplicație.
- PHP și Node sunt disponibile în PATH; Composer, MySQL și Docker nu au fost găsite în PATH. Aceasta nu dovedește că nu sunt instalate în altă parte.
- Nu există teste runtime sau aplicație executabilă. Validarea documentară este separată de validarea viitoarei implementări.
- Execuția shell izolată a eșuat cu `apply deny-read ACLs`; citirile necesare au reușit prin execuția aprobată în afara sandbox-ului. Aceasta este o limitare a mediului de lucru, nu un defect al aplicației.

## Text pentru reluarea într-o conversație nouă

> Continuăm Ordely. Citește AGENTS.md, STATUS.md, plan.md și docs/modules/01-arhitectura.md. Lucrăm numai la modulul 01: revizuirea arhitecturii și deciziilor rămase. Nu începe aplicația înainte de aprobarea arhitecturii. Verifică starea Git înainte de modificări și actualizează documentele la final.
