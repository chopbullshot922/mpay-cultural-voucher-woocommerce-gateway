# Certificates

## Three distinct certificates

The integration uses three certificates that are NOT interchangeable:

1. **HTTPS/TLS certificate** - protects the network connection
2. **Merchant system certificate** - signs SOAP responses (WS-Security)
3. **MPay public certificate** - verifies incoming SOAP requests from MPay

## 1. HTTPS/TLS website certificate

Encrypts HTTP traffic, protects the connection between MPay and endpoint, confirms domain identity.

Can be:
- Let's Encrypt
- Hosting-provided
- Commercial TLS/SSL
- Cloudflare-managed (with correct origin configuration)

This is NOT the certificate used for WS-Security XML signing. STISC treats SSL certificates and system certificates as different products.

Practical conclusion:
- HTTPS can be Let's Encrypt
- SOAP signing needs the system certificate separately
- Do NOT upload your Let's Encrypt certificate into the provider private key field

## 2. Merchant system certificate (X.509)

Used by the plugin for:
- Signing SOAP responses
- Cryptographic merchant identification
- Message integrity protection
- WS-Security identity inclusion
- Allowing MPay to verify that responses come from the registered provider

Format:
- `.pfx` or `.p12` for the private key and associated certificate
- `.cer`, `.crt`, or `.pem` for the public certificate
- A password for opening the PKCS#12 package

The PFX package contains:
- Private key
- Public certificate of the merchant
- Possibly intermediate certificates

**The private key is NEVER transmitted to MPay.**

Only the public certificate is sent to MPay, without the private key and without the PFX password.

## 3. MPay public certificate

Used by the plugin to verify incoming messages from MPay.

Role:
- Verifies GetOrderDetails signature
- Verifies ConfirmOrderPayment signature
- Confirms messages were signed by MPay infrastructure
- Allows rejecting forged or modified messages

The MPay public certificate:
- Does NOT contain the MPay private key
- Cannot be used to sign merchant responses
- May differ between test and production
- Must be replaced if MPay rotates its certificate

## What to obtain from STISC

For production, request a certificate intended for an information system, usable for automatic XML/SOAP signing.

The request must clearly state:

> X.509 digital system certificate for automatic SOAP message signing via WS-Security and XML-DSig, in server-to-server mode.

Do NOT request vaguely:
- "an SSL"
- "a website certificate"
- "a mobile signature"
- "a personal certificate"
- "a token for manual document signing"

Clarify that:
- Signing is automatic by the application
- No human intervention is possible per SOAP response
- The key must be usable by the server
- The WordPress/PHP application must be able to access the key
- Desired format is PFX/PKCS#12 or a technically compatible equivalent

## STISC procedure

### Step 1: Legal entity

The certificate must be requested by the entity that:
- Provides the service
- Receives payments
- Operates the store
- Will be registered with MPay

Prepare:
- Exact legal name
- IDNO
- Legal representative data
- Authorized person data (if not submitted by the director)
- Information system name
- Domain and endpoint
- Technical contact person

### Step 2: Confirm the product

Before ordering, get written confirmation that the product:
- Is a system certificate
- Can sign XML
- Can be used for WS-Security
- Allows server-to-server signing
- Can be used automatically by an application
- Can be delivered as PFX/PKCS#12 or equivalent

There is ambiguity on the STISC website: the products page describes the system certificate in a way that is not perfectly aligned with the legal entity form and the information systems section. Therefore, the exact type and delivery form must be confirmed before payment or CSR generation.

### Step 3: Online application

The form requires:
- IDNO of the legal entity
- System certificate data
- Subsequent steps as required

Verify the current form version at the time of application.

### Step 4: Documents

General structure for a legal entity requesting a certificate for an information system:
- Application with domain list
- Public key certification request for each domain
- Power of attorney (if submitted by someone other than the director)
- ID copy of the director or authorized person

May also be required:
- Contract
- Legal entity documents
- Payment proof
- Additional representation documents
- CSR
- Domain ownership or usage confirmation

The final list must be confirmed with STISC for the specific request.

## CN (Common Name)

CN is one of the main fields in the X.509 certificate and identifies the system for which it is issued.

