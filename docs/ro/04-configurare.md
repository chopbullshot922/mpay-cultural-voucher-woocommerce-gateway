# Configurare

Toata configurarea pluginului se face din pagina de setari admin WordPress. Pluginul nu citeste constante din wp-config.php - toate optiunile sunt gestionate exclusiv prin interfata admin.

## Accesare pagina setari

Navigati la: **WooCommerce > Settings > Payments > MPay**

Alternativ, folositi link-ul "Settings" de langa plugin in pagina Plugins.

## Profile de configurare

Profilurile preincarca automat valorile corecte pentru endpoint-uri si parametri. Dupa selectarea unui profil, campurile se populeaza automat.

| Profil | Endpoint | Utilizare |
|--------|----------|-----------|
| store_test | Mediu test MPay | Dezvoltare si testare |
| store_prod | Mediu productie MPay | Magazin live |
| terabitlab_test | Mediu test TerabitLab | Testare initiala rapida |
| custom | Configurabil manual | Scenarii speciale |

La schimbarea profilului, endpoint-urile si ServiceID-ul se actualizeaza automat.

## Tab-uri setari

### Tab General

- **Activare/Dezactivare** - Toggle pornit/oprit metoda de plata
- **Titlu** - Numele afisat la checkout (ex: "Plata MPay")
- **Descriere** - Text afisat sub titlu la checkout
- **Profil** - Selectare profil configurare
- **ServiceID** - Identificatorul serviciului alocat de MPay
- **ReturnUrl** - URL-ul de intoarcere dupa plata (generat automat)

### Tab Certificate

- **Fisier certificat** - Upload PKCS#12 (.pfx/.p12)
- **Passphrase** - Parola certificatului
- **Cale certificat** - Afisare cale fisier pe disk
- **Status certificat** - Valid/Expirat/Lipsa
- **Data expirare** - Data expirarii certificatului

### Tab SOAP

- **Endpoint SOAP magazin** - URL-ul endpoint-ului SOAP expus de plugin
- **Algoritm semnatura** - rsa-sha1 sau rsa-sha256
- **Mod verificare** - Strict sau permisiv pentru semnatura raspuns

### Tab Voucher Cultural

- **Activare Voucher Cultural** - Toggle pentru functionalitate voucher
- **Categorii eligibile** - Categorii WooCommerce acceptate pentru voucher
- **Camp IDNP** - Activare camp identificare IDNP la checkout

### Tab Diagnosticare

- **Status conexiune** - Verificare comunicare cu MPay
- **Log evenimente** - Ultimele evenimente procesate
- **Snapshot** - Export stare curenta pentru depanare
- **Consola debug** - Teste manuale endpoint

## Salvare setari

Apasati "Save changes" in partea de jos a paginii. Modificarile se aplica imediat.

## Configurare recomandata pentru productie

1. Selectati profilul `store_prod`
2. Verificati ServiceID-ul primit de la MPay
3. Incarcati certificatul de productie
4. Setati algoritmul pe `rsa-sha256`
5. Dezactivati modul debug
6. Testati cu o tranzactie reala de valoare mica

## Observatii

- Schimbarea profilului nu sterge certificatul incarcat
- ServiceID-ul este specific fiecarui mediu (test vs productie)
- ReturnUrl-ul se genereaza automat pe baza permalink-urilor WordPress
- Daca permalink-urile se schimba, ReturnUrl-ul se actualizeaza automat
