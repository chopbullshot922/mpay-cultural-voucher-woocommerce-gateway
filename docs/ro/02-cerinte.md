# Cerinte de sistem

Inainte de instalarea pluginului MPay Voucher Gateway, asigurati-va ca mediul server indeplineste toate cerintele de mai jos.

## PHP

- **Versiune minima**: PHP 7.4
- **Versiune recomandata**: PHP 8.1 sau mai nou
- PHP trebuie compilat cu suport OpenSSL (extensia `openssl`)

## Extensii PHP obligatorii

| Extensie | Utilizare |
|----------|-----------|
| openssl | Operatii criptografice, citire certificate PKCS#12 |
| soap | Client si server SOAP nativ |
| dom | Manipulare XML pentru WS-Security |
| xmlwriter | Generare XML |
| mbstring | Procesare siruri multi-byte |
| json | Serializare/deserializare date |

## Extensii PHP recomandate

| Extensie | Utilizare |
|----------|-----------|
| xmlsec | Performanta semnatura XML (optional, fallback PHP pur) |
| curl | Transport HTTP alternativ |

## WordPress

- **Versiune minima**: WordPress 5.8
- **Versiune recomandata**: WordPress 6.x
- Cron WordPress activ (sau cron real configurat via crontab)
- WP-CLI disponibil pentru comenzile de diagnosticare (optional)

## WooCommerce

- **Versiune minima**: WooCommerce 6.0
- **Versiune recomandata**: WooCommerce 8.x sau mai nou
- WooCommerce HPOS (High-Performance Order Storage) suportat

## HTTPS

Conexiunea HTTPS este **obligatorie**. MPay nu va comunica cu endpoint-uri HTTP necriptate.

Cerinte SSL/TLS:
- Certificat SSL valid pe domeniul magazinului
- TLS 1.2 minim (recomandat TLS 1.3)
- Endpoint-ul SOAP al magazinului trebuie accesibil public pe HTTPS

**Important:** Certificatul HTTPS si certificatul de sistem pentru semnare SOAP sunt produse DIFERITE. STISC le trateaza separat. HTTPS poate fi Let's Encrypt. Certificatul de semnare SOAP este un certificat X.509 de sistem separat obtinut de la STISC.

## Certificat de sistem (productie)

Pentru productie, aveti nevoie de un certificat de sistem de la STISC (X.509 pentru semnare automata SOAP). Vezi docs/ro/05-certificate.md pentru procedura completa STISC, intrebarile de pus si documentele necesare.

Pentru testare, puteti folosi certificatele furnizate de echipa MPay.

## Permisiuni sistem de fisiere

- Directorul `wp-content/uploads/` trebuie sa fie writeable de PHP
- Directorul de certificate trebuie sa aiba permisiuni restrictive (chmod 600 sau 640)
- PHP trebuie sa poata executa `openssl` CLI ca fallback (optional)

## Firewall si retea

- Serverul trebuie sa accepte conexiuni HTTPS de intrare de la IP-urile MPay
- Portul 443 deschis pentru trafic de intrare
- Nu blocati user-agent-ul MPay in regulile WAF

## Verificare cerinte

Dupa instalare, pluginul ofera un portal de diagnosticare in admin care verifica automat:
- Versiuni PHP, WordPress, WooCommerce
- Extensii PHP disponibile
- Configurare HTTPS
- Accesibilitate endpoint SOAP
- Stare certificate

## Hosting recomandat

Orice hosting care suporta WordPress si WooCommerce cu acces la extensiile PHP listate mai sus. VPS sau hosting dedicat este preferabil pentru control deplin asupra configurarii PHP si firewall.
