# Depanare

Probleme frecvente intalnite la utilizarea pluginului si solutiile lor.

## Plata nu functioneaza deloc

### Simptom: Clientul nu este redirectionat catre MPay

Cauze posibile:
1. Metoda de plata nu este activata - verificati in WooCommerce > Settings > Payments
2. ServiceID gresit sau lipsa - verificati in setarile pluginului
3. Eroare JavaScript pe pagina checkout - verificati consola browser (F12)
4. Plugin de cache serveste pagina checkout din cache - excludeti pagina checkout din cache

### Simptom: Redirect functioneaza dar MPay afiseaza eroare

Cauze posibile:
1. ServiceID nu corespunde mediului (test vs productie)
2. Profilul selectat nu corespunde certificatului incarcat
3. URL-ul magazinului s-a schimbat dar ReturnUrl-ul nu s-a actualizat

## MPay nu poate apela endpoint-ul SOAP

### Simptom: Comanda ramane "Pending" dupa plata

Aceasta este cea mai frecventa problema. MPay nu poate contacta magazinul.

Verificati:
1. **Endpoint accesibil** - Accesati URL-ul endpoint-ului in browser
2. **HTTPS valid** - Certificatul SSL nu este expirat
3. **Firewall** - Nu blocheaza IP-urile MPay
4. **Plugin securitate** - Wordfence, Sucuri etc. pot bloca cereri SOAP
5. **Cloudflare** - Modul "Under Attack" blocheaza cereri automate
6. **Maintenance mode** - Dezactivati inainte de a testa

Solutii rapide:
```
# Testati endpoint-ul din exterior
curl -X POST https://domeniul-dvs.md/wc-api/mpay-soap/ -H "Content-Type: text/xml"

# Verificati ca URL-ul raspunde
wget --spider https://domeniul-dvs.md/wc-api/mpay-soap/
```

## Erori semnatura WS-Security

### "Signature verification failed"

- Certificatul incarcat nu corespunde mediului MPay selectat
- Algoritmul (rsa-sha1 vs rsa-sha256) nu se potriveste cu cel asteptat de MPay
- Fisierul certificat este corupt - reincarcati-l

### "Digest mismatch"

- Un proxy/WAF modifica continutul cererii SOAP in tranzit
- Verificati ca nu exista transformari de continut (minificare, compresie pe content text/xml)

### "Timestamp expired"

- Ceasul serverului nu este sincronizat - configurati NTP
- Diferenta acceptabila: maxim 5 minute
```bash
# Verificati si sincronizati ceasul
date
ntpdate -q pool.ntp.org
```

## Erori certificat

### "Cannot read PKCS12 file"

- Fisierul este corupt - descarcati din nou de la sursa
- Passphrase-ul este gresit - verificati cu openssl:
```bash
openssl pkcs12 -in certificat.pfx -nokeys -passin pass:PAROLA_DVS
```

### "Private key not found in PKCS12"

- Fisierul a fost exportat fara cheie privata
- Reexportati certificatul incluzand cheia privata

### "Certificate expired"

- Obtineti un certificat nou de la emitent
- Incarcati-l din tab-ul Certificate

## Probleme Voucher Cultural

### Camp IDNP nu apare la checkout

- Voucher Cultural nu este activat in setari
- JavaScript-ul de pe checkout este blocat de alt plugin
- Theme-ul suprascrie template-ul checkout

### "Produse neeligibile" dar produsele sunt culturale

- Categoriile produselor nu sunt adaugate in lista de categorii eligibile
- Produsul nu are categorie asignata
- Verificati ca folositi categoriile WooCommerce (nu tag-uri)

## Probleme de performanta

### Raspuns SOAP lent (timeout)

- Baza de date lenta - optimizati tabelele WooCommerce
- Prea multe plugin-uri care se incarca la fiecare cerere
- Obiect cache (Redis/Memcached) poate ajuta

### Confirmari duplicate procesate

- Mecanismul transient nu functioneaza - verificati obiect cache
- Daca folositi Redis, asigurati-va ca transient-urile persista corect

## Log-uri utile

Activati WooCommerce logging:
1. **WooCommerce > Status > Logs** - selectati fisierul `mpay-vg-*`
2. **wp-content/debug.log** - daca `WP_DEBUG_LOG` este true
3. **Access log server** - cereri catre endpoint-ul SOAP
4. **Error log PHP** - erori fatale

## Cand sa contactati suportul

Daca problemele persista:
1. Generati un snapshot din tab-ul Diagnosticare
2. Notati pasii exacti de reproducere
3. Trimiteti la incontact@terabitlab.com
