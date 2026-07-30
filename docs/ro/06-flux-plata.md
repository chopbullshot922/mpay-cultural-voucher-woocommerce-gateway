# Fluxul de plata

Documentul cel mai important - descrie fluxul complet de la checkout pana la confirmarea platii. In acest sistem, MPay apeleaza magazinul (nu invers).

## Diagrama flux

```
Client          Magazin (WooCommerce)         MPay
  |                    |                        |
  |-- Checkout ------->|                        |
  |                    |-- HTTP POST form ------>|
  |                    |   (ServiceID,          |
  |                    |    OrderKey,           |
  |                    |    ReturnUrl)          |
  |<----------------------------------------------|
  |   (Client pe pagina MPay)                   |
  |                    |                        |
  |                    |<-- SOAP GetOrderDetails-|
  |                    |-- Raspuns detalii ----->|
  |                    |                        |
  |   (Client plateste pe MPay)                 |
  |                    |                        |
  |                    |<-- SOAP ConfirmOrder ---|
  |                    |-- Raspuns confirmare -->|
  |                    |                        |
  |<-- Redirect ReturnUrl                       |
  |   (Pagina Thank You)                       |
```

## Pas 1: Checkout

Clientul selecteaza metoda de plata "MPay" si plaseaza comanda. WooCommerce creeaza comanda cu status "Pending payment".

## Pas 2: Redirect catre MPay (HTTP POST)

Pluginul genereaza un formular HTML care se trimite automat (auto-submit) catre URL-ul MPay. Formularul contine:

| Camp | Descriere |
|------|-----------|
| ServiceID | Identificatorul serviciului magazinului |
| OrderKey | Cheia unica a comenzii WooCommerce |
| ReturnUrl | URL-ul de intoarcere dupa plata |

Redirect-ul este un HTTP POST (formular), nu un redirect HTTP 302.

## Pas 3: MPay apeleaza GetOrderDetails

Dupa ce primeste cererea, MPay apeleaza endpoint-ul SOAP al magazinului cu operatia `GetOrderDetails`. Cererea contine OrderKey.

Magazinul raspunde cu:
- Suma totala comanda
- Moneda
- Descriere comanda
- Informatii produse (pentru Voucher Cultural: detalii eligibilitate)
- IDNP platitor (daca este Voucher Cultural)

Mesajul SOAP este semnat cu WS-Security (certificatul magazinului).

## Pas 4: Clientul plateste

Clientul introduce datele de plata pe pagina MPay. Aceasta etapa se desfasoara integral pe infrastructura MPay.

## Pas 5: MPay apeleaza ConfirmOrderPayment

Dupa plata reusita, MPay trimite o cerere SOAP `ConfirmOrderPayment` catre magazin cu:
- OrderKey
- ID tranzactie MPay
- Status plata
- Suma platita
- Data/ora tranzactie

## Pas 6: Procesare confirmare

Pluginul proceseaza confirmarea:

1. Verifica semnatura WS-Security a mesajului primit
2. Identifica comanda dupa OrderKey
3. Verifica ca suma platita corespunde sumei comenzii
4. Aplica lock transient-based (idempotenta) pentru a preveni procesarea dubla
5. Actualizeaza statusul comenzii la "Processing" sau "Completed"
6. Salveaza meta-date tranzactie (ID, data, status)
7. Raspunde MPay cu confirmare succes

## Pas 7: Return client

Clientul este redirectionat de MPay catre ReturnUrl (pagina Thank You din WooCommerce).

## Idempotenta

Confirmarea este idempotenta - daca MPay trimite aceeasi confirmare de mai multe ori (retry), comanda nu se proceseaza repetat. Mecanismul foloseste transient WordPress cu durata scurta ca lock.

## Erori posibile in flux

