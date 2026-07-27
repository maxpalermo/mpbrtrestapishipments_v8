# mpbrtrestapishipments

Modulo PrestaShop 8 per la gestione delle spedizioni e del tracking tramite REST API ufficiali BRT Bartolini.

## Descrizione

Il modulo `mpbrtrestapishipments` consente di integrare i servizi di spedizione e tracciamento BRT Bartolini direttamente nel Back Office di PrestaShop 8 tramite le nuove API REST ufficiali (`ShipmentApiV00` e `BrtRestApiTracking`).

### Caratteristiche Principali

- **Gestione Spedizioni REST API**: Creazione, conferma, cancellazione e calcolo routing delle spedizioni BRT in tempo reale.
- **Tracking in Tempo Reale**: Monitoraggio continuo dello stato delle spedizioni con aggiornamento degli eventi, filiale di consegna e dettagli recapito.
- **Stampa Etichette PDF**: Generazione e download delle etichette di spedizione (stream PDF Base64) direttamente dal Back Office.
- **Integrazione Dettaglio Ordine**: Pulsante rapido "BRT Spedizione" integrato nella barra strumenti della pagina dell'ordine.
- **Pannello di Amministrazione Multi-Tab**:
  - **Impostazioni**: Configurazione credenziali BRT, codice cliente, deposito partenza, ambiente Sandbox/Produzione, tipi di servizio e reti.
  - **Spedizioni**: Registro completo di tutte le richieste e risposte di spedizione inviate a BRT.
  - **Tracking**: Tabella di tracciamento dettagliata con i singoli eventi della spedizione.
  - **Statistiche**: Dashboard con metriche su consegne, tempi medi di transito e grafici mensili.
- **Migrazione Dati v1.6**: Helper `MpConnector` per l'importazione graduale a scaglioni (chunk AJAX) dei dati storici delle spedizioni da installazioni PrestaShop 1.6.

---

## Requisiti Tecnici

- **PrestaShop**: >= 8.0 (Testato su 8.2.7)
- **PHP**: >= 7.4
- **Estensioni PHP**: `cURL`, `json`, `mbstring`

---

## Struttura del Modulo

- `mpbrtrestapishipments.php`: Classe principale del modulo PrestaShop.
- `src/Api/`: Client ed helper per le REST API BRT (`Shipment` e `Tracking`).
- `src/Models/`: Classi di modello ObjectModel (`ModelBrtRestApiShipmentRequest`, `ModelBrtRestApiShipmentResponse`, `ModelBrtRestApiTracking`).
- `src/Helpers/`: Classi di utilità (`BrtConfig`, `BrtStats`, `MpConnector`, `GetTwigEnvironment`).
- `controllers/admin/AdminMpBrtRestApiShipmentsController.php`: Controller amministrativo principale.
- `views/`: Asset CSS/JS e template Twig del Back Office.

---

## Changelog

### 1.5.3

- **Correzione Parametri Contrassegno BRT (`codPaymentType` e `codCurrency`)**:
  - Confrontando il payload con una richiesta valida BRT, `codPaymentType` deve essere trasmesso a stringa vuota `""` (per indicare Contanti di default), mentre `codCurrency` deve essere sempre valorizzato a `"EUR"`.

### 1.5.2

- Risolto l'errore BRT `-68` (`WRONG OR INCONSISTENT DATA - codPaymentType codCurrency`):
  - In conformità con le specifiche REST API BRT ed il progetto SoapUI DEMO di BRT, per le spedizioni in contrassegno `codPaymentType` e `codCurrency` vengono trasmessi come stringa vuota `""` quando viene selezionato o estratto il tipo contanti (`CA`).

### 1.5.1

- **Posizionamento Fisso della Sezione Totali e RIEPILOGO in Fondo al PDF**:
  - Aggiornata la classe `BrdPdfGenerator` in [BrdPdfGenerator.php](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpbrtrestapishipments/src/Helpers/BrdPdfGenerator.php): il box **RIEPILOGO** ed il blocco **FIRMA** vengono ora posizionati sempre in modo fisso ed ancorato nella parte inferiore del foglio A4 Landscape (`boxY = 143mm`), posizionandosi esattamente sopra la linea del piè di pagina (come mostrato nei campioni ufficiali BRT).
  - Se le righe delle spedizioni occupano uno spazio verticale superiore a `138mm`, la sezione dei totali viene automaticamente spostata in una nuova pagina in fondo al foglio.

