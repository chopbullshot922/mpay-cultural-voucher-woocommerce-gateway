# WS-Security

## Overview

The plugin implements WS-Security for SOAP message authentication and integrity. Every SOAP message exchanged between your store and MPay is digitally signed using X.509 certificates.

## Cryptographic flow - incoming request

1. MPay builds the SOAP message
2. Adds Timestamp
3. Calculates digests of signed elements
4. Builds SignedInfo
5. Signs with MPay private key
6. Sends XML to the merchant endpoint
7. Plugin extracts the certificate or reference
8. Plugin verifies signature using MPay public certificate
9. Plugin verifies Timestamp
10. Plugin verifies that signed elements are the expected ones
11. Only after successful validation does the plugin process the operation

## Cryptographic flow - outgoing response

1. Plugin builds the complete SOAP response
2. Adds Timestamp
3. Calculates digests
4. Builds SignedInfo
5. Signs using the merchant private key
6. Includes merchant public certificate information
7. Sends the final XML to MPay
8. MPay verifies using the registered public certificate

## Supported algorithms

| Component | Algorithms |
|-----------|-----------|
| Signature | rsa-sha1, rsa-sha256 |
| Digest | sha1, sha256 |
| Canonicalization | Exclusive XML Canonicalization (exc-c14n) |

## Cryptographic compatibility

The integration uses:
- rsa-sha1 for SignatureMethod
- SHA-1 for digest
- Exclusive XML canonicalization
- Signing of contract-expected elements

This is a compatibility requirement for the MPay integration, not a modern general recommendation.

**Do NOT unilaterally change SHA-1 to SHA-256.** Any algorithm change must be coordinated with MPay. The problem encountered in practice was exactly this: the signature was calculated one way, the Algorithm attribute declared another, MPay calculated a different result, and the message was rejected as invalid signature.

## Critical XML-DSig rules

XML signature is sensitive to bytes, namespaces, and canonicalization.

Problems encountered in real integrations:
- Extra newline character
- Extra character at end of document
- UTF-8 with BOM
- Pretty-print after signing
- Complete document canonicalization after signing
- Cloudflare cache or transformations modifying the body
- Difference between declared transforms and actually used transforms
- InclusiveNamespaces declared in SignedInfo but not included in digest calculation
- Wrong XML element order
- Algorithm attribute different from the actual algorithm used

### Correct signing procedure

1. Build the complete XML
2. Establish final namespaces
3. Add final IDs
4. Canonicalize exactly the signed elements
5. Calculate digest
6. Build SignedInfo
7. Sign
8. **Do NOT modify ANYTHING after signing**
9. No newline added
10. No pretty-print
11. Do not rebuild the document
12. Send the exact signed bytes

## Certificate configuration

Two certificates are involved:
- **Merchant certificate** (from .pfx/.p12) - signs outgoing responses
- **MPay certificate** - verifies incoming request signatures

Both are configured in the Security tab of the admin settings.

## Timestamp verification

The Timestamp element contains Created and Expires values. The plugin verifies:
- The message was created within an acceptable time window
- The message has not expired
- Server time is synchronized (NTP)

Time differences between servers cause Timestamp rejections.

## Security considerations

- Private keys never leave the server
- Signature verification happens before any business logic executes
- Failed verifications are logged with request details
- The plugin does not accept unsigned SOAP requests when WS-Security enforcement is ON
- Certificate pinning is applied against the configured MPay certificate

## Cloudflare and proxy

For endpoints /mpay/soap, /mpay/debug, /mpay/diagnostics, /mpay/playbook:

Disable:
- Cache
- Minify
- Rocket Loader
- HTML/XML transformations
- Body rewrites
- Normalizations
- Unexpected redirects
- Content injection
- Any function that can change the response body

Any byte-level modification after signing invalidates the signature.
