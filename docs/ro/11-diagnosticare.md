# Diagnosticare

Pluginul include instrumente integrate de diagnosticare pentru verificarea starii sistemului si depanarea problemelor.

## Consola debug

Accesibila din **WooCommerce > Settings > Payments > MPay > Tab Diagnosticare**.

Consola permite:
- Testarea manuala a endpoint-ului SOAP
- Verificarea semnaturii unui mesaj de test
- Inspectarea raspunsului la o cerere GetOrderDetails simulata
- Vizualizarea configuratiei active (fara date sensibile)

## Portal diagnosticare

Portalul ofera o viziune completa asupra starii pluginului:

### Verificari automate

| Verificare | Ce testeaza |
|------------|-------------|
| PHP Version | Versiunea PHP >= 7.4 |
| OpenSSL Extension | Extensia openssl incarcata |
| SOAP Extension | Extensia soap incarcata |
| HTTPS | Site-ul ruleaza pe HTTPS |
| Certificate | Certificat valid si neexpirat |
| Endpoint Access | Endpoint SOAP accesibil din exterior |
| WooCommerce | Versiune compatibila |
| Permissions | Permisiuni fisiere corecte |

Fiecare verificare afiseaza: OK, Warning sau Error cu detalii.

### Status indicator

- Verde - Toate verificarile trec
- Galben - Avertismente (functioneaza dar cu riscuri)
- Rosu - Erori critice (platile nu vor functiona)

## Snapshot

Functia snapshot exporta starea curenta a pluginului intr-un format text structurat. Include:

- Versiuni (plugin, PHP, WordPress, WooCommerce)
- Configuratie activa (fara passphrase sau chei private)
- Status certificat
- Ultimele 10 evenimente din log
- Rezultatele verificarilor automate
- Informatii server relevante

### Generare snapshot

1. Navigati la tab-ul Diagnosticare
2. Apasati "Generate Snapshot"
3. Copiati textul generat

Snapshot-ul este util cand solicitati suport tehnic la incontact@terabitlab.com.

## Log evenimente

Pluginul inregistreaza evenimentele importante:

| Eveniment | Ce se logheaza |
|-----------|----------------|
| Cerere SOAP primita | Timestamp, operatie, OrderKey |
| Raspuns SOAP trimis | Timestamp, status, durata procesare |
| Confirmare plata | OrderKey, TransactionID, suma |
| Eroare semnatura | Detalii eroare, mesaj primit |
| Certificat warning | Zile pana la expirare |
| Eroare generala | Stack trace, context |

### Vizualizare log

- Din tab-ul Diagnosticare - ultimele N evenimente
- Din WooCommerce > Status > Logs - fisier log dedicat `mpay-vg-*`
- Prin WP-CLI: `wp mpay event-log`

### Retentie log

Log-urile sunt rotite automat. Durata retentie implicita: 30 zile. Fisierele vechi sunt sterse de WooCommerce prin mecanismul standard de log cleanup.

## Instrumente suplimentare

### Test conectivitate

Verifica daca endpoint-ul SOAP al magazinului este accesibil din exterior. Rezultatul indica:
- HTTP status code
- Timp raspuns
- Headere relevante

### Verificare certificat

Afiseaza:
- Subject si Issuer
- Serial number
- Data emitere si expirare
- Algoritm semnatura
- Fingerprint SHA-256

### Simulare cerere

Permite trimiterea unei cereri GetOrderDetails de test folosind un OrderKey real sau fictiv. Utila pentru verificarea ca raspunsul SOAP este corect format si semnat.

## Cand sa folositi diagnosticarea

- La instalare initiala - verificati ca totul este configurat corect
- Dupa actualizare plugin sau server - verificati ca nimic nu s-a stricat
- Cand platile nu functioneaza - identificati pasul care esueaza
- Inainte de a contacta suportul - generati un snapshot
