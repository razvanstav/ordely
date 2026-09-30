# Shipping

Expediții și tracking: AWB, etichetă, pickup și evenimente curier. Contractul extern existent este Core/Contracts/CarrierProvider.php. Invoicing și Commerce rămân contexte separate; coordonarea Facturează + AWB intră în modulul 12.

Structură pregătită la cererea utilizatorului (D25). Logica și persistarea acestui context sunt încă în backlog, conform plan.md; nu sunt implementate și nu se pretinde funcționarea providerului.

Domain deține modelele și regulile; Application comenzile și orchestrarea; Infrastructure repository-urile/adaptoarele interne; Presentation API-urile și autorizarea. Efectele externe trec prin Operations și porturile Core, cu idempotency/audit și reconcilierea timeout-urilor. Contextul merchant/store se validează la fiecare operațiune.

Ecranul administrativ este definit în resources/workspace.js, cu componente comune și fără înregistrări simulate. La activare, se conectează reader-ul și command handler-ul real; starea funcției, filtrele și acțiunile se actualizează împreună. Nu crea alt model de date în browser.