### 1.5.0

- **Stampa Borderò PDF Nativa TCPDF (A4 Landscape)**:
  - Implementata la classe `BrdPdfGenerator` e la sottoclasse `BrtBorderoTcpdf` utilizzando esclusivamente i metodi diretti di TCPDF (`Cell`, `SetXY`, `Line`, `Rect`, `SetFont`, ecc.) senza alcuna conversione HTML.
  - Formattazione fedele ai campioni ufficiali BRT: 2 righe per ogni spedizione (Ragione Sociale/Indirizzo/Rif. numerico/Importo/Colli/Peso/Volume/Segnacollo iniziale nella riga 1; Tipo Servizio/CAP-Città-Prov/Rif. alfanumerico/Segnacollo finale nella riga 2).
  - Controllo dinamico del salto pagina per garantire l'integrità e la coerenza delle coppie di righe.
  - Sezione finale con box bordato **RIEPILOGO** (Totale Spedizioni, Colli, Contrassegni, Importo Contrassegni, Peso e Volume) e blocco **FIRMA**.
  - Assegnato al pulsante *"Stampa Borderò Giornaliero PDF"* con apertura diretta del PDF in una nuova scheda browser (`target="_blank"`).

### 1.4.1

- **Gestione Parametri Payload CREATE REST BRT (`senderParcelType`, `isAlertRequired`, Contrassegno COD)**:
  - `senderParcelType` viene ora letto dalla configurazione del modulo (`MPBRTRESTAPI_SENDER_PARCEL_TYPE`, predefinito "ABBIGLIAMENTO", max 15 caratteri).
  - `isAlertRequired` viene forzato sempre al valore `'1'`.
  - I parametri del contrassegno (`isCODMandatory: '1'`, `cashOnDelivery`, `codPaymentType`, `codCurrency: 'EUR'`) vengono inseriti nel payload JSON inviato a BRT (e nel viewer JSON) **esclusivamente se l'importo del contrassegno è maggiore di 0**. Se l'importo è 0 o non specificato, questi 4 campi vengono rimossi dal payload.

### 1.4.0

