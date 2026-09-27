# Workflow modular și lucru de pe mai multe PC-uri

## Sursa de adevăr

Repository-ul conține cerințele, arhitectura, codul, testele, deciziile și punctul de reluare. Chatul ajută discuția; nu este registrul proiectului. Căile din documente sunt relative la repository, fără dependență de numele utilizatorului sau litera discului.

Un singur modul este activ. În interiorul lui lucrăm la un singur pas verificabil. Un exemplu de pas este „validarea tranziției ReceiveReturn”, urmat de teste și actualizarea fișei. Nu deschidem simultan widget, facturare și curieri.

## La începutul unei sesiuni

1. Verifică branch-ul și modificările locale cu `git status --short --branch`.
2. Dacă există modificări locale, identifică și păstrează-le înainte de sincronizare. Nu le suprascrie.
3. Dacă există `origin`, rulează `git fetch origin`. Verifică în STATUS/istoric branch-ul de predare și continuă-l; nu presupune că se lucrează mereu pe branch-ul principal.
4. Pe branch-ul corect, cu working tree curat și upstream configurat, rulează `git pull --ff-only`. Dacă există divergențe, analizează istoricul și rezolvă fără force push.
5. Citește AGENTS → STATUS → plan → fișa modulului → deciziile relevante.
6. Confirmă local versiunile și urmează [setup-ul](setup.md), cu comenzile reproductibile livrate în modulul 02.
7. Definește pasul de lucru și verificarea lui în fișa modulului.

## În timpul lucrului

- Bifează doar ce este terminat. Notează și lucrurile mici care influențează reluarea: migrare aplicată, configurare schimbată, test nereușit, limitare a sandbox-ului, fixture nou.
- Pentru fiecare test: comandă/scenariu, mediu și rezultat. Nu salva date reale de clienți în rapoarte.
- O decizie arhitecturală are ID, motiv și consecințe; se consemnează în `docs/decisions.md`.
- Dacă o verificare eșuează, remediază în modulul curent. Dacă este blocat, consemnează blocajul, fără să declari modulul terminat.

## La finalul unei sesiuni

1. Rulează testele relevante pentru pasul modificat și notează rezultatele.
2. Actualizează fișa modulului, `plan.md`, `STATUS.md`, jurnalul zilei și deciziile modificate.
3. Inspectează diff-ul pentru cod accidental, secrete și fișiere locale.
4. Cu identitatea Git configurată, adaugă explicit fișierele relevante și creează un commit mic: de exemplu `docs(module-01): define architecture and handoff`.
5. Cu remote-ul stabilit, trimite branch-ul: `git push -u origin <branch>` la prima publicare; ulterior `git push`.
6. Verifică rezultatul comenzii și egalitatea commit-ului local cu branch-ul remote, prin fetch și compararea referințelor. Un working tree curat singur nu dovedește că ai făcut push.
7. Mesajul de predare include branch, commit, rezultatul push-ului, teste și următorul pas. Hash-ul commit-ului care conține documentul se află în Git; nu încerca să înscrii în commit propriul hash.

Dacă push-ul eșuează, spune „salvat local, nesincronizat” și păstrează modificările. Nu muta lucrul pe celălalt PC presupunând că există acolo.

## Prima configurare Git

Configurarea inițială este finalizată: `origin` indică `https://github.com/razvanstav/ordely.git`, iar branch-ul `codex/modul-01-arhitectura` are commit-uri și upstream. Identitatea locală a fost stabilită din contul GitHub autentificat, folosind adresa noreply. Autentificarea se face local prin mecanismul Git al PC-ului; nu cerem parole sau token-uri în documente/chat.

Pe un PC nou, rulează într-un folder părinte potrivit:

```powershell
git clone --branch codex/modul-01-arhitectura https://github.com/razvanstav/ordely.git
cd ordely
git status --short --branch
```

Deschide folderul clonat și citește `AGENTS.md`, `STATUS.md` și `plan.md`. Configurează autentificarea și identitatea Git a acelui PC pentru commit-uri viitoare; configurația locală Git nu se transferă prin clone. Dacă există deja o clonă, folosește procedura fetch/pull de la începutul documentului, fără să suprascrii modificări locale.

Nu sincroniza `.git`, `vendor`, fișiere `.env` sau baze de date prin foldere cloud partajate. Folosește câte o clonă Git per PC. Secretele se configurează separat; versiunile și exemplele fără secrete sunt versionate.

## Conversații separate

Titlu sugerat: `Ordely — Modul 08 — Import comenzi`, eventual cu numărul pasului. Utilizatorul poate deschide o conversație nouă la schimbarea modulului; agentul nu creează automat conversații. Dacă utilizatorul autorizează un lot secvențial, fiecare modul se testează, documentează și publică înaintea următorului. Starea nu depinde de păstrarea conversației precedente.

Prompt reutilizabil:

> Citește AGENTS.md, STATUS.md, plan.md și fișa modulului curent. Verifică Git și continuă doar următorul pas documentat. Nu începe alte module. Rulează verificările relevante și actualizează fișa, planul, starea, jurnalul și deciziile. Raportează dacă schimbările sunt doar locale sau sincronizate.

## Ce înseamnă DONE

Criteriile și testele obligatorii sunt îndeplinite, documentele reflectă rezultatul și nu există blocaje nerezolvate. Pentru modulul 01 este necesară aprobarea arhitecturii, cerută explicit de brief. Modulul următor se abordează în ordinea planului și în limita lucrului autorizat, cu fișă și predare proprii.
