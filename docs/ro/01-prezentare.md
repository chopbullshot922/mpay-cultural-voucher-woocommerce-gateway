# Prezentare generala

MPay Voucher Gateway este un plugin WooCommerce (v14.3.2) dezvoltat de TerabitLab care integreaza magazinul online cu sistemul de plata MPay si programul Voucher Cultural din Republica Moldova.

## Ce face pluginul

- Adauga o metoda de plata WooCommerce pentru MPay
- Gestioneaza fluxul complet de plata: redirect catre MPay, primire confirmare, actualizare comanda
- Suporta plati prin Voucher Cultural cu verificare eligibilitate produse
- Implementeaza comunicare SOAP cu semnatura WS-Security (X.509)
- Expune un endpoint SOAP pe care MPay il apeleaza pentru a obtine detalii comanda si a confirma plata
- Gestioneaza certificate PKCS#12 (.pfx/.p12) pentru semnatura digitala

## Pentru cine este

Pluginul este destinat magazinelor online WooCommerce din Republica Moldova care doresc sa accepte plati prin:

- Sistemul MPay (plati electronice)
- Programul Voucher Cultural (plati cu vouchere culturale)

## Trei componente de integrare

Integrarea are trei componente distincte:

1. **HTTPS/TLS** - protejeaza conexiunea de retea (poate fi Let's Encrypt)
2. **Certificatul de sistem al comerciantului** - semneaza raspunsurile SOAP trimise catre MPay (obtinut de la STISC pentru productie)
3. **Certificatul public MPay** - verifica cererile SOAP primite de la MPay

Aceste certificate NU sunt interschimbabile. Vezi docs/ro/05-certificate.md pentru procedura completa STISC.

## Arhitectura

Namespace-ul PHP principal este `MPAY_VG\`. Structura directoarelor:

```
includes/
  Admin/    - Pagini setari, meta box-uri
  CLI/      - Comenzi WP-CLI
  Core/     - Logica centrala plugin
  Soap/     - Server SOAP, WS-Security
  Woo/      - Integrare WooCommerce gateway
```

## Profile de configurare

Pluginul ofera profile predefinite pentru conectare rapida:

| Profil | Utilizare |
|--------|-----------|
| store_test | Testare magazin pe mediul de test MPay |
| store_prod | Productie magazin |
| terabitlab_test | Testare cu credentialele TerabitLab |
| custom | Configurare manuala completa |

## Licenta

Source-available, utilizare comerciala restrictionata. Gratuit pentru evaluare si testare. Utilizarea comerciala pe propriul magazin necesita notificare la incontact@terabitlab.com. Instalari agentii/multi-site necesita licenta comerciala.

## Contact

Pentru suport tehnic: incontact@terabitlab.com

## Versiune curenta

v14.3.2 - include suport rsa-sha256, diagnosticare avansata, si comenzi CLI.

## Cum functioneaza pe scurt

1. Clientul alege MPay la checkout
2. Magazinul trimite un formular HTTP POST catre MPay (ServiceID, OrderKey, ReturnUrl)
3. MPay apeleaza magazinul prin SOAP pentru detalii comanda
4. Dupa plata, MPay confirma prin SOAP (ConfirmOrderPayment)
5. Comanda se actualizeaza automat in WooCommerce

Fluxul detaliat este descris in documentul 06-flux-plata.md.
