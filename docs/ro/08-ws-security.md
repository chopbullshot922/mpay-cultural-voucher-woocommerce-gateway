# WS-Security

## Prezentare

Pluginul implementeaza WS-Security pentru autentificarea si integritatea mesajelor SOAP. Fiecare mesaj SOAP schimbat intre magazin si MPay este semnat digital cu certificate X.509.

## Fluxul criptografic - cerere primita

1. MPay construieste mesajul SOAP
2. Adauga Timestamp
3. Calculeaza digesturile elementelor semnate
4. Construieste SignedInfo
5. Semneaza cu cheia privata MPay
6. Trimite XML-ul catre endpoint-ul comerciantului
7. Pluginul extrage certificatul sau referinta certificatului
8. Pluginul verifica semnatura folosind certificatul public MPay
9. Pluginul verifica Timestamp
10. Pluginul verifica ca elementele semnate sunt cele asteptate
11. Numai dupa validare proceseaza operatia

## Fluxul criptografic - raspuns trimis

1. Pluginul construieste raspunsul SOAP complet
2. Adauga Timestamp
3. Calculeaza digesturile
4. Construieste SignedInfo
5. Semneaza folosind cheia privata a comerciantului
6. Include informatia despre certificatul public al comerciantului
7. Trimite XML-ul final catre MPay
8. MPay verifica semnatura folosind certificatul public inregistrat anterior

## Algoritmi suportati

| Component | Algoritmi |
|-----------|-----------|
| Semnatura | rsa-sha1, rsa-sha256 |
| Digest | sha1, sha256 |
| Canonicalizare | Exclusive XML Canonicalization (exc-c14n) |

## Compatibilitate criptografica

Integrarea foloseste:
- rsa-sha1 pentru SignatureMethod
- SHA-1 pentru digest
- Canonicalizare XML exclusiva
- Semnarea elementelor asteptate de contract

Aceasta este o cerinta de compatibilitate pentru integrarea MPay, NU o recomandare moderna generala.

**NU schimba unilateral SHA-1 cu SHA-256.** Orice schimbare de algoritm trebuie coordonata cu MPay. Problema intalnita in practica a fost exact aceasta: semnatura era calculata intr-un mod, atributul Algorithm declara alt algoritm, MPay calcula alt rezultat, mesajul era respins ca invalid signature.

## Lectia critica despre XML-DSig

Semnatura XML este sensibila la octeti, namespace-uri si canonicalizare.

Probleme reale intalnite:
- Newline suplimentar
- Caracter suplimentar la final
- UTF-8 cu BOM
- Pretty-print dupa semnare
- Canonicalizare completa dupa semnare
- Cloudflare cache sau transformari
- Diferenta intre transformarile declarate si cele folosite
- InclusiveNamespaces declarat in SignedInfo, dar neinclus in calculul digestului
- Ordine gresita a elementelor XML
- Atributul algoritmului diferit de algoritmul real

### Procedura corecta de semnare

1. Construieste XML-ul complet
2. Stabileste namespace-urile finale
3. Adauga ID-urile finale
4. Canonicalizeaza exact elementele semnate
5. Calculeaza digestul
6. Construieste SignedInfo
7. Semneaza
8. **NU modifica absolut nimic dupa semnare**
9. Fara newline
10. Fara pretty-print
11. Nu reconstrui documentul
12. Trimite exact octetii semnati

## Configurare certificate

Doua certificate sunt implicate:
- **Certificatul comerciantului** (din .pfx/.p12) - semneaza raspunsurile trimise
- **Certificatul MPay** - verifica semnatura cererilor primite

Ambele se configureaza din tab-ul Securitate al setarilor admin.

## Verificare Timestamp

Elementul Timestamp contine valorile Created si Expires. Pluginul verifica:
- Mesajul a fost creat intr-o fereastra de timp acceptabila
- Mesajul nu a expirat
- Ora serverului este sincronizata (NTP)

Diferentele de timp intre servere cauzeaza respingerea Timestamp.

## Cloudflare si proxy

Pentru endpoint-urile /mpay/soap, /mpay/debug, /mpay/diagnostics, /mpay/playbook:

Dezactivati:
- Cache
- Minify
- Rocket Loader
- Transformari HTML/XML
- Rescrieri ale corpului
- Normalizari
- Redirect-uri neasteptate
- Injectarea de continut
- Orice functie care poate schimba body-ul

Orice modificare la nivel de octet dupa semnare invalideaza semnatura.

## Securitate

- Cheile private nu parasesc niciodata serverul
- Verificarea semnaturii se face inainte de orice logica de business
- Verificarile esuate sunt logate cu detalii
- Pluginul nu accepta cereri SOAP nesemnate cand WS-Security enforcement este ON
- Certificate pinning este aplicat contra certificatului MPay configurat
