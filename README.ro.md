> Aveți nevoie de implementarea MPay sau Voucher Cultural pe website-ul dumneavoastră WordPress sau WooCommerce? Contactați TerabitLab la [**incontact@terabitlab.com**](mailto:incontact@terabitlab.com).

[![English](assets/language/english-inactive.svg)](README.md) [![Romana](assets/language/romanian-active.svg)](README.ro.md)

# Gateway WooCommerce pentru integrări MPay Moldova și Voucher Cultural

Dezvoltat de TerabitLab pentru a sprijini adoptarea MPay și pentru a simplifica integrările MPay Moldova și Voucher Cultural pentru comercianții și dezvoltatorii care folosesc WordPress și WooCommerce.

## Ce face acest plugin

Acesta este un gateway funcțional de plată WooCommerce care conectează magazinele online la sistemul de plăți MPay Moldova și la programul Voucher Cultural. Gestionează ciclul complet de plată: redirecționarea clientului, verificarea plății prin SOAP cu semnături digitale XML WS-Security și actualizarea automată a statusului comenzii.

## Cum funcționează fluxul de plată

1. Clientul selectează MPay la checkout.
2. Pluginul redirecționează browserul către MPay prin formular HTTP POST (ServiceID, OrderKey, ReturnUrl).
3. Clientul plătește pe portalul MPay.
4. MPay apelează endpoint-ul SOAP al magazinului cu GetOrderDetails. Magazinul răspunde cu datele comenzii.
5. După plată, MPay apelează ConfirmOrderPayment pe endpoint-ul magazinului. Magazinul înregistrează plata și actualizează comanda WooCommerce.
6. ReturnUrl readuce browserul în magazin. Aceasta este doar informativă și nu confirmă plata.

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

## Instalare

1. Încărcați folderul pluginului în wp-content/plugins/
2. Activați pluginul în admin WordPress
3. Accesați pagina de setări MPay (link-ul Settings apare pe pagina de pluginuri)
4. Selectați un profil de configurare sau folosiți Custom
5. Configurați Service ID
6. Configurați căile certificatelor și parola
7. Configurați detaliile bancare (beneficiar, cod bancă, cod fiscal, IBAN)
8. Testați cu o tranzacție mică în mediul de test

## Configurare

Toate setarile sunt gestionate prin pagina de setari admin (meniul MPay Gateway din WordPress admin). Pluginul suporta profile de configurare pentru medii de test si productie.

## Documentatie

Documentatie completa bilingva: [English](docs/en/README.md) | [Romana](docs/ro/README.md)

Documente cheie:
- [Certificate si procedura STISC](docs/ro/05-certificate.md) - ce trebuie obtinut, cum, intrebari de pus
- [Flux plata si checklist productie](docs/ro/06-flux-plata.md) - fluxul complet, cerinte MPay
- [WS-Security](docs/ro/08-ws-security.md) - flux criptografic, reguli XML-DSig
- [Depanare](docs/ro/13-depanare.md) - probleme frecvente si depanare avansata

## Diagrama flux plata

![Flux plata](assets/diagrams/mpay-woocommerce-payment-flow.svg)

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

Source-available, utilizare comerciala restrictionata. Gratuit pentru evaluare, studiu si testare.

Utilizarea comerciala pe propriul magazin necesita notificare prin email la [incontact@terabitlab.com](mailto:incontact@terabitlab.com) inainte de lansare. Instalari pentru agentii, multi-site si clienti necesita licenta comerciala.

Vezi [LICENSE](LICENSE) pentru termeni completi.

## Program referral

Castiga comision 15% referind noi clienti comerciali la TerabitLab. Vezi [referral/PROGRAM.md](referral/PROGRAM.md) pentru detalii si formulare.

