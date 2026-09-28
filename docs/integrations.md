# Conexiuni și chei — modul 06

Actualizare 07: aplicația Ordely și magazinul **Ordely Shop (dev)** au fost create, dar conectarea nu este încă implementată. ANATOMIK este live și exclus explicit din teste. Urmează [fișa modulului 07](modules/07-shopify.md); furnizorul rămâne indisponibil în registru până la integrarea efectivă.

ProviderConnection aparține unui merchant; `store_provider_bindings` autorizează explicit fiecare magazin. FK-urile compuse verifică merchant-ul și tipul commerce/carrier/invoice. Mai multe conexiuni pot fi asociate aceluiași magazin; maximum una implicită per tip, impus de MySQL. Revocarea păstrează conexiunea, asocierile și istoricul, dar oprește utilizarea.

## Registru și limite

`config/providers.php` este composition root pentru definiții și fabrici. Contractele rămân în Core. Resolverul verifică merchant/store/conexiune/asociere/tip, decriptează pe server și verifică din nou starea/versiunea înainte de callback. Adaptoarele autentificate nu se salvează în jobs și nu se păstrează în cache între apeluri.

În `APP_ENV=dev` sau `test` sunt disponibile trei simulatoare: magazin, curier, facturare. Folosesc tokenuri inventate, nu contactează furnizori și păstrează efectele simulate doar în memorie pe durata instanței. `prod` nu le înregistrează și refuză utilizarea conexiunilor fake rămase în DB. Shopify are fluxul separat de [instalare și autentificare](shopify.md) din 07; operațiile CommerceConnector urmează în 08, de aceea registry le indică încă indisponibile. Oblio, Sameday și FAN rămân planificate, fără credențiale reale colectate.

Capabilitățile se citesc din adaptorul rezolvat pentru conexiunea curentă. Setările specifice fiecărui provider vor primi scheme explicite în modulul său; nu acceptăm un JSON arbitrar de configurare.

## Setup local

După Composer și migrații rulează `php bin/keyring.php`. Creează `var/keys/keyring.json` exclusiv dacă nu există, fără afișarea materialului criptografic. `var/` este ignorat de Git și este în afara document root `public/`. Pe Unix fișierul este creat cu permisiuni private; pe Windows folosește ACL-urile directorului utilizatorului. Pentru deployment, acordă acces numai identității procesului PHP și operatorilor autorizați, prin secret storage/ACL.

Implicit aplicația citește acest fișier prin cale absolută. `ORDELY_KEYRING_FILE` poate indica o cale absolută la un secret file montat separat, de exemplu `/run/secrets/ordely-keyring.json`. Formatul este `active` (ID neconfidențial) și `keys` (dicționar ID → cheie de 32 bytes codificată Base64 strict). Cheia lipsește din DB, Git, job payload și audit. Fără keyring, citirea metadatelor și revocarea funcționează; crearea/decriptarea/rotația eșuează închis.

Pe alt PC cu DB nouă generează propriul keyring. Dacă restaurezi aceeași DB, ai nevoie separat de cheile care o pot decripta; Git nu le transportă. Nu trimite chei sau credențiale în chat, commit-uri ori jurnal.

## Rotație

1. Păstrează backup securizat al DB și al keyring-ului, cu cheile distribuite separat de DB.
2. `php bin/keyring.php keyring-next keyring` creează un fișier nou, păstrează toate cheile vechi și adaugă una activă aleatoare. Nu suprascrie fișiere existente.
3. Configurează `ORDELY_KEYRING_FILE` către calea absolută a `var/keys/keyring-next.json` pe fiecare proces web/worker și repornește procesele care au încărcat configurația veche.
4. În UI, folosește „Recriptează cu cheia activă” pe fiecare conexiune, inclusiv cele revocate. Operația verifică versiunea, decriptează cu cheia din envelope și recriptează cu cheia activă; starea revocată rămâne revocată.
5. `php bin/key-status.php` raportează numai ID-uri de chei și numărul de conexiuni care le referă, inclusiv cele revocate. Retragerea unei chei cere zero referințe și respectarea retenției/restore-ului backup-urilor. Nu există ștergere automată a cheilor.

„Înlocuiește tokenul” schimbă credențialele providerului și recriptează cu cheia activă, fără să activeze o conexiune revocată. Rotația keyring-ului schimbă cheia de stocare; nu rotește contul/tokenul la furnizor. O conexiune revocată nu se reactivează; reconectarea va avea identitate nouă.

## API și permisiuni

Sunt necesare o sesiune activă owner/admin și `all_stores=true`, verificate din DB la fiecare operație. O conexiune partajată poate afecta mai multe magazine, deci un admin limitat la anumite magazine nu gestionează credențialele merchant-ului. CSRF și JSON sunt obligatorii pentru scrieri.

| Rută | Body / rezultat |
| --- | --- |
| `GET /api/integrations` | Registry și metadate/asocieri; fără credentials, nonce, tag sau ciphertext |
| `POST /api/integrations` | `id` aleator hex32, `provider`, `label`, `credentials` cu schema exactă; simulatoarele cer `apiToken` inventat |
| `POST /api/integrations/{id}/bind` | `version`, `storeId`, `isDefault` boolean |
| `POST /api/integrations/{id}/unbind` | `version`, `storeId` |
| `POST /api/integrations/{id}/rotate` | `version`; recriptare, inclusiv arhive revocate |
| `POST /api/integrations/{id}/credentials` | `version`, `credentials`; numai conexiune activă |
| `POST /api/integrations/{id}/revoke` | `version`; nu face apel extern/dezinstalare la furnizor |
| `POST /api/integrations/{id}/capabilities` | `storeId`, `kind`; returnează numai funcțiile adaptorului rezolvat |

ID-ul clientului se păstrează la retry: același conținut revine la aceeași conexiune fără audit duplicat; conținut diferit produce 409. Modificările verifică versiunea sub row lock. Două rotații pentru aceeași versiune: una reușește, cealaltă primește conflict. Tranzacția include modificarea și auditul allowlist; eșecul de criptare lasă configurația și auditul neschimbate.

`ExternalOperations` cere conexiune în DB și asociere activă atât înainte de rezervare, cât și imediat înaintea apelului. Un job vechi nu păstrează autorizarea de dinaintea revocării. Un apel deja trimis poate avea efect extern dacă ulterior se revocă conexiunea; se păstrează regulile UNKNOWN din 05. Revocarea locală nu pretinde anularea unui apel executat sau revocarea tokenului la furnizor.

## Criptare și probe

AES-256-GCM folosește chei aleatoare de exact 32 bytes, nonce aleator de 12 bytes și tag verificat de 16 bytes. AAD include scopul credentials, versiunea formatului, algoritmul, merchant, connection, provider și key ID. Mutarea envelope-ului pe alt context sau modificarea lui invalidează decriptarea. Validăm explicit lungimile, deoarece API-ul PHP poate ajusta implicit cheia. [Documentație PHP OpenSSL](https://www.php.net/manual/en/function.openssl-encrypt.php).

Valorile secrete au JSON/debug redactat și serializarea PHP interzisă; `reveal()` este numai granița server-side către criptare/adaptor. Erorile publice și logurile aplicației folosesc categorii/clase, fără credentials sau mesaje brute. [Raportul de teste](testing/06-provider-connections.md) include decriptare falsificată, HTTP real și inspectarea logului rezultat.
