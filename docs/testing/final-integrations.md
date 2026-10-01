# Verificarea finală a integrărilor

Mutată la final la cererea utilizatorului din 2026-10-01, conform [D26](../decisions.md). Registrul urmărește probe reale; dezvoltarea pe module continuă cu verificările locale ale fiecărui flux. Conexiunile și probele de citire existente nu se pierd și nu se repetă fără motiv. Se salvează aici numai comenzi/scenarii și rezultate fără secrete, date personale sau documente reale.

| Integrare | Configurare / punct de conectare | Probă finală rămasă | Stare |
| --- | --- | --- | --- |
| Shopify | Integrări → Shopify; instalare OAuth și acces pe magazin conform [fluxului existent](../shopify.md) | Revalidare acces/scopuri, import incremental, webhook/retry și date de facturare complete în fluxul final | NOT_RUN — amânat; importul din 08 are probe anterioare |
| Oblio | Integrări → Oblio; email/clientId + API KEY/clientSecret; firmă/serie separat, criptat | Mediu D08, emitere/retry/rezultat necunoscut, original verificat/storno/PDF, opțiuni stoc/email/SPV | NOT_RUN — amânat; autentificarea și nomenclatoarele au probe anterioare |
| Sameday | Integrări → Curierat; schema credentialelor și adaptorul se definesc în 10 | Conectare, AWB/COD/etichete, retry și reconciliere pe mediu controlat | NOT_RUN — modul PLANNED |
| FAN Courier | Integrări → Curierat; schema credentialelor și adaptorul se definesc în 11 | Conectare, AWB/COD/etichete, retry și reconciliere pe mediu controlat | NOT_RUN — modul PLANNED |
| Flux transversal | Modulele funcționale conectate la aceleași contexte merchant/store | Schimb: diferență manuală + transport din site; refuz de primire → un singur storno; acces/revocare și operații parțial reușite | NOT_RUN — după implementarea modulelor |

Un rezultat local sau simulat nu închide o linie de mai sus. Înainte de lansare, completăm fiecare rezultat real PASS/FAIL/BLOCKED, mediul, limitările și dovada. Nu este o autorizație de emitere în contul existent; mediul și opțiunile D08 se stabilesc înaintea probei fiscale. Reintegrarea/gestiunea stocului nu intră în acest registru.
