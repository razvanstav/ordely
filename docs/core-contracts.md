# Core: valori și contracte

`src/Core` conține numai obiecte de valoare, DTO-uri imutabile și porturi. Nu importă Identity HTTP, PDO, Symfony, adaptoare sau SDK-uri. Testul de arhitectură verifică tokenii PHP, inclusiv o probă negativă cu un SDK nou. `src/Adapters/Fake` implementează contractele, iar viitoarele adaptoare reale vor sta în propriile directoare.

## Bani și snapshots

`Money` păstrează un întreg semnat, cu limită absolută 9.000.000.000.000.000 unități minore, pe PHP 64-bit. `Currency(code, exponent)` cere exponent explicit; importatorul îl ia din metadate verificate, fără să presupună două zecimale pentru orice monedă. Adaptoarele fake acceptă RON cu exponent 2. Nu există conversie valutară automată.

`Money::decimal('129.90', new Currency('RON', 2))` produce 12.990 bani. Parserul refuză float, notație exponențială, virgulă, spații și precizie suplimentară. Aritmetica respinge monede/exponenți diferiți și overflow înainte ca PHP să transforme rezultatul în float. JSON livrează `minor` ca string pentru precizie în JavaScript. [PHP documentează conversia la float în caz de overflow](https://www.php.net/manual/en/language.types.integer.php).

`ratio` rotunjește jumătățile în sens opus lui zero. `allocate` folosește metoda resturilor cele mai mari, cu egalități rezolvate în ordinea intrării: 100 bani împărțiți 1:1:1 → 34,33,33; totalul se păstrează și la sume negative. `TaxRate` folosește basis points; este un mecanism de calcul, fără cotă fiscală implicită sau politică de facturare stabilită în acest modul.

`CommercialLine`: cantitate pozitivă × preț unitar net − reducere netă + taxă = total brut. Comanda adună liniile și transportul brut; factura adună liniile. Totalurile primite trebuie să coincidă. Snapshot-urile păstrează adrese/client/produse normalizate, fără payload brut al providerului. PII din snapshots nu se trimite în audit/job payloads. Coletele folosesc grame și milimetri.

MerchantId, StoreId, ConnectionId, OrderId și CorrelationId sunt tipuri distincte. ExternalId este opac și sensibil la case. `ConnectionContext` identifică merchant/store/connection și correlation; **nu este o dovadă de autorizare**. Resolver-ul server-side din 06 validează accesul și starea curentă înaintea apelului.

## Porturi

| Contract | Suprafață |
| --- | --- |
| CommerceConnector | Compune OrderReader, CatalogReader, FulfillmentWriter, OrderWriter, ReturnWriter, WebhookRegistrar și CapabilitiesProvider |
| OrderReader / CatalogReader | Comenzi, client, produse, variante, inventar; pagini 1–100 și cursor opac |
| FulfillmentWriter | Linii/cantități/locație, fulfillment și tracking tipat |
| OrderWriter | Editarea notei și referințe Ordely allowlist; alte editări vor primi câmpuri explicite, fără JSON arbitrar |
| ReturnWriter / WebhookRegistrar | Export/schimbare retur normalizat, abonamente cu topicuri și callback HTTPS validate |
| CarrierProvider | Servicii, tarif, expediere/retur/anulare, etichetă, tracking, pickup și puncte pickup |
| InvoiceProvider | Emitere/anulare, credit note, citire/PDF și cerere de trimitere |

`CapabilitySet` declară funcții, monede și țări. Absența unei funcții produce `ProviderFailure(Unsupported)`, fără succes fictiv. Restricțiile contului/serviciului se verifică din nou în adaptor. `ProviderFailure` expune doar categoria sigură și retry-after, fără răspuns extern, secret ori excepție SDK. `Transient` înseamnă efect cert neprodus și poate permite retry; `Unknown` înseamnă rezultat incert și cere reconciliere. Doar adaptorul care cunoaște protocolul poate clasifica sigur rezultatul.

`OperationKey` este obligatorie la efecte. `CanonicalJson` ordonează cheile obiectelor, păstrează ordinea listelor și normalizează timestamp-uri UTC; respinge float. CorrelationId nu schimbă identitatea operațiunii. Aceste contracte nu înlocuiesc coordonarea durabilă din 05.

## Adaptoare fake

FakeCommerce se populează explicit cu fixtures normalizate. FakeCarrier are serviciul `standard`, RON/RO, COD, retur, pickup și etichete de test PDF/ZPL. Parcel exchange și pickup points nu sunt active implicit. FakeInvoice produce numere `TEST-*`, documente marcate NOT VALID și înregistrează destinatarii numai în memorie; nu trimite emailuri.

Toate datele și cheile sunt separate pe merchant/store/connection. Repetarea aceleiași operațiuni/chei/conținut întoarce rezultatul original; conținut schimbat produce Conflict. `Effects::failNext` simulează eșec înainte sau după efect. O altă cheie poate crea o nouă operațiune; deduplicarea intenției de business este responsabilitatea modulului 05.

Starea fake dispare la restart. Nu este persistată în fișiere sau DB și nu reproduce toate politicile fiscale, limitele cumulative de fulfillment/retur sau regulile unui furnizor. Nu folosi fake-urile în producție. Nu există trafic către Shopify/curieri/Oblio. Contract tests verifică suprafața Ordely; integrarea reală are teste suplimentare în modulele 07+.

Testele relevante sunt `MoneyTest`, `CoreValidationTest`, `CommerceContractTest`, `CarrierContractTest`, `InvoiceContractTest` și `ArchitectureTest`. Pentru un nou adaptor, aplică scenariile aceleiași interfețe cu fixtures/configurarea lui și verifică separat API-ul real într-un cont controlat.