| Etapa | Eroare | Comportament |
|-------|--------|-------------|
| Pas 3 | Endpoint SOAP inaccesibil | MPay nu poate continua, clientul vede eroare |
| Pas 3 | Semnatura invalida | MPay respinge raspunsul |
| Pas 5 | Semnatura MPay invalida | Pluginul respinge confirmarea |
| Pas 5 | OrderKey inexistent | Raspuns eroare catre MPay |
| Pas 5 | Suma nu corespunde | Raspuns eroare, comanda ramane pending |

## Timeout-uri

- GetOrderDetails: MPay asteapta raspuns maxim 30 secunde
- ConfirmOrderPayment: MPay retrimite daca nu primeste raspuns in 30 secunde
- Lock transient: expira dupa 60 secunde

## Note tehnice

- Toate comunicarile SOAP sunt pe HTTPS
- Endpoint-ul SOAP al magazinului este public (fara autentificare HTTP) dar protejat prin WS-Security
- Formatul XML respecta specificatiile WSDL ale MPay

## Ce trebuie obtinut de la MPay

### Pentru test

- ServiceID de test
- Acces la pagina de test
- Certificatul public MPay de test
- Confirmarea endpoint-ului SOAP al comerciantului
- IP-uri sau reguli de whitelist (daca e cazul)
- Acces la API-ul de facturi (daca este folosit)
- Confirmarea algoritmilor WS-Security
- Confirmarea regulilor Voucher Cultural
- Confirmarea testelor de acceptanta

### Pentru productie

- ServiceID de productie
- Inregistrarea endpoint-ului de productie
- Inregistrarea certificatului public al comerciantului
- Certificatul public MPay de productie
- Configurarea IP-ului (daca este necesara)
- Accesul API pe portul specific (daca este folosit)
- Confirmarea activarii serviciului
- Test final controlat

### Ce poate fi transmis MPay

- Certificatul public .cer sau .pem
- CN
- Fingerprint (daca este cerut)
- ServiceID
- Endpoint SOAP
- ReturnUrl
- IP public (daca este necesar)
- Contact tehnic

## Checklist productie

### STISC

- [ ] Certificat emis
- [ ] PFX deschis cu succes
- [ ] Parola confirmata
- [ ] Certificat public extras
- [ ] CN corect
- [ ] Issuer corect
- [ ] Valabilitate verificata
- [ ] Lant disponibil
- [ ] Backup securizat
- [ ] Procedura de revocare cunoscuta
- [ ] Data expirarii notata

### MPay

- [ ] ServiceID productie
- [ ] Endpoint productie inregistrat
- [ ] Certificat public inregistrat
- [ ] Fingerprint confirmat
- [ ] Certificat public MPay productie primit
- [ ] Whitelist configurat
- [ ] Acces API (daca e folosit)
- [ ] Reguli Voucher Cultural confirmate
- [ ] Test final trecut

### Server

- [ ] HTTPS valid
- [ ] DNS corect
- [ ] Endpoint accesibil extern
- [ ] Fara redirect neasteptat
- [ ] Cache dezactivat pe /mpay/*
- [ ] PHP SOAP instalat
- [ ] PHP OpenSSL instalat
- [ ] DOM/XML instalat
- [ ] cURL instalat
- [ ] Ora sincronizata (NTP)
- [ ] Permisiuni corecte
- [ ] Certificate protejate
- [ ] Loguri active
- [ ] Cheie debug secreta

### Plugin

- [ ] Mod PROD activat
- [ ] ServiceID productie configurat
- [ ] Date bancare oficiale
- [ ] BeneficiaryName oficial (denumirea juridica exacta)
- [ ] Certificat public comerciant incarcat
- [ ] PFX/cheie privata incarcata
- [ ] Parola verificata (butonul de test)
- [ ] Certificat public MPay incarcat
- [ ] WS-Security activ
- [ ] HTTP test dezactivat
- [ ] Monitorizare certificate activa
- [ ] Endpoint verificat accesibil
- [ ] Comanda controlata plasata
- [ ] Confirmare verificata
- [ ] Idempotency verificata (test apel dublu)
- [ ] ReturnUrl verificat
