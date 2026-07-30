# Certificate

## Trei certificate distincte

Integrarea foloseste trei certificate care NU sunt interschimbabile:

1. **Certificatul HTTPS/TLS** - protejeaza conexiunea de retea
2. **Certificatul de sistem al comerciantului** - semneaza raspunsurile SOAP (WS-Security)
3. **Certificatul public MPay** - verifica cererile SOAP primite de la MPay

## 1. Certificatul HTTPS/TLS al website-ului

Cripteaza traficul HTTP, protejeaza conexiunea dintre MPay si endpoint, confirma identitatea domeniului.

Poate fi:
- Let's Encrypt
- Furnizat de hosting
- Commercial TLS/SSL
- Gestionat de Cloudflare (cu configurarea corecta a originului)

NU este certificatul folosit pentru semnarea XML WS-Security. STISC trateaza certificatele SSL si certificatul de sistem drept produse diferite.

Concluzie practica:
- HTTPS poate fi asigurat de Let's Encrypt
- Semnarea SOAP necesita separat certificatul de sistem
- NU incarca certificatul Let's Encrypt in campul cheii private a prestatorului

## 2. Certificatul de sistem al comerciantului (X.509)

Folosit de plugin pentru:
- Semnarea raspunsurilor SOAP
- Identificarea criptografica a comerciantului
- Protejarea integritatii mesajului
- Includerea identitatii in WS-Security
- Permite MPay sa verifice ca raspunsul provine de la prestatorul inregistrat

Format:
- `.pfx` sau `.p12` pentru cheia privata si certificatul asociat
- `.cer`, `.crt` sau `.pem` pentru certificatul public
- O parola pentru deschiderea pachetului PKCS#12

Pachetul PFX contine:
- Cheia privata
- Certificatul public al comerciantului
- Eventual certificate intermediare

**Cheia privata nu se transmite NICIODATA catre MPay.**

Catre MPay se transmite numai certificatul public, fara cheia privata si fara parola PFX.

## 3. Certificatul public MPay

Folosit de plugin pentru a verifica mesajele primite de la MPay.

Rol:
- Verifica semnatura GetOrderDetails
- Verifica semnatura ConfirmOrderPayment
- Confirma ca mesajul a fost semnat de infrastructura MPay
- Permite respingerea mesajelor falsificate sau modificate

Certificatul public MPay:
- NU contine cheia privata MPay
- Nu poate fi folosit pentru semnarea raspunsurilor comerciantului
- Poate fi diferit intre test si productie
- Trebuie inlocuit daca MPay efectueaza rotatia certificatului

## Ce trebuie obtinut de la STISC

Pentru productie, solicitati un certificat destinat unui sistem informational, utilizabil pentru semnarea automata XML/SOAP.

Solicitarea trebuie sa precizeze clar:

> Certificat digital X.509 de sistem pentru semnarea automata a mesajelor SOAP prin WS-Security si XML-DSig, in regim server-to-server.

NU cereti vag:
- "un SSL"
- "un certificat de website"
- "o semnatura mobila"
- "un certificat personal"
- "un token pentru semnarea manuala a documentelor"

Clarificati ca:
- Semnarea se face automat de aplicatie
- Nu poate exista interventie umana la fiecare raspuns SOAP
- Cheia trebuie sa poata fi utilizata de server
- Aplicatia WordPress/PHP trebuie sa poata accesa cheia
- Formatul dorit este PFX/PKCS#12 sau un echivalent compatibil tehnic

## Procedura STISC

### Pasul 1: Persoana juridica solicitanta

Certificatul trebuie solicitat de entitatea juridica ce:
- Presteaza serviciul
- Primeste platile
- Opereaza magazinul
- Va fi inregistrata la MPay

Pregatiti:
- Denumirea juridica exacta
- IDNO
- Datele reprezentantului legal
- Datele persoanei imputernicite (daca cererea nu este depusa de conducator)
- Denumirea sistemului informational
- Domeniul si endpoint-ul
- Persoana tehnica responsabila

### Pasul 2: Confirmarea produsului

Inainte de comanda, obtineti confirmare scrisa ca produsul:
- Este certificat de sistem
- Poate semna XML
- Poate fi folosit pentru WS-Security
- Permite semnare server-to-server
- Poate fi folosit automat de o aplicatie
- Poate fi livrat ca PFX/PKCS#12 sau echivalent

Exista o ambiguitate pe site-ul STISC: pagina de produse descrie certificatul de sistem intr-un mod care nu este perfect aliniat cu formularul pentru persoane juridice si cu sectiunea dedicata certificatelor pentru sisteme informationale. De aceea, tipul exact si forma livrarii trebuie confirmate inainte de plata sau generarea CSR-ului.

### Pasul 3: Cererea online

In formular se introduce:
- IDNO-ul persoanei juridice
- Datele certificatului de sistem
- Informatiile solicitate in etapele ulterioare

Verificati versiunea curenta a formularului la momentul aplicarii.

### Pasul 4: Documentele

Structura generala pentru persoana juridica ce solicita certificat pentru un sistem informational:
- Cererea cu lista domeniilor
- Cererea de certificare a cheii publice pentru fiecare domeniu
- Actul de imputernicire (daca depune alta persoana decat conducatorul)
- Copia actului de identitate al conducatorului sau persoanei imputernicite

Pot fi solicitate si:
- Contractul
- Documentele persoanei juridice
- Dovada platii
- Documente suplimentare privind reprezentarea
- CSR
- Confirmarea detinerii sau utilizarii domeniului

