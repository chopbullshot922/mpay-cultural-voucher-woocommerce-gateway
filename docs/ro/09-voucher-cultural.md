# Voucher Cultural

Pluginul suporta plata prin programul Voucher Cultural din Republica Moldova, cu verificare eligibilitate produse si identificare platitor prin IDNP.

## Ce este Voucher Cultural

Programul Voucher Cultural permite cetatenilor sa achizitioneze produse si servicii culturale folosind vouchere alocate de stat. Pluginul integreaza aceasta functionalitate direct in WooCommerce.

## Activare

1. Navigati la **WooCommerce > Settings > Payments > MPay > Tab Voucher Cultural**
2. Activati toggle-ul "Voucher Cultural"
3. Configurati categoriile eligibile
4. Activati campul IDNP la checkout
5. Salvati setarile

## Eligibilitate produse

Nu toate produsele pot fi achizitionate cu Voucher Cultural. Pluginul verifica eligibilitatea pe baza:

### Categorii eligibile

Definiti categoriile WooCommerce ale caror produse sunt eligibile:
- Carti
- Muzica
- Arte vizuale
- Spectacole
- Alte categorii culturale definite de program

Configurare: selectati categoriile din lista din tab-ul Voucher Cultural.

### Verificare la checkout

Cand clientul selecteaza plata cu Voucher Cultural:
- Pluginul verifica fiecare produs din cos
- Produsele din categorii neeligibile sunt semnalate
- Comanda nu poate fi plasata daca contine produse neeligibile (in modul strict)

### Plati partiale

Daca cosul contine atat produse eligibile cat si neeligibile:
- Pluginul poate separa suma eligibila de cea neeligibila
- Suma eligibila se plateste cu Voucher Cultural
- Suma neeligibila necesita alta metoda de plata
- Comportamentul depinde de configurarea magazinului

## IDNP - Identificare platitor

IDNP (Numarul de Identificare de Stat al Persoanei) este obligatoriu pentru plata cu Voucher Cultural.

### Camp IDNP la checkout

Cand Voucher Cultural este activ, la checkout apare un camp suplimentar:
- Label: "IDNP"
- Validare: 13 cifre
- Obligatoriu pentru metoda de plata Voucher Cultural
- Nu apare pentru alte metode de plata

### Transmitere IDNP

IDNP-ul este inclus in raspunsul GetOrderDetails catre MPay:
- MPay il foloseste pentru verificarea dreptului la voucher
- IDNP-ul este stocat in meta-datele comenzii
- Nu este afisat public in email-urile comenzii

## Flux plata Voucher Cultural

1. Clientul adauga produse culturale in cos
2. La checkout, selecteaza "Plata cu Voucher Cultural"
3. Introduce IDNP-ul
4. Pluginul verifica eligibilitatea produselor
5. Comanda se plaseaza, redirect catre MPay
6. MPay verifica IDNP si sold voucher
7. Plata se proceseaza
8. Confirmarea revine la magazin

## Restrictii

- Un IDNP poate fi folosit o singura data per sesiune de plata
- Soldul voucher este verificat de MPay (nu de plugin)
- Produsele digitale pot avea reguli diferite de eligibilitate
- Pluginul nu stocheaza soldul voucherului clientului

## Depanare

| Problema | Cauza | Solutie |
|----------|-------|---------|
| "Produse neeligibile in cos" | Categoria produsului nu e in lista | Adaugati categoria in setari |
| "IDNP invalid" | Format gresit | Verificati ca are 13 cifre |
| "Voucher insufficient" | Sold insuficient la MPay | Clientul trebuie sa verifice soldul |
| Camp IDNP nu apare | Voucher Cultural dezactivat | Activati din setari |
