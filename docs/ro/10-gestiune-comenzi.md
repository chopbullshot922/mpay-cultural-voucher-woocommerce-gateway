# Gestiune comenzi

Pluginul se integreaza cu sistemul de comenzi WooCommerce pentru a reflecta statusul platilor MPay.

## Statusuri comanda

### Flux normal de statusuri

```
Pending Payment --> Processing --> Completed
```

| Status WooCommerce | Cand se aplica |
|-------------------|----------------|
| Pending Payment | Comanda creata, asteptare plata |
| Processing | Plata confirmata de MPay |
| Completed | Comanda finalizata (manual sau automat) |
| Failed | Plata esuata sau expirata |
| Cancelled | Comanda anulata de admin sau client |

### Tranzitii automate

Pluginul schimba automat statusul in urmatoarele cazuri:

- **Pending --> Processing**: La primirea `ConfirmOrderPayment` cu status Success
- **Pending --> Failed**: La primirea confirmarii cu status Failed
- **Pending --> Failed**: La expirarea timeout-ului (daca este configurat)

### Tranzitii manuale

Administratorul poate schimba manual statusul din pagina comenzii. Pluginul nu blocheaza tranzitiile manuale.

## Meta box MPay

In pagina de editare a comenzii, pluginul adauga un meta box "MPay Payment Details" cu:

- **Status plata MPay** - Success/Failed/Pending
- **ID tranzactie** - Identificatorul unic MPay
- **Data tranzactie** - Data si ora confirmarii
- **Suma platita** - Suma confirmata de MPay
- **Metoda** - MPay Standard sau Voucher Cultural
- **IDNP** - Afisat doar pentru plati Voucher Cultural

## Inregistrare plata (Payment Note)

La confirmarea platii, pluginul adauga o nota la comanda:

```
Plata MPay confirmata. Tranzactie: MPay-TX-98765. Suma: 150.00 MDL.
```

Nota este vizibila in sectiunea "Order notes" si include timestamp-ul.

## Meta-date comanda

Pluginul salveaza urmatoarele meta-date pe comanda:

| Meta key | Continut |
|----------|----------|
| _mpay_transaction_id | ID tranzactie MPay |
| _mpay_payment_status | Status plata |
| _mpay_payment_date | Data confirmare |
| _mpay_payment_amount | Suma platita |
| _mpay_order_key | OrderKey trimis la MPay |
| _mpay_idnp | IDNP platitor (doar Voucher Cultural) |

## Cautare comenzi

Puteti cauta comenzi dupa:
- ID tranzactie MPay (in campul de cautare comenzi)
- OrderKey
- IDNP (pentru comenzi Voucher Cultural)

## WooCommerce HPOS

Pluginul este compatibil cu High-Performance Order Storage (HPOS). Meta-datele sunt stocate corect indiferent daca folositi tabelele custom HPOS sau post meta traditional.

## Export comenzi

Meta-datele MPay sunt incluse in:
- Export CSV WooCommerce standard
- WooCommerce REST API
- Rapoarte WooCommerce

## Actiuni admin

Din meta box-ul MPay puteti:
- Vizualiza detalii tranzactie complete
- Copia ID-ul tranzactiei
- Accesa log-ul de comunicare SOAP pentru comanda respectiva

## Notificari email

Pluginul nu modifica template-urile email WooCommerce. ID-ul tranzactiei MPay este inclus automat in email-ul "Order Completed" prin hook-urile standard WooCommerce.