Lista finala trebuie confirmata cu STISC pentru solicitarea concreta.

## CN (Common Name)

CN este unul dintre campurile principale din certificatul X.509 si identifica sistemul pentru care este emis.

Proces corect:
1. Stabiliti denumirea sistemului
2. Cereti STISC formatul exact acceptat pentru NumeCN
3. Cereti MPay sa confirme daca exista o conventie necesara pentru inregistrare
4. NU generati CSR-ul definitiv inainte de confirmarea CN-ului
5. Folositi exact CN-ul aprobat
6. Dupa emitere, extrageti CN-ul din certificat si comunicati-l MPay daca este solicitat

NU inventati extensii fara confirmarea emitentului.

## CSR (Certificate Signing Request)

Contine: cheia publica, CN, denumirea organizatiei, unitatea organizationala, tara, localitatea.

NU contine cheia privata.

### Varianta 1: STISC genereaza cheia si pachetul

- Urmati procedura STISC
- Nu generati o a doua cheie
- Solicitati PFX/P12
- Solicitati certificatul public separat
- Solicitati lantul de certificare
- Solicitati procedura sigura pentru primirea parolei
- Verificati ca aplicatia poate folosi pachetul fara interventie manuala

### Varianta 2: Comerciantul genereaza cheia

Se foloseste NUMAI daca STISC confirma ca solicitantul trebuie sa furnizeze CSR.

Generare cheie:

```
openssl genpkey \
  -algorithm RSA \
  -aes-256-cbc \
  -out system-private-key.pem \
  -pkeyopt rsa_keygen_bits:2048
```

Generare CSR:

```
openssl req \
  -new \
  -key system-private-key.pem \
  -out system-certificate-request.csr \
  -subj "/C=MD/O=YOUR_LEGAL_ORGANIZATION/OU=YOUR_SYSTEM/CN=APPROVED_CN"
```

Verificare CSR:

```
openssl req \
  -in system-certificate-request.csr \
  -noout \
  -text \
  -verify
```

Protejare fisiere:

```
chmod 600 system-private-key.pem
chmod 640 system-certificate-request.csr
```

Parametrii RSA si atributele CSR trebuie confirmati cu STISC inainte de generarea finala.

## Intrebarile pentru STISC

- Care este produsul exact pentru semnare automata XML/SOAP?
- Este certificatul compatibil cu WS-Security?
- Este cheia privata exportabila in PFX/PKCS#12?
- Poate fi folosita automat fara PIN la fiecare mesaj?
- Care este formatul exact al NumeCN?
- Sunt necesare campuri SAN?
- Cine genereaza cheia privata?
- Este necesar CSR?
- Ce algoritm si dimensiune de cheie?
- Ce campuri trebuie incluse in CSR?
- Ce documente trebuie prezentate?
- Care este costul?
- Care este termenul estimativ?
- Care este perioada de valabilitate?
- Cum se efectueaza reinnoirea?
- Cum se solicita revocarea?
- Care este lantul de certificare?
- De unde se obtin certificatele root si intermediate?
- Exista CRL sau OCSP?
- Se foloseste acelasi certificat in test si productie?
- Cum este livrata parola?
- Poate fi folosit pe Linux, Docker si PHP/OpenSSL?
- Este acceptabila stocarea PFX-ului pe server?
- Ce trebuie facut in cazul compromiterii cheii?

## Nota ServerSign

STISC ofera si ServerSign, un serviciu automatizat de semnare/verificare pentru XML si PDF prin infrastructura PKI.

Pluginul este construit pentru:
- Certificat local
- PFX/P12 sau PEM
- Acces direct la cheia privata
- Semnare prin OpenSSL

ServerSign poate fi folosit NUMAI daca:
- MPay confirma ca arhitectura este acceptata
- Semnatura rezultata corespunde exact WS-Security cerut
- Pluginul este adaptat pentru apelarea serviciului ServerSign
- Latenta si disponibilitatea sunt acceptabile
- Serviciul poate semna elementele XML exacte cerute

NU prezentati ServerSign drept inlocuitor direct fara validare.

## Testarea PFX

Pluginul are butonul "Test cheie privata" care verifica:
- Existenta fisierului
- Parola
- Structura PKCS#12
- Posibilitatea extractiei cheii
- Disponibilitatea OpenSSL PHP
- Disponibilitatea OpenSSL CLI

Verificare CLI:

```
openssl pkcs12 -info -in provider-certificate.pfx -noout
```

Extragere certificat public:

```
openssl pkcs12 \
  -in provider-certificate.pfx \
  -clcerts \
  -nokeys \
  -out provider-public-cert.pem
```

Inspectare certificat:

```
openssl x509 \
  -in provider-public-cert.pem \
  -noout \
  -subject \
  -issuer \
  -serial \
  -dates \
  -fingerprint \
  -sha256
```

Conversie DER in PEM:

```
openssl x509 \
  -inform DER \
  -in provider.cer \
  -out provider.pem
```

## Verificarea perechii certificat-cheie

```
openssl x509 -in provider-public-cert.pem -pubkey -noout | openssl sha256
openssl pkey -in provider-private-key.pem -pubout | openssl sha256
```

Hash-urile trebuie sa fie identice. Daca nu sunt:
- Certificatul public nu apartine cheii
- A fost combinat PFX-ul gresit
- MPay nu va putea valida raspunsurile
- Trebuie obtinut sau reconstruit pachetul corect

## Contact

Pentru suport implementare certificate: incontact@terabitlab.com
