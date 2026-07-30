# Server SOAP

Pluginul expune un endpoint SOAP pe care MPay il apeleaza pentru a obtine detalii comanda si a confirma platile.

## Endpoint URL

Endpoint-ul SOAP este disponibil la:
```
https://domeniul-dvs.md/wc-api/mpay-soap/
```

URL-ul exact depinde de configurarea permalink-urilor WordPress. Adresa corecta este afisata in tab-ul SOAP din setarile pluginului.

## Cerinte endpoint

- Accesibil public pe HTTPS
- Nu necesita autentificare HTTP (Basic/Digest)
- Trebuie sa raspunda la cereri POST cu Content-Type `text/xml`
- Nu trebuie blocat de firewall, WAF sau plugin-uri de securitate

## Operatii SOAP

### GetOrderDetails

MPay apeleaza aceasta operatie dupa ce primeste cererea de plata de la client.

**Cerere (input):**
```xml
<GetOrderDetails>
  <OrderKey>wc_order_abc123</OrderKey>
</GetOrderDetails>
```

**Raspuns (output):**
```xml
<GetOrderDetailsResponse>
  <OrderAmount>150.00</OrderAmount>
  <Currency>MDL</Currency>
  <Description>Comanda #1234</Description>
  <Items>
    <Item>
      <Name>Produs exemplu</Name>
      <Quantity>1</Quantity>
      <Price>150.00</Price>
    </Item>
  </Items>
</GetOrderDetailsResponse>
```

Pentru comenzi Voucher Cultural, raspunsul include si:
- IDNP platitor
- Flaguri eligibilitate per produs

### ConfirmOrderPayment

MPay apeleaza aceasta operatie dupa procesarea reusita a platii.

**Cerere (input):**
```xml
<ConfirmOrderPayment>
  <OrderKey>wc_order_abc123</OrderKey>
  <TransactionID>MPay-TX-98765</TransactionID>
  <Amount>150.00</Amount>
  <Status>Success</Status>
  <DateTime>2025-01-15T14:30:00</DateTime>
</ConfirmOrderPayment>
```

**Raspuns (output):**
```xml
<ConfirmOrderPaymentResponse>
  <Result>OK</Result>
</ConfirmOrderPaymentResponse>
```

## Semnatura WS-Security

Toate mesajele SOAP (cereri si raspunsuri) sunt semnate cu WS-Security:

- Cererile de la MPay sunt semnate cu certificatul MPay
- Raspunsurile magazinului sunt semnate cu certificatul magazinului
- Pluginul verifica semnatura cererilor primite inainte de procesare
- Detalii complete in documentul 08-ws-security.md

## Erori SOAP

Pluginul returneaza SOAP Fault in caz de eroare:

| Cod | Descriere |
|-----|-----------|
| InvalidSignature | Semnatura WS-Security invalida |
| OrderNotFound | OrderKey nu corespunde niciunei comenzi |
| AmountMismatch | Suma platita nu corespunde sumei comenzii |
| InternalError | Eroare interna server |
| AlreadyConfirmed | Plata deja confirmata (idempotenta) |

## WSDL

Pluginul nu expune un fisier WSDL public. Comunicarea se bazeaza pe specificatiile furnizate de MPay.

## Depanare endpoint

Verificati ca endpoint-ul functioneaza:

1. Accesati URL-ul endpoint-ului in browser - trebuie sa returneze raspuns (chiar si eroare)
2. In consola de diagnosticare, folositi testul "Ping SOAP endpoint"
3. Verificati ca nu exista reguli .htaccess sau plugin-uri care blocheaza accesul
4. Verificati log-urile server (access log si error log)

## Compatibilitate

- Functioneaza cu mod_php, PHP-FPM, LiteSpeed
- Compatibil cu Cloudflare (asigurati-va ca nu este in modul "Under Attack")
- Compatibil cu reverse proxy (nginx in fata Apache)
