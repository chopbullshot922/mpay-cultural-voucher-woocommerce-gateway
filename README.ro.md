> Aveți nevoie de implementarea MPay sau Voucher Cultural pe website-ul dumneavoastră WordPress sau WooCommerce? Contactați TerabitLab la [**incontact@terabitlab.com**](mailto:incontact@terabitlab.com).

[![English](assets/language/english-inactive.svg)](README.md) [![Romana](assets/language/romanian-active.svg)](README.ro.md)

# Gateway WooCommerce pentru MPay Moldova și Voucher Cultural

[![Version](https://img.shields.io/badge/versiune-14.3.2-blue.svg)](../../releases) [![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4.svg)](https://php.net) [![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-21759B.svg)](https://wordpress.org) [![WooCommerce](https://img.shields.io/badge/WooCommerce-6.0%2B-96588A.svg)](https://woocommerce.com) [![Licență](https://img.shields.io/badge/licență-source--available-orange.svg)](LICENSE)

**Gateway de plată WooCommerce gata de producție pentru MPay Moldova și Voucher Cultural.**
Integrare completă SOAP/WS-Security cu semnare X.509, construit pentru utilizare reală de comercianți.

Dezvoltat de [TerabitLab](mailto:incontact@terabitlab.com).

<a href="../../releases"><img src="https://img.shields.io/badge/Descarcă_Plugin_ZIP-v14.3.2-success?style=for-the-badge" alt="Descarcă"></a>

---

## Cum funcționează fluxul de plată

1. Clientul selectează MPay la checkout.
2. Pluginul redirecționează browserul către MPay prin formular HTTP POST (ServiceID, OrderKey, ReturnUrl).
3. Clientul plătește pe portalul MPay.
4. MPay apelează endpoint-ul SOAP al magazinului cu GetOrderDetails. Magazinul răspunde cu datele comenzii.
5. După plată, MPay apelează ConfirmOrderPayment pe endpoint-ul magazinului. Magazinul înregistrează plata și actualizează comanda WooCommerce.
6. ReturnUrl readuce browserul în magazin. Aceasta este doar informativă și nu confirmă plata.

![Flux plată](assets/diagrams/mpay-woocommerce-payment-flow.svg)

---

## Instalare

1. Descărcați ZIP-ul pluginului din [Releases](../../releases)
2. În admin WordPress: **Plugin-uri > Adaugă nou > Încarcă plugin**
3. Selectați fișierul ZIP și apăsați **Instalează acum**
4. Apăsați **Activează pluginul**
5. Mergeți la **MPay Gateway** în meniul admin WordPress

Fără programare, fără FTP, fără editare de fișiere.

---

## Configurare: mediul de TEST

După activare, configurați totul din pagina de administrare a pluginului:

| Pas | Unde în plugin | Ce trebuie făcut |
|-----|---------------|-----------------|
| 1 | Dropdown profil | Selectați **store_test** sau **terabitlab_test** |
| 2 | Service ID | Introduceți Service ID-ul de test primit de la MPay |
| 3 | Tab WS-Security | Încărcați certificatul PFX de test și introduceți parola |
| 4 | Tab WS-Security | Încărcați certificatul public MPay de test |
| 5 | Tab detalii bancare | Introduceți detaliile bancare de test (beneficiar, IBAN, cod fiscal) |
| 6 | Salvare | Apăsați Salvare modificări |
| 7 | Testare | Plasați o comandă mică folosind MPay la checkout |

<img src="assets/screenshots/02-production-profile-and-serviceid.png" alt="Configurare profil și Service ID" width="800">

<img src="assets/screenshots/06-ws-security-certificate-settings.png" alt="Setări certificate WS-Security" width="800">

---

## Configurare: mediul de PRODUCȚIE

Când MPay confirmă că integrarea de test este aprobată:

| Pas | Unde în plugin | Ce trebuie făcut |
|-----|---------------|-----------------|
| 1 | Dropdown profil | Comutați pe **store_prod** |
| 2 | Service ID | Introduceți Service ID-ul de producție de la MPay |
| 3 | Tab WS-Security | Încărcați certificatul PFX de producție de la STISC și parola |
| 4 | Tab WS-Security | Încărcați certificatul public MPay de producție |
| 5 | Tab detalii bancare | Introduceți detaliile bancare reale |
| 6 | HTTPS | Confirmați că domeniul are certificat HTTPS valid |
| 7 | Salvare și verificare | Salvați, apoi plasați o tranzacție reală de sumă mică |

**Înainte de producție aveți nevoie de:**
- Certificat de sistem de producție de la STISC (vezi [Documentația certificate](docs/ro/05-certificate.md))
- Service ID de producție de la MPay
- Certificatul public MPay de producție
- HTTPS pe domeniu
- Aprobarea MPay a integrării de test
- IP-ul public al serverului comunicat la MPay (pot face whitelist)
- IP-urile MPay de producție trecute în whitelist pe firewall-ul serverului

Toată configurarea se face din pagina de setări a pluginului. Nu sunt necesare modificări de cod.

---

## Interfața admin a pluginului

### Setări principale

<img src="assets/screenshots/01-wordpress-admin-menu-entry.png" alt="Intrare meniu admin WordPress" width="700">

<img src="assets/screenshots/03-general-gateway-settings.png" alt="Setări generale gateway" width="800">

### Cont bancar și reguli de plată

<img src="assets/screenshots/04-merchant-bank-account-settings.png" alt="Setări cont bancar" width="800">

<img src="assets/screenshots/05-payment-rule-settings.png" alt="Setări reguli plată" width="800">

### Setări facturare și PDF

<img src="assets/screenshots/07-invoice-api-and-pdf-settings.png" alt="Setări API factură și PDF" width="700">

### Eligibilitate WooCommerce

<img src="assets/screenshots/08-woocommerce-eligibility-conditions.png" alt="Condiții eligibilitate" width="700">

<img src="assets/screenshots/09-woocommerce-eligibility-and-test-overrides.png" alt="Eligibilitate și suprascrieri test" width="700">

---

## Monitorizare și diagnosticare

### Log și sănătate

<img src="assets/screenshots/10-log-and-health-overview.png" alt="Prezentare log și sănătate" width="800">

<img src="assets/screenshots/11-debug-event-log.png" alt="Log evenimente debug" width="700">

<img src="assets/screenshots/12-event-history-and-monitoring-controls.png" alt="Istoric evenimente și monitorizare" width="700">

### Panou diagnosticare

<img src="assets/screenshots/14-diagnostics-dashboard-overview.png" alt="Panou diagnosticare" width="800">

<img src="assets/screenshots/15-soap-runtime-and-orderkey-inspector.png" alt="Inspector SOAP runtime" width="700">

<img src="assets/screenshots/16-structured-debug-event-stream.png" alt="Flux structurat evenimente debug" width="700">

### Playbook depanare

<img src="assets/screenshots/17-mpay-troubleshooting-playbook-scenarios-1-to-4.png" alt="Scenarii depanare" width="700">

<img src="assets/screenshots/18-mpay-troubleshooting-playbook-ws-security-toolkit.png" alt="Toolkit WS-Security" width="700">

### Statistici

<img src="assets/screenshots/19-mpay-statistics-dashboard.png" alt="Panou statistici" width="800">

---

## Informații plugin

<img src="assets/screenshots/13-plugin-information-and-attribution.png" alt="Informații plugin" width="600">

---

## Funcționalități

- Metode de plată MPay și Voucher Cultural pentru WooCommerce
- Server SOAP pentru GetOrderDetails și ConfirmOrderPayment
- Semnătură digitală XML WS-Security (semnare și verificare) cu certificate X.509
- Gestionare certificate PKCS#12 (PHP OpenSSL cu fallback CLI)
- Confirmare plată idempotentă cu blocare pe bază de transiente
- Suport plată parțială cu status de comandă personalizat
- Eligibilitate produse Voucher Cultural și identificare plătitor IDNP
- Monitorizare expirare certificate (cron zilnic)
- Pagină de setări admin cu profile de configurare (test/producție)
- Diagnosticare admin, statistici și meta box pe comandă
- Consolă debug remote cu autentificare prin cheie partajată
- Portal de diagnosticare și playbook public
- Log evenimente în baza de date cu persistență cereri SOAP
- Comenzi WP-CLI (cert-status, event-log, cleanup)
- Rate limiting și limite de dimensiune pe endpoint-ul SOAP
- Condiții configurabile de disponibilitate (min/max total, țări, metode livrare, doar virtual, guest)

## Cerințe

- PHP 7.4 sau superior
- WordPress 5.8 sau superior
- WooCommerce 6.0 sau superior
- Extensia PHP SOAP
- Extensia PHP OpenSSL
- Certificat X.509 valid (obținut în procesul de onboarding MPay)
- HTTPS pe domeniu

## Documentație

Documentație completă bilingvă: [English](docs/en/README.md) | [Romana](docs/ro/README.md)

Documente cheie:
- [Certificate și procedura STISC](docs/ro/05-certificate.md) - ce trebuie obținut, cum, întrebări de pus
- [Flux plată și checklist producție](docs/ro/06-flux-plata.md) - fluxul complet, cerințe MPay
- [WS-Security](docs/ro/08-ws-security.md) - flux criptografic, reguli XML-DSig
- [Depanare](docs/ro/13-depanare.md) - probleme frecvente și depanare avansată

## Structura pluginului

```
includes/
  Admin/
    Diagnostics.php
    MetaBox.php
    Notices.php
    Settings.php
    Stats.php
  CLI/
    Commands.php
  Core/
    CertMonitor.php
    DB.php
    DebugConsole.php
    DiagnosticsPortal.php
    DiagnosticsSnapshot.php
    DiagnosticsTools.php
    Invoices.php
    Logger.php
    OrderMapper.php
    PublicPlaybook.php
    Rewrites.php
    TestPlaybook.php
  Soap/
    Server.php
    WsSecurity.php
  Woo/
    Checkout.php
    Eligibility.php
    Emails.php
    Gateway_MPay.php
    OrderStatus.php
    Thankyou.php
  functions-helpers.php
mpay-voucher-gateway.php
uninstall.php
```

## Endpoint-uri

Pluginul înregistrează următoarele endpoint-uri URL:

- /mpay/redirect - Pagina de redirecționare care trimite browserul către MPay prin formular POST
- /mpay/soap - Server SOAP pentru callback-urile MPay (GetOrderDetails, ConfirmOrderPayment)
- /mpay/debug - Consolă debug remote (necesită cheie partajată)
- /mpay/diagnostics - Portal de diagnosticare
- /mpay/playbook - Playbook public

## Ce nu este inclus

Acest repository nu conține:

- Certificate reale sau chei private
- Parole sau passphrase-uri reale
- Service ID-uri reale
- Detalii bancare reale (IBAN, BIC, coduri fiscale)
- Configurații specifice clientului
- Loguri cereri/răspunsuri SOAP
- Documentație privată

Trebuie să obțineți propriile credențiale prin procesul de onboarding MPay.

## Servicii de implementare MPay și Voucher Cultural

Acest gateway a fost dezvoltat de TerabitLab pe baza experienței practice de integrare în WordPress și WooCommerce.

TerabitLab poate ajuta cu:

- instalarea și configurarea gateway-ului;
- adaptarea integrării pentru un magazin WooCommerce existent;
- configurarea parametrilor specifici comerciantului;
- configurarea gestionării certificatelor;
- implementarea confirmărilor de plată;
- testarea completă a fluxului de plată;
- diagnosticarea erorilor de integrare MPay;
- pregătirea website-ului pentru procesul de conectare și testare al comerciantului.

Pentru implementare, contactați [incontact@terabitlab.com](mailto:incontact@terabitlab.com).

## Licență

Source-available, utilizare comercială restricționată. Gratuit pentru evaluare, studiu și testare.

Utilizarea comercială pe propriul magazin necesită notificare prin email la [incontact@terabitlab.com](mailto:incontact@terabitlab.com) înainte de lansare. Instalări pentru agenții, multi-site și clienți necesită licență comercială.

Vezi [LICENSE](LICENSE) pentru termeni compleți.

## Program referral

Câștigă comision 15% referind noi clienți comerciali la TerabitLab. Vezi [referral/PROGRAM.md](referral/PROGRAM.md) pentru detalii și formulare.
