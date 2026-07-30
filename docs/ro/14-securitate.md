# Securitate

Consideratii de securitate pentru operarea pluginului MPay Voucher Gateway in productie.

## Protectie certificate si chei

### Stocare certificate

- Certificatele sunt stocate in `wp-content/uploads/mpay-vg-certificates/`
- Directorul trebuie protejat impotriva accesului HTTP direct
- Permisiuni recomandate: director 750, fisiere 640

### Protectie .htaccess

Pluginul creeaza automat un fisier `.htaccess` in directorul de certificate:
```apache
<Files "*">
  Require all denied
</Files>
```

Pentru nginx, adaugati manual:
```nginx
location ~* /wp-content/uploads/mpay-vg-certificates/ {
    deny all;
    return 404;
}
```

### Passphrase

- Stocat criptat in baza de date WordPress (wp_options)
- Nu este afisat in clar in interfata admin dupa salvare
- Nu este inclus in snapshot-uri de diagnosticare
- Nu este logat niciodata in fisiere log

## Rate limiting

### Endpoint SOAP

Pluginul implementeaza protectie impotriva abuzului pe endpoint-ul SOAP:
- Limita cereri per minut de la aceeasi adresa IP
- Cereri cu semnatura invalida sunt respinse imediat
- Cererile fara header WS-Security sunt ignorate

### Recomandari suplimentare

La nivel de server sau WAF:
- Limitati rata cererilor POST catre endpoint-ul SOAP
- Permiteti doar IP-urile MPay cunoscute (daca sunt documentate)
- Monitorizati volumul cererilor pentru anomalii

## Validare input

Pluginul valideaza toate datele primite:

| Input | Validare |
|-------|----------|
| OrderKey | Format specific WooCommerce, exista in baza de date |
| TransactionID | Alfanumeric, lungime limitata |
| Amount | Numeric pozitiv, doua zecimale |
| IDNP | Exact 13 cifre |
| Mesaje SOAP | Schema XML valida, semnatura verificata |

## Protectie impotriva atacurilor

### Replay attacks

- Timestamp WS-Security cu fereastra de 5 minute
- Transient lock previne procesarea dubla a aceleiasi confirmari
- OrderKey este unic per comanda

### XML injection

- Parsarea XML se face cu libxml in modul sigur
- External entities (XXE) sunt dezactivate:
```php
libxml_disable_entity_loader(true);
```
- Input-ul XML este validat inainte de procesare

### SQL injection

- Toate query-urile folosesc WordPress prepared statements
- OrderKey este sanitizat inainte de cautare in baza de date

### XSS (Cross-Site Scripting)

- Datele din raspunsurile MPay sunt escaped la afisare in admin
- Meta-datele comenzii sunt sanitizate

## Idempotenta platilor

Mecanismul de idempotenta previne:
- Plati duplicate (aceeasi confirmare procesata de doua ori)
- Race conditions (doua cereri simultane pentru aceeasi comanda)
- Implementare: transient WordPress cu TTL scurt ca mutex

## Logging securizat

Ce NU se logheaza niciodata:
- Passphrase certificat
- Cheie privata
- IDNP complet (se afiseaza mascat: ****\*\*\*\*\*1234)
- Continut complet al mesajelor SOAP (doar metadata)

Ce se logheaza:
- Timestamp cereri
- OrderKey
- Status operatii (success/failure)
- Erori de semnatura (fara continut mesaj)

## Actualizari de securitate

- Actualizati pluginul prompt la aparitia versiunilor noi
- Monitorizati expirarea certificatelor
- Rotiti certificatele conform politicii organizatiei
- Verificati periodic log-urile pentru activitate suspecta

## Conformitate

- Pluginul nu stocheaza date de card (platile se proceseaza pe MPay)
- IDNP este stocat in meta-date comanda (acces restrictionat la admin)
- Log-urile respecta principiul minimizarii datelor

## Checklist securitate productie

- [ ] HTTPS activ cu certificat SSL valid
- [ ] Directorul certificate protejat impotriva accesului HTTP
- [ ] Permisiuni fisiere restrictive (640)
- [ ] Firewall configurat
- [ ] Plugin-uri securitate nu blocheaza endpoint-ul SOAP
- [ ] NTP sincronizat pe server
- [ ] Log-uri monitorizate
- [ ] Certificat MPay valid si neexpirat
- [ ] Modul verificare semnatura pe "Strict"
- [ ] Backup regulat baza de date
