# Instalare

Ghid pas cu pas pentru instalarea si activarea pluginului MPay Voucher Gateway.

## Metoda 1: Upload din admin WordPress

1. Descarcati arhiva ZIP a pluginului
2. Navigati la **Plugins > Add New > Upload Plugin** in admin WordPress
3. Selectati fisierul ZIP si apasati "Install Now"
4. Dupa instalare, apasati "Activate Plugin"

## Metoda 2: Upload manual via FTP/SFTP

1. Dezarhivati fisierul ZIP local
2. Incarcati directorul rezultat in `wp-content/plugins/`
3. Navigati la **Plugins** in admin WordPress
4. Gasiti "MPay Voucher Gateway" in lista si apasati "Activate"

## Metoda 3: WP-CLI

```bash
wp plugin install /cale/catre/mpay-voucher-gateway.zip --activate
```

## Verificare post-instalare

Dupa activare, verificati:

1. In **Plugins** - pluginul apare ca activ fara erori
2. In **WooCommerce > Settings > Payments** - metoda "MPay" este vizibila in lista
3. Nu exista erori PHP in log-ul WordPress (`wp-content/debug.log`)

## Setup initial

Dupa activare, urmati acesti pasi:

### Pas 1: Deschideti pagina de setari

Navigati la **WooCommerce > Settings > Payments > MPay** sau folositi link-ul "Settings" direct din pagina Plugins.

### Pas 2: Selectati profilul

Alegeti un profil de configurare:
- `store_test` pentru testare initiala
- `store_prod` cand sunteti gata de productie
- `custom` pentru configurare manuala

### Pas 3: Incarcati certificatul

Incarcati fisierul certificat PKCS#12 (.pfx sau .p12) si introduceti passphrase-ul asociat. Detalii complete in documentul 05-certificate.md.

### Pas 4: Verificati endpoint-ul SOAP

Accesati tab-ul de diagnosticare si confirmati ca endpoint-ul SOAP al magazinului este accesibil public pe HTTPS.

### Pas 5: Activati metoda de plata

Reveniti la **WooCommerce > Settings > Payments** si activati metoda MPay (toggle ON).

### Pas 6: Testati o comanda

Creati o comanda de test pentru a verifica:
- Redirect-ul catre MPay functioneaza
- MPay poate apela endpoint-ul magazinului
- Confirmarea platii actualizeaza statusul comenzii

## Actualizare plugin

Pentru actualizare, repetati procesul de upload. Setarile se pastreaza intre versiuni. Inainte de actualizare:

- Faceti backup la baza de date
- Notati versiunea curenta instalata
- Verificati changelog-ul pentru breaking changes

## Dezinstalare

1. Dezactivati pluginul din **Plugins**
2. Stergeti pluginul
3. Certificatele uploadate raman in directorul uploads - stergeti-le manual daca nu mai sunt necesare