Correct process:
1. Determine the system name
2. Ask STISC for the exact accepted format for NumeCN
3. Ask MPay if there is a naming convention required for registration
4. Do NOT generate the final CSR before CN confirmation
5. Use exactly the approved CN
6. After issuance, extract the CN and communicate it to MPay if requested

Do NOT invent extensions without emitter confirmation.

## CSR (Certificate Signing Request)

Contains: public key, CN, organization name, organizational unit, country, locality.

Does NOT contain the private key.

### Variant 1: STISC generates the key and package

- Follow their procedure
- Do not generate a second key
- Request PFX/P12
- Request the public certificate separately
- Request the certification chain
- Request secure password delivery procedure
- Verify the application can use the package without manual intervention

### Variant 2: Merchant generates the key

Use ONLY if STISC confirms that the applicant must provide a CSR.

Generate key:

```
openssl genpkey \
  -algorithm RSA \
  -aes-256-cbc \
  -out system-private-key.pem \
  -pkeyopt rsa_keygen_bits:2048
```

Generate CSR:

```
openssl req \
  -new \
  -key system-private-key.pem \
  -out system-certificate-request.csr \
  -subj "/C=MD/O=YOUR_LEGAL_ORGANIZATION/OU=YOUR_SYSTEM/CN=APPROVED_CN"
```

Verify CSR:

```
openssl req \
  -in system-certificate-request.csr \
  -noout \
  -text \
  -verify
```

Protect files:

```
chmod 600 system-private-key.pem
chmod 640 system-certificate-request.csr
```

RSA parameters and CSR attributes must be confirmed with STISC before final generation.

## Questions to ask STISC

- What is the exact product for automatic XML/SOAP signing?
- Is the certificate compatible with WS-Security?
- Is the private key exportable in PFX/PKCS#12?
- Can it be used automatically without PIN entry per message?
- What is the exact NumeCN format?
- Are SAN fields required?
- Who generates the private key?
- Is CSR required?
- What algorithm and key size must be used?
- What fields must be included in CSR?
- What documents must be presented?
- What is the cost?
- What is the estimated timeline?
- What is the validity period?
- How is renewal done?
- How is revocation requested?
- What is the certification chain?
- Where are root and intermediate certificates obtained?
- Is there CRL or OCSP?
- Is the same certificate used in test and production?
- How is the password delivered?
- Can it be used on Linux, Docker, and PHP/OpenSSL?
- Is PFX storage on server acceptable?
- What must be done if the key is compromised?

## ServerSign note

STISC also offers ServerSign, an automated signing/verification service for XML and PDF via PKI infrastructure.

The plugin is built for:
- Local certificate
- PFX/P12 or PEM
- Direct private key access
- Signing via OpenSSL

ServerSign can be used ONLY if:
- MPay confirms the architecture is accepted
- The resulting signature matches exactly the required WS-Security
- The plugin is adapted for the ServerSign API
- Latency and availability are acceptable
- The service can sign the exact required XML elements

Do NOT present ServerSign as a direct replacement without validation.

## Testing the PFX

The plugin has a "Test private key" button that verifies:
- File existence
- Password correctness
- PKCS#12 structure
- Key extraction capability
- PHP OpenSSL availability
- CLI OpenSSL availability

CLI verification:

```
openssl pkcs12 -info -in provider-certificate.pfx -noout
```

Extract public certificate:

```
openssl pkcs12 \
  -in provider-certificate.pfx \
  -clcerts \
  -nokeys \
  -out provider-public-cert.pem
```

Inspect certificate:

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

Convert DER to PEM:

```
openssl x509 \
  -inform DER \
  -in provider.cer \
  -out provider.pem
```

## Verifying key-certificate pair

```
openssl x509 -in provider-public-cert.pem -pubkey -noout | openssl sha256
openssl pkey -in provider-private-key.pem -pubout | openssl sha256
```

Hashes must be identical. If they are not:
- The public certificate does not belong to the key
- The wrong PFX was combined
- MPay will not be able to validate responses
- The correct package must be obtained or rebuilt

## Contact

For certificate-related implementation support: incontact@terabitlab.com