- **Integrazione Pulsanti Toolbar Ordine ("Crea Segnacollo BRT" & "Tracking BRT") via `hookActionGetAdminToolbarButtons`**:
  - Aggiunti entrambi i pulsanti **"Crea Segnacollo BRT"** (apertura modale compilazione segnacollo) e **"Tracking BRT"** (apertura diretta tab tracking modulo per l'ordine corrente) alla toolbar di navigazione ordine PrestaShop 8 usando `ActionsBarButton`.
  - Integrata la logica di filtraggio per stato ordine (`MPBRTRESTAPI_ORDERSTATES_DISPLAY`) e il controllo permessi SuperAdmin all'interno dell'hook `hookActionGetAdminToolbarButtons`.
  - Rimosso l'hook obsoleto `hookDisplayDashboardToolbarTopMenu`.

### 1.3.9

- **Fix Caricamento Asset JS/CSS e Rendering Modale su Symfony AdminOrders**:
  - Risolto l'errore `Uncaught ReferenceError: brtRestApiOpenDialog is not defined`: aggiunti gli hook `actionAdminControllerSetMedia`, `displayAdminOrderMainBottom` e `displayAdminOrderSide` per garantire il caricamento costante delle librerie JavaScript, degli stili CSS e del modale HTML `<dialog id="brtRestApiDialog">` nelle pagine degli ordini gestite dal controller Symfony di PrestaShop 8.

### 1.3.8

- **Integrazione Pulsante "Crea Segnacollo BRT" nella Toolbar Ordini PrestaShop**:
  - Implementato l'hook `actionGetAdminToolbarButtons` in `mpbrtrestapishipments.php`: quando un operatore visualizza un ordine nel Back-Office (`AdminOrders`), la barra superiore delle azioni dell'ordine include il nuovo pulsante **"Crea Segnacollo BRT"** (con icona `local_shipping`). Cliccandolo viene aperto ed auto-compilato direttamente il modale della richiesta spedizione per l'ordine corrente.

### 1.3.7

- **Logica Differenziata Sandbox / Produzione per Codici Pagamento Contrassegno**:
  - Implementata la verifica dell'ambiente attivo in `BrtShipmentRequest.php`: in ambiente Sandbox i campi `codPaymentType` e `codCurrency` vengono sempre trasmessi come stringa vuota `""` per conformità con l'account di test BRT (evitando l'errore `-68`), mentre in Produzione vengono inviati i codici specifici previsti dal contratto commerciale del cliente.

### 1.3.6

- **Fix `codPaymentType` e `codCurrency` per Contrassegno (Specifica SoapUI BRT)**:
  - Corretto l'errore BRT `-68` (`WRONG OR INCONSISTENT DATA - codPaymentType codCurrency`): in conformità con i progetti demo SoapUI ufficiali di BRT REST API, per le spedizioni in contrassegno `codPaymentType` e `codCurrency` devono essere valorizzati a stringa vuota `""`. In questo modo BRT applica automaticamente il tipo di pagamento predefinito e la valuta EUR del contratto cliente.

### 1.3.5

- **Fix Parametri Contrassegno (COD) per Spedizioni a €0.00**:
  - Risolto l'errore BRT `-68` (`WRONG OR INCONSISTENT DATA - codPaymentType codCurrency`): quando l'importo del contrassegno (`cashOnDelivery`) è pari a `0` o non specificato, i parametri `cashOnDelivery`, `isCODMandatory`, `codPaymentType` e `codCurrency` vengono ora completamente rimossi dal payload JSON prima dell'invio a BRT e nel viewer JSON.

### 1.3.4

- **Inversione Layout Pulsanti Modale & Viewer JSON Richiesta**:
  - Invertita la disposizione dei pulsanti nel footer del modale spedizione (`modal-shipment.html.twig`): i pulsanti d'azione (`Crea Segnacollo BRT`, `Vedi Richiesta`, `Stampa Segnacollo`, `Annulla Spedizione`) sono ora posizionati a sinistra ed il pulsante `Chiudi` a destra.
  - Aggiunto il nuovo pulsante **"Vedi Richiesta (JSON)"** con modale dedicato `<dialog id="brtJsonViewerDialog">` per l'ispezione ed il debug in tempo reale del payload JSON inviato all'endpoint `POST /shipment` di BRT con supporto per la copia negli appunti.

### 1.3.3

- **Spiegazione Chiara Errore `-67` Annullamento Spedizioni**:
  - Sostituita la generica ed ambigua dicitura di sistema `USER/ACCOUNT VIOLATION` per l'errore `-67` con l'indicazione tecnica della documentazione BRT: *"Spedizione non ancora annullabile. L'invocazione dell'annullamento deve essere eseguita dopo almeno 5 minuti dalla creazione della spedizione."*

### 1.3.2

- **Migliorata Formattazione Messaggi di Errore BRT**:
  - Inclusione dinamica della descrizione esplicita dell'errore (`codeDesc`, es: `USER/ACCOUNT VIOLATION`), della gravità (`severity`, es: `ERROR` / `WARNING`) e del messaggio esteso nel messaggio notificato all'utente ed evidenziato nelle modali e nei log (`BRT error [-67] (ERROR): USER/ACCOUNT VIOLATION`).

### 1.3.1

- **Fix Spedizioni Manuali Svincolate da ID Ordine**:
  - Rimosso il vincolo bloccante `ID ordine mancante` consentendo la creazione di segnacolli manuali non legati ad un ordine PrestaShop (`id_order = 0`) tramite `numericSenderReference` / timestamp.
- **Correzione Payload REST API BRT & Gestione Warning / Errori**:
  - Corretto il nome del campo cellulare in `consigneeMobilePhoneNumber` conforme alle specifiche REST BRT.
  - Pulizia automatica dell'array helper `parcels` dal payload inviato a BRT per evitare errori di campo sconosciuto (`code -68`).
  - Ottimizzazione parser delle risposte BRT: `severity: "WARNING"` (come codice 4 `DATA NORMALIZATION DONE`) viene ora trattato come **creazione avvenuta con successo** con salvataggio nel database e registrazione nel borderò.
  - Estesa la lunghezza massima della colonna `execution_message` a 512 caratteri con troncamento sicuro a 500 caratteri.
- **Unione PDF Multicollo & Stampa Segnacollo Interattiva**:
  - Integrazione della libreria `setasign/fpdi` ed aggiunta della classe helper `BrtPdfMerger.php`.
  - Unione automatica di più etichette PDF Base64 in un unico documento PDF multi-pagina in caso di spedizioni con più colli.
  - Generazione di un Blob URL nativo con apertura automatica del PDF in una nuova scheda pronta per la stampa ed avviso visivo via modale `<dialog>` in caso di errori.
  - Attivati i pulsanti d'azione di riga (`Stampa` ed `Annulla`) nella tabella Registro Spedizioni BRT.

### 1.3.0

- **Parser Dinamico `pricingConditionCode` & Validatore Sintattico da Back-Office**:
  - Implementazione della classe `BrtPricingRuleParser.php` per la valutazione dinamica e configurabile delle regole del codice tariffario (`pricingConditionCode`).
  - Supporto per gli operatori `=, !=, >, >=, <, <=, RANGE "min,max", IN`.
  - Gestione del valore speciale **`VUOTO`** per l'assegnazione esplicita della stringa vuota `""`.
  - Nuova tabella interattiva nelle impostazioni con aggiunta/rimozione/riordinamento delle regole, selettore `VUOTO` integrato e sezione footer toggle con tag cliccabili (`{network}`, `{numberOfParcels}`, `{weightKG}`, `{volumeM3}`, `{serviceType}`, `{consigneeCountryAbbreviationISOAlpha2}`, ecc.) per l'inserimento senza errori di digitazione.
  - Implementazione del validatore di sintassi in tempo reale con modale nativo HTML `<dialog id="brtRuleSyntaxErrorDialog">` che notifica errori di battitura (con ricerca Levenshtein per campi simili), parentesi sbilanciate o sintassi errate e fornisce il pulsante per la correzione automatica.
  - Implementazione del pulsante **"Testa BRT"** (`#btn-test-brt`) che esegue un test in tempo reale della connessione e delle credenziali alle REST API BRT (in ambiente Sandbox o Produzione) tramite l'endpoint di lettura `getRouting`.

### 1.2.0

- **Gestione Misure Pacchi & Tabella `brt_restapi_weight`**:
  - Creazione della tabella `ps_brt_restapi_weight` con chiave univoca su `(reference_number, progressivo)` ed associazione al campo `id_order`.
  - Creazione della classe ObjectModel `ModelBrtRestApiWeight.php` per la persistenza ed il calcolo automatico dei volumi e dei totali di spedizione.
  - Implementazione dell'endpoint API Front Controller via HTTP GET (`index.php?fc=module&module=mpbrtrestapishipments&controller=weight`) che permette l'inserimento/aggiornamento dei pacchi trasmettendo parametri come `code=10299-1&weight=2.5&x=30&y=20&z=15`.
  - Integrata la nuova sezione nella modal di compilazione spedizione con tabella interattiva per l'inserimento delle misure $x, y, z$ (in cm), calcolo automatico del volume per riga ($m^3$), supporto per indicatore Busta (`is_envelope`) e ricalcolo dinamico in tempo reale dei totali globali della spedizione (Colli, Peso KG, Volume $M^3$).

### 1.1.7

- Restyling completo ultra-compatto della scheda di compilazione richiesta segnacollo BRT:
  - Riorganizzati tutti i campi in 2 sezioni logiche principali (*Destinatario & Contatti*, *Parametri Spedizione & BRT* con blocco *Contrassegno* integrato).
  - Ottimizzate le altezze degli input (`30px`), le dimensioni dei font e le spaziature dei form.
  - Eliminato qualsiasi scorrimento orizzontale/verticale su schermi desktop (altezza complessiva del modale ~460px).

### 1.1.6

- Rifattorizzate le sezioni interne del modale utilizzando la struttura standard PrestaShop 8 (`card`, `card-header`, `card-body` e `card-footer`).

### 1.1.5

- Rimosso il blocco interno di ricerca ordine nel modale della richiesta (in quanto presente il pulsante d'azione dedicato "NUOVO SEGNACOLLO DA ORDINE").
- Ridotta la densità delle spaziature dei form (`mb-2` / `height: 32px`) ed ottimizzata la griglia Bootstrap (`col-12 col-lg-6`) per garantire che l'intero form rientri in altezza senza scroll su desktop.
- Aggiunta la responsività completa per dispositivi mobili con breakpoint `@media (max-width: 768px)`.

### 1.1.4

- Ottimizzato e rifattorizzato l'aspetto visivo del modale `<dialog id="brtRestApiDialog">`:
  - Riorganizzata la barra superiore di ricerca ordine in una scheda orizzontale pulita (`form-row align-items-center justify-content-between`).
  - Distribuite le colonne "Dati Destinatario" e "Dettagli Spedizione BRT" su due schede a sfondo bianco ben definite con icone descrittive.
  - Rifattorizzata la barra dei pulsanti a piè di pagina con stili Bootstrap 4 per PrestaShop 8, distanziamento e icone allineate.

### 1.1.3

- Aggiunti nella scheda **Spedizioni & Borderò** i pulsanti d'azione:
  - **`NUOVO SEGNACOLLO`**: Apre il modale universale con i campi della richiesta completamente vuoti, pronti per l'inserimento manuale dei dati.
  - **`NUOVO SEGNACOLLO DA ORDINE`**: Apre il modale di selezione `<dialog id="brtSelectOrderDialog">` per inserire l'ID ordine PrestaShop e caricare i dati compilando automaticamente il form della richiesta.

### 1.1.2

- Popolato il file CSS [style-override.css](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpbrtrestapishipments/views/css/style-override.css) con le utility di layout Flexbox (`d-flex`, `justify-content-center`, `align-items-center`, `gap-1`, `gap-2`, `gap-3`), regole per le schede PrestaShop 8 (`card`, `card-header`, `card-body`), badge e form Chosen.
- Registrato `style-override.css` nel metodo `setMedia` del controller amministrativo `AdminMpBrtRestApiShipmentsController`.

### 1.1.1

- Rifattorizzati tutti i template Twig del Back Office (`tab_settings.html.twig`, `tab_shipments.html.twig`, `tab_stats.html.twig`, `tab_tracking.html.twig`, `layout.html.twig`): rimosse le classi CSS custom obsolete (`brt-card`, `brt-card-header`, `brt-card-body`) e sostituite con le classi nativi Bootstrap 4 / PrestaShop 8 (`card`, `card-header`, `card-body`, `card-footer`, `table`, `badge`, `alert`).

### 1.1.0

- Implementazione completa della **Sezione 1** (Segnacollo, Stampa & Borderò).
- Focalizzazione sui comandi REST **POST create** (richiesta segnacollo) e **PUT delete** (annullamento spedizione).
- Creazione del componente modale universale `<dialog>` (`modal-shipment.html.twig`) richiamabile da qualsiasi pagina o hook.
- Implementazione della classe JavaScript modulare `BrtShipmentModal` in Vanilla JS con chiamate AJAX via `fetch()` in formato `application/x-www-form-urlencoded`.
- Auto-compilazione dinamica dei dati di spedizione partendo da un ordine PrestaShop (`BrtShipmentRequest::extractDataFromOrder`).
- Creazione del modello di dati `ModelBrtRestApiBordero` e tabella database `ps_brt_restapi_bordero` per il tracciamento dei segnacolli e la gestione delle stampe.
- Implementazione dell'helper `BrdPdfGenerator` per la generazione e stampa del manifesto di spedizione giornaliero (Borderò).

### 1.0.0

- Inizializzazione della prima versione stabile del modulo `mpbrtrestapishipments`.
- Integrazione completa con le REST API ufficiali BRT Bartolini per Creazione (`POST /shipment`), Conferma (`PUT /shipment`), Cancellazione (`PUT /delete`) e Routing (`PUT /routing`).
- Integrazione con l'API BRT Tracking per il recupero ed il tracciamento in tempo reale degli eventi di consegna (`GET /parcelID/{parcelID}`).
- Creazione della dashboard Back Office con visualizzazione statistiche di consegna e registro storico ordini.
- Implementazione della creazione tabelle database `ps_mpbrtrestapi_shipment_request`, `ps_mpbrtrestapi_shipment_response` e `ps_mpbrtrestapi_tracking`.
- Inserimento pulsante rapido per la generazione spedizioni BRT nella pagina dettaglio dell'ordine nel Back Office (`hookDisplayDashboardToolbarTopMenu`).
