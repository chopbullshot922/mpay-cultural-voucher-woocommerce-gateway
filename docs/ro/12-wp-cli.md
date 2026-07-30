# WP-CLI

Pluginul ofera comenzi WP-CLI pentru administrare si diagnosticare din linia de comanda.

## Cerinte

- WP-CLI instalat si functional
- Acces SSH la server
- Permisiuni suficiente pentru a rula comenzi WordPress

## Comenzi disponibile

### wp mpay cert-status

Afiseaza statusul certificatului digital instalat.

```bash
wp mpay cert-status
```

Output exemplu:
```
Certificate Status: Valid
Subject: CN=Magazin SRL, O=Magazin SRL, C=MD
Issuer: CN=MPay CA, O=MPay, C=MD
Serial: 1A:2B:3C:4D:5E:6F
Valid From: 2025-01-01 00:00:00
Valid To: 2026-01-01 00:00:00
Days Until Expiry: 284
Algorithm: sha256WithRSAEncryption
Fingerprint (SHA-256): AB:CD:EF:...
```

Optiuni:
- `--format=json` - Output in format JSON
- `--quiet` - Doar status (Valid/Expired/Missing)

Folosire in scripturi de monitorizare:
```bash
wp mpay cert-status --quiet
# Exit code 0 = valid, 1 = warning (< 30 zile), 2 = expired/missing
```

### wp mpay event-log

Afiseaza log-ul de evenimente al pluginului.

```bash
wp mpay event-log
```

Optiuni:
- `--limit=N` - Numarul de evenimente (implicit 20)
- `--type=error` - Filtreaza dupa tip (error, warning, info, debug)
- `--since="2025-01-01"` - Evenimente dupa o data anume
- `--format=table|json|csv` - Format output

Exemple:
```bash
# Ultimele 50 erori
wp mpay event-log --limit=50 --type=error

# Evenimente din ultima saptamana in JSON
wp mpay event-log --since="7 days ago" --format=json

# Toate evenimentele de confirmare plata
wp mpay event-log --type=info --limit=100
```

### wp mpay cleanup

Curata date temporare si log-uri vechi ale pluginului.

```bash
wp mpay cleanup
```

Ce curata:
- Transient-uri expirate ale pluginului
- Fisiere log mai vechi de 30 zile
- Cache intern expirat
- Sesiuni de diagnosticare vechi

Optiuni:
- `--dry-run` - Afiseaza ce ar sterge fara sa stearga efectiv
- `--force` - Nu cere confirmare
- `--days=N` - Sterge log-uri mai vechi de N zile (implicit 30)

Exemple:
```bash
# Vedere ce s-ar curata
wp mpay cleanup --dry-run

# Curatare fara confirmare
wp mpay cleanup --force

# Sterge log-uri mai vechi de 7 zile
wp mpay cleanup --days=7 --force
```

## Utilizare in cron

Puteti programa comenzile in crontab:

```cron
# Verificare certificat zilnic la 08:00
0 8 * * * cd /path/to/wordpress && wp mpay cert-status --quiet || mail -s "MPay cert warning" admin@exemplu.md

# Curatare saptamanala (duminica la 03:00)
0 3 * * 0 cd /path/to/wordpress && wp mpay cleanup --force
```

## Integrare cu monitorizare

Exit codes pentru `cert-status`:
- 0 - Certificat valid (mai mult de 30 zile)
- 1 - Warning (mai putin de 30 zile pana la expirare)
- 2 - Eroare (expirat sau lipsa)

Exemplu integrare Nagios/Icinga:
```bash
#!/bin/bash
OUTPUT=$(wp mpay cert-status --quiet 2>&1)
EXIT=$?
echo "$OUTPUT"
exit $EXIT
```

## Multisite

Pe instalari WordPress Multisite, adaugati `--url=site.md` pentru a specifica site-ul:

```bash
wp mpay cert-status --url=magazin.exemplu.md
```

## Depanare WP-CLI

Daca comenzile nu sunt disponibile:
- Verificati ca pluginul este activ: `wp plugin list | grep mpay`
- Verificati versiunea WP-CLI: `wp --version`
- Rulati cu debug: `wp mpay cert-status --debug`
