<?php
namespace MPAY_VG\Soap;
use MPAY_VG\Core\Logger;
if (!defined('ABSPATH')) { exit; }

/**
 * Minimal WS-Security XML-DSig implementation using OpenSSL + DOM C14N.
 * RO: Verifică semnătura MPay și semnează răspunsurile noastre.
 * EN: Verifies MPay signature and signs our SOAP envelopes.
 *
 * Notă: Acceptă rsa-sha1 și rsa-sha256 pentru verificare. Transform: Exclusive C14N.
 */
class WsSecurity {
    private static function logFailure($code, $message, array $context = []) {
        $ctx = array_merge(
            [
                'component' => 'wssecurity.verify',
                'code' => $code,
                'details' => $message,
            ],
            $context
        );
        Logger::log('WS-Security verificare eșuată', $ctx, 'error');
    }

    public static function verify(\DOMDocument $doc) : array {
        $opts = \mpay_vg_get_settings();
        $enforced = !empty($opts['enforce_wssec']);
        $mpay_cert_path = $opts['mpay_public_cert_path'] ?? '';
        $telemetry = [
            'operation' => self::detectOperation($doc),
            'cert_path' => $mpay_cert_path,
            'references' => [],
            'enforced' => $enforced,
        ];
        if (!$enforced) return ['ok'=>true];
        if (!$mpay_cert_path || !file_exists($mpay_cert_path)) {
            self::logFailure('AuthenticationFailed', 'Certificatul public MPay lipsește.', ['path'=>$mpay_cert_path]);
            return self::verificationError($telemetry, 'AuthenticationFailed', 'MPay public certificate missing', ['reason'=>'cert_missing']);
        }
        $cert_raw = @file_get_contents($mpay_cert_path);
        if ($cert_raw === false) {
            self::logFailure('AuthenticationFailed', 'Nu pot citi certificatul public MPay.', ['path'=>$mpay_cert_path]);
            return self::verificationError($telemetry, 'AuthenticationFailed', 'Cannot read MPay public certificate', ['reason'=>'cert_unreadable']);
        }
        $cert_pem = \mpay_vg_normalize_certificate($cert_raw);
        $telemetry['fingerprint_sha1'] = \mpay_vg_certificate_fingerprint($cert_pem, 'sha1');
        $telemetry['fingerprint_sha256'] = \mpay_vg_certificate_fingerprint($cert_pem, 'sha256');
        static $mpayCertLogged = false;
        if (!$mpayCertLogged) {
            $logCtx = [
                'component' => 'wssecurity.verify',
                'code' => 'mpay_cert_loaded',
                'path' => $mpay_cert_path,
            ];
            $fpSha1 = \mpay_vg_certificate_fingerprint($cert_pem, 'sha1');
            $fpSha256 = \mpay_vg_certificate_fingerprint($cert_pem, 'sha256');
            if ($fpSha1) { $logCtx['fingerprint_sha1'] = $fpSha1; }
            if ($fpSha256) { $logCtx['fingerprint_sha256'] = $fpSha256; }
            Logger::log('Certificat MPay încărcat pentru verificare.', $logCtx);
            $mpayCertLogged = true;
        }

        $xp = new \DOMXPath($doc);
        $xp->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xp->registerNamespace('wsu', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd');
        $xp->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

        $sigList = $xp->query('//ds:Signature');
        if (!$sigList instanceof \DOMNodeList) {
            self::logFailure('AuthenticationFailed', 'Semnătura lipsește din mesaj.', ['reason'=>'xpath_failed']);
            return self::verificationError($telemetry, 'AuthenticationFailed', 'No Signature', ['reason'=>'signature_missing']);
        }
        $sigNode = $sigList->item(0);
        if (!$sigNode) {
            self::logFailure('AuthenticationFailed', 'Semnătura lipsește din mesaj.', []);
            return self::verificationError($telemetry, 'AuthenticationFailed', 'No Signature', ['reason'=>'signature_missing']);
        }
        $signedInfoList = $xp->query('.//ds:SignedInfo', $sigNode);
        $sigValueList = $xp->query('.//ds:SignatureValue', $sigNode);
        if (!$signedInfoList instanceof \DOMNodeList || !$sigValueList instanceof \DOMNodeList) {
            self::logFailure('AuthenticationFailed', 'Structura semnăturii este invalidă.', ['reason'=>'xpath_failed']);
            return self::verificationError($telemetry, 'AuthenticationFailed', 'Signature malformed', ['reason'=>'signedinfo_missing']);
        }
        $signedInfo = $signedInfoList->item(0);
        $sigValueNode = $sigValueList->item(0);
        if (!$signedInfo || !$sigValueNode) {
            self::logFailure('AuthenticationFailed', 'Structura semnăturii este invalidă.', []);
            return self::verificationError($telemetry, 'AuthenticationFailed', 'Signature malformed', ['reason'=>'signedinfo_missing']);
        }
        $sigValue = base64_decode(trim($sigValueNode->textContent));

        // Validate Timestamp freshness (Created/Expires) -> AuthorizationFailed on expired
    $createdList = $xp->query('//*[local-name()="Timestamp"]/*[local-name()="Created"]');
    $expiresList = $xp->query('//*[local-name()="Timestamp"]/*[local-name()="Expires"]');
        $created = ($createdList instanceof \DOMNodeList) ? $createdList->item(0) : null;
        $expires = ($expiresList instanceof \DOMNodeList) ? $expiresList->item(0) : null;

        if (!$created instanceof \DOMNode || !$expires instanceof \DOMNode) {
            self::logFailure('AuthenticationFailed', 'Marcaj temporal lipsă în antet.', ['reason'=>'timestamp_missing']);
            return self::verificationError($telemetry, 'AuthenticationFailed', 'Timestamp missing', ['reason'=>'timestamp_missing']);
        }
        if ($created && $expires) {
            $c = strtotime(trim($created->textContent));
            $e = strtotime(trim($expires->textContent));
            $telemetry['timestamp_created'] = $created?->textContent;
            $telemetry['timestamp_expires'] = $expires?->textContent;
            if ($c && $e && (time() < $c-300 || time() > $e+300)) {
                self::logFailure('AuthorizationFailed', 'Timestamp depășit.', ['created'=>$created?->textContent, 'expires'=>$expires?->textContent]);
                return self::verificationError($telemetry, 'AuthorizationFailed', 'Timestamp expired', ['reason'=>'timestamp_expired']);
            }
        }

        // Verify all Reference digests
        $refs = $xp->query('.//ds:Reference', $signedInfo);
        if (!$refs instanceof \DOMNodeList) {
            self::logFailure('AuthenticationFailed', 'Nu pot procesa referințele din semnătură.', ['reason'=>'xpath_failed']);
            return self::verificationError($telemetry, 'AuthenticationFailed', 'Reference list missing', ['reason'=>'reference_list_missing']);
        }
        foreach ($refs as $ref) {
            if (!$ref instanceof \DOMElement) {
                continue;
            }

            $uriRaw = trim((string) $ref->getAttribute('URI'));
            if ($uriRaw !== '' && $uriRaw[0] === '#') {
                $uri = trim(substr($uriRaw, 1));
            } else {
                $uri = $uriRaw;
            }
            $digestNodeList = $xp->query('.//ds:DigestValue', $ref);
            $digestMethodList = $xp->query('.//ds:DigestMethod', $ref);
            $digestNode = ($digestNodeList instanceof \DOMNodeList) ? $digestNodeList->item(0) : null;
            $digestMethod = ($digestMethodList instanceof \DOMNodeList) ? $digestMethodList->item(0) : null;
            if (!$digestNode || !$digestMethod) {
                self::logFailure('AuthenticationFailed', 'Digest lipsă în referință.', ['uri'=>$uri]);
                return self::verificationError($telemetry, 'AuthenticationFailed', 'Digest missing', ['reason'=>'digest_missing','reference_uri'=>$uri]);
            }
            $algo = $digestMethod->getAttribute('Algorithm');
            $target = null;
            if ($uri) {
                $xpath = '//*[@Id="'.$uri.'" or @wsu:Id="'.$uri.'" or @u:Id="'.$uri.'"]';
                $targetList = $xp->query($xpath);
                $target = ($targetList instanceof \DOMNodeList) ? $targetList->item(0) : null;
                if (!$target) {
                    $fallback = '//*[@*[local-name()="Id" and translate(normalize-space(@*[local-name()="Id"]), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz") = "'.strtolower($uri).'"]]';
                    $targetList = $xp->query($fallback);
                    $target = ($targetList instanceof \DOMNodeList) ? $targetList->item(0) : null;
                }
                if (!$target) {
                    $candidates = $xp->query('//*[@*]');
                    if ($candidates instanceof \DOMNodeList) {
                        foreach ($candidates as $candidate) {
                            if (!$candidate instanceof \DOMElement) {
                                continue;
                            }
                            foreach ($candidate->attributes as $attr) {
                                if (!$attr instanceof \DOMAttr) {
                                    continue;
                                }
                                $local = $attr->localName ?: $attr->name;
                                if (strcasecmp($local, 'Id') !== 0) {
                                    continue;
                                }
                                $value = trim($attr->value);
                                if ($value === '' || strcasecmp($value, $uri) !== 0) {
                                    continue;
                                }
                                $target = $candidate;
                                break 2;
                            }
                        }
                    }
                }
                if (!$target) {
                    $approx = [];
                    $candidates = $xp->query('//*[@*]');
                    if ($candidates instanceof \DOMNodeList) {
                        foreach ($candidates as $candidate) {
                            if (!$candidate instanceof \DOMElement) {
                                continue;
                            }
                            foreach ($candidate->attributes as $attr) {
                                if (!$attr instanceof \DOMAttr) {
                                    continue;
                                }
                                $val = trim($attr->value);
                                if ($val === '' || stripos($val, $uri) === false) {
                                    continue;
                                }
                                $approx[] = $candidate->localName.'@'.$attr->name.'='.$val;
                                if (count($approx) >= 10) {
                                    break 2;
                                }
                            }
                        }
                    }
                    if ($approx) {
                        // aid debugging when fragment not found
                        Logger::log('Referință fără ţintă găsită, valori similare.', [
                            'component' => 'wssecurity.verify',
                            'code' => 'target_candidates',
                            'uri' => $uri,
                            'candidates' => $approx,
                        ], 'error');
                    }
                }
            } else {
                $bodyList = $xp->query('//soap:Body');
                $target = ($bodyList instanceof \DOMNodeList) ? $bodyList->item(0) : null;
            }

            if (!$target instanceof \DOMNode) {
                self::logFailure('AuthenticationFailed', 'Nu pot identifica fragmentul semnat.', ['reason'=>'target_missing','uri'=>$uri]);
                return self::verificationError($telemetry, 'AuthenticationFailed', 'Signed fragment missing', ['reason'=>'target_missing','reference_uri'=>$uri]);
            }
            if (!$target) {
                self::logFailure('AuthenticationFailed', 'Nu găsesc nodul referit de semnătură.', ['uri'=>$uri]);
                return self::verificationError($telemetry, 'AuthenticationFailed', 'Reference target not found', ['reason'=>'target_not_found','reference_uri'=>$uri]);
            }
            $canon = $target->C14N(true, false);
            $digest = null;
            if (stripos($algo, 'sha256') !== false) $digest = base64_encode(hash('sha256', $canon, true));
            else $digest = base64_encode(hash('sha1', $canon, true));
            $expectedDigest = trim($digestNode->textContent);
            $telemetry['references'][] = [
                'uri' => $uri ? '#'.$uri : 'soap:Body',
                'algorithm' => stripos($algo, 'sha256') !== false ? 'sha256' : 'sha1',
                'expected_digest' => $expectedDigest,
                'calculated_digest' => $digest,
                'length' => strlen($canon),
            ];
            if ($expectedDigest !== $digest) {
                self::logFailure('AuthenticationFailed', 'Digest mismatch.', ['uri'=>$uri]);
                return self::verificationError($telemetry, 'AuthenticationFailed', 'Digest mismatch', ['reason'=>'digest_mismatch','reference_uri'=>$uri]);
            }
        }

        // Verify SignedInfo signature using MPay public cert
        $canonSignedInfo = $signedInfo->C14N(true, false);
        $pub = openssl_pkey_get_public($cert_pem);
        if (!$pub) {
            self::logFailure('AuthenticationFailed', 'Cheia publică MPay este invalidă.', []);
            return self::verificationError($telemetry, 'AuthenticationFailed', 'Public key invalid', ['reason'=>'public_key_invalid']);
        }
    $sigMethodList = $xp->query('.//ds:SignatureMethod', $signedInfo);
    $sigMethod = ($sigMethodList instanceof \DOMNodeList) ? $sigMethodList->item(0) : null;
    $algo = $sigMethod ? $sigMethod->getAttribute('Algorithm') : '';
        $ok = false;
        if (stripos($algo, 'rsa-sha256') !== false) {
            $ok = openssl_verify($canonSignedInfo, $sigValue, $pub, OPENSSL_ALGO_SHA256) === 1;
        } else {
            $ok = openssl_verify($canonSignedInfo, $sigValue, $pub, OPENSSL_ALGO_SHA1) === 1;
        }
        $telemetry['signature_algorithm'] = stripos($algo, 'rsa-sha256') !== false ? 'rsa-sha256' : 'rsa-sha1';
        $telemetry['signature_length'] = strlen($sigValue);
        $telemetry['signed_info_bytes'] = strlen($canonSignedInfo);
        $telemetry['signature_value_b64'] = trim($sigValueNode->textContent);
        if (!$ok) {
            return self::verificationError($telemetry, 'AuthenticationFailed', 'Signature verify failed', ['reason'=>'openssl_verify_failed']);
        }
        return self::verificationSuccess($telemetry);
    }

    public static function sign(string $soapEnvelope) : string {
        $opts = \mpay_vg_get_settings();
        if (empty($opts['enforce_wssec'])) return $soapEnvelope;
        [$pkey, $certBody] = self::loadSigningMaterial($opts);
        $signTelemetry = [
            'cert_fingerprint_sha1' => $certBody ? \mpay_vg_certificate_fingerprint($certBody, 'sha1') : null,
            'cert_fingerprint_sha256' => $certBody ? \mpay_vg_certificate_fingerprint($certBody, 'sha256') : null,
            'private_key_path' => $opts['sp_private_key_path'] ?? '',
            'public_cert_path' => $opts['sp_public_cert_path'] ?? '',
            'references' => [],
            'result' => 'pending',
        ];
        static $signMaterialLogged = false;
        if (!$signMaterialLogged) {
            $logCtx = [
                'component' => 'wssecurity.sign',
                'code' => 'signing_material_ready',
                'private_key_path' => $opts['sp_private_key_path'] ?? '',
                'public_cert_path' => $opts['sp_public_cert_path'] ?? '',
            ];
            if ($certBody) {
                $fpSha1 = \mpay_vg_certificate_fingerprint($certBody, 'sha1');
                $fpSha256 = \mpay_vg_certificate_fingerprint($certBody, 'sha256');
                if ($fpSha1) { $logCtx['fingerprint_sha1'] = $fpSha1; }
                if ($fpSha256) { $logCtx['fingerprint_sha256'] = $fpSha256; }
            }
            Logger::log('Materialul de semnare WS-Security a fost pregătit.', $logCtx);
            $signMaterialLogged = true;
        }
        if (!$pkey) {
            \MPAY_VG\Core\Logger::log('WS-Security signing omis: lipsă cheie privată.', [
                'component' => 'wssecurity.sign',
                'code' => 'missing_private_key',
            ], 'error');
            $signTelemetry['result'] = 'error';
            $signTelemetry['reason'] = 'missing_private_key';
            self::recordSignatureRuntime($signTelemetry);
            return $soapEnvelope;
        }

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = false;
        $doc->loadXML($soapEnvelope, LIBXML_NONET);
        $xp = new \DOMXPath($doc);
        $xp->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xp->registerNamespace('wsu', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd');
        $xp->registerNamespace('wsse', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd');
        $xp->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

        $header = $xp->query('//soap:Header')->item(0);
        $body = $xp->query('//soap:Body')->item(0);
        if (!$header || !$body) return $soapEnvelope;

        $secNs = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';
        $wsuNs = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd';

        $soapNs = 'http://schemas.xmlsoap.org/soap/envelope/';

        // Ensure Body has Id
        if (!$body->hasAttribute('wsu:Id')) {
            $body->setAttributeNS($wsuNs, 'wsu:Id', 'id-'.self::randomIdFragment());
        }
        $bodyId = $body->getAttribute('wsu:Id');
        if (!$body->hasAttribute('Id')) {
            $body->setAttribute('Id', $bodyId);
        }

        // Add security headers according to WS-Security profile
        $sec = $xp->query('//wsse:Security')->item(0);
        if (!$sec) {
            $sec = $doc->createElementNS($secNs, 'wsse:Security');
            $header->appendChild($sec);
        }
        $sec->setAttributeNS($soapNs, 'soapenv:mustUnderstand', '1');

        $bstId = null;
        if ($certBody) {
            $bstId = 'X509-'.self::randomIdFragment();
            $bst = $doc->createElementNS($secNs, 'wsse:BinarySecurityToken', self::certBodyNoPem($certBody));
            $bst->setAttribute('EncodingType', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary');
            $bst->setAttribute('ValueType', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3');
            $bst->setAttributeNS($wsuNs, 'wsu:Id', $bstId);
            $sec->appendChild($bst);
        }

        // Add Timestamp
        $tsNodeList = $xp->query('./wsu:Timestamp', $sec);
        $ts = ($tsNodeList instanceof \DOMNodeList) ? $tsNodeList->item(0) : null;
        if (!$ts instanceof \DOMElement) {
            $ts = $doc->createElementNS($wsuNs, 'wsu:Timestamp');
            $sec->appendChild($ts);
        } else {
            while ($ts->firstChild) {
                $ts->removeChild($ts->firstChild);
            }
        }
        $tsId = 'TS-'.self::randomIdFragment();
        $ts->setAttributeNS($wsuNs, 'wsu:Id', $tsId);
        $ts->setAttribute('Id', $tsId);
        $created = gmdate('Y-m-d\TH:i:s\Z');
        $expires = gmdate('Y-m-d\TH:i:s\Z', time()+300);
        $ts->appendChild($doc->createElementNS($wsuNs, 'wsu:Created', $created));
        $ts->appendChild($doc->createElementNS($wsuNs, 'wsu:Expires', $expires));

        // Build SignedInfo
        $sigNs = 'http://www.w3.org/2000/09/xmldsig#';
    $sig = $doc->createElementNS($sigNs, 'ds:Signature');
    $sigId = 'SIG-'.self::randomIdFragment();
    $sig->setAttribute('Id', $sigId);
        $signedInfo = $doc->createElementNS($sig->namespaceURI, 'ds:SignedInfo');

        $cm = $doc->createElementNS($sig->namespaceURI, 'ds:CanonicalizationMethod');
        $cm->setAttribute('Algorithm', 'http://www.w3.org/2001/10/xml-exc-c14n#');
        $signedInfo->appendChild($cm);

        $sm = $doc->createElementNS($sig->namespaceURI, 'ds:SignatureMethod');
        $sm->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#rsa-sha1');
        $signedInfo->appendChild($sm);

        $references = [
            [$tsId, $ts, ['wsse','mpay','soapenv','wsu']],
            [$bodyId, $body, ['mpay','wsu']],
        ];
        $signTelemetry['body_id'] = $bodyId;
        $signTelemetry['timestamp_id'] = $tsId;

        foreach ($references as $spec) {
            [$refId, $targetNode, $prefixCandidates] = $spec;
            if (!$targetNode instanceof \DOMNode) {
                continue;
            }

            $ref = $doc->createElementNS($sig->namespaceURI, 'ds:Reference');
            $ref->setAttribute('URI', '#'.$refId);
            $transforms = $doc->createElementNS($sig->namespaceURI, 'ds:Transforms');
            $tr = $doc->createElementNS($sig->namespaceURI, 'ds:Transform');
            $tr->setAttribute('Algorithm', 'http://www.w3.org/2001/10/xml-exc-c14n#');
            // No InclusiveNamespaces: we sign with default Exclusive C14N to keep digest consistent
            $transforms->appendChild($tr);
            $ref->appendChild($transforms);

            $canon = $targetNode->C14N(true, false);
            $digest = base64_encode(hash('sha1', $canon, true));
            $signTelemetry['references'][] = [
                'uri' => '#'.$refId,
                'digest' => $digest,
                'length' => strlen($canon),
                'algorithm' => 'sha1',
            ];

            $dm = $doc->createElementNS($sig->namespaceURI, 'ds:DigestMethod');
            $dm->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');
            $dv = $doc->createElementNS($sig->namespaceURI, 'ds:DigestValue', $digest);
            $ref->appendChild($dm);
            $ref->appendChild($dv);
            $signedInfo->appendChild($ref);
        }

        $sig->appendChild($signedInfo);

    $ki = $doc->createElementNS($sig->namespaceURI, 'ds:KeyInfo');
    $ki->setAttribute('Id', 'KI-'.self::randomIdFragment());
        if ($bstId) {
            $strNode = $doc->createElementNS($secNs, 'wsse:SecurityTokenReference');
            $strNode->setAttributeNS($wsuNs, 'wsu:Id', 'STR-'.self::randomIdFragment());
            $refNode = $doc->createElementNS($secNs, 'wsse:Reference');
            $refNode->setAttribute('URI', '#'.$bstId);
            $refNode->setAttribute('ValueType', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3');
            $strNode->appendChild($refNode);
            $ki->appendChild($strNode);
        } elseif ($certBody) {
            $x509 = $doc->createElementNS($sig->namespaceURI, 'ds:X509Data');
            $x509cert = $doc->createElementNS($sig->namespaceURI, 'ds:X509Certificate', self::certBodyNoPem($certBody));
            $x509->appendChild($x509cert);
            $ki->appendChild($x509);
        }
        $sig->appendChild($ki);

        $sec->appendChild($sig);
        if ($ts->parentNode === $sec) {
            $sec->removeChild($ts);
            $sec->appendChild($ts);
        }

        // Sign SignedInfo
        $canonSignedInfo = $signedInfo->C14N(true, false);
        $signature = '';
        $signOk = openssl_sign($canonSignedInfo, $signature, $pkey, OPENSSL_ALGO_SHA1);
        if (!$signOk) {
            Logger::log('WS-Security semnare eșuată.', [
                'component' => 'wssecurity.sign',
                'code' => 'sign_failure',
                'reason' => 'openssl_sign_failed',
            ], 'error');
            $signTelemetry['result'] = 'error';
            $signTelemetry['reason'] = 'openssl_sign_failed';
            self::recordSignatureRuntime($signTelemetry);
            return $soapEnvelope;
        }
        $signTelemetry['signed_info_bytes'] = strlen($canonSignedInfo);
        $signTelemetry['signature_length'] = strlen($signature);
        $signTelemetry['signature_sha256'] = strtoupper(hash('sha256', $signature));
        $signTelemetry['signature_algorithm'] = 'rsa-sha1';
        $sigValue = $doc->createElementNS($sig->namespaceURI, 'ds:SignatureValue', base64_encode($signature));
        // Insert after SignedInfo
        $signedInfo->parentNode->insertBefore($sigValue, $ki);

        $finalXml = self::serializeDocument($doc);
        $signTelemetry['envelope_bytes'] = strlen($finalXml);
        $signTelemetry['result'] = 'success';
        Logger::log('Envelope SOAP semnat.', [
            'component' => 'wssecurity.sign',
            'code' => 'signature_generated',
            'body_id' => $signTelemetry['body_id'] ?? '',
            'timestamp_id' => $signTelemetry['timestamp_id'] ?? '',
            'references' => $signTelemetry['references'],
            'signed_info_bytes' => $signTelemetry['signed_info_bytes'] ?? 0,
            'signature_length' => $signTelemetry['signature_length'] ?? 0,
            'signature_sha256' => $signTelemetry['signature_sha256'] ?? '',
            'signature_algorithm' => $signTelemetry['signature_algorithm'] ?? 'rsa-sha1',
            'envelope_bytes' => $signTelemetry['envelope_bytes'] ?? 0,
        ]);
        self::recordSignatureRuntime($signTelemetry);
        return $finalXml;
    }

    private static function verificationError(array $telemetry, string $code, string $message, array $extra = []) : array {
        $data = array_merge($telemetry, $extra);
        self::recordVerificationRuntime($data, $code, $message, false);
        return ['ok'=>false, 'code'=>$code, 'msg'=>$message];
    }

    private static function verificationSuccess(array $telemetry) : array {
        self::recordVerificationRuntime($telemetry, 'OK', 'Signature verified', true);
        return ['ok'=>true];
    }

    private static function recordVerificationRuntime(array $telemetry, string $code, string $message, bool $ok) : void {
        $telemetry['result'] = $ok ? 'ok' : 'error';
        $telemetry['code'] = $code;
        $telemetry['message'] = $message;
        $telemetry['timestamp'] = time();
        \mpay_vg_set_runtime('last_verify', $telemetry, 900);
    }

    private static function recordSignatureRuntime(array $telemetry) : void {
        $telemetry['timestamp'] = time();
        \mpay_vg_set_runtime('last_signature', $telemetry, 900);
    }

    private static function detectOperation(\DOMDocument $doc) : ?string {
        $xp = new \DOMXPath($doc);
        $node = $xp->query('//*[local-name()="Body"]/*[1]');
        if ($node && $node->length) {
            return $node->item(0)->localName;
        }
        return null;
    }

    private static function certBodyNoPem($pem) {
        $pem = trim($pem);
        $pem = preg_replace('/\-+BEGIN CERTIFICATE\-+/', '', $pem);
        $pem = preg_replace('/\-+END CERTIFICATE\-+/', '', $pem);
        $pem = str_replace(["\r","\n"], '', $pem);
        return $pem;
    }

    private static function serializeDocument(\DOMDocument $doc) : string {
        $xml = $doc->saveXML();
        if (!is_string($xml) || $xml === '') {
            return '';
        }
        return rtrim($xml);
    }

    private static function randomIdFragment(int $bytes = 16) : string {
        $random = null;
        if (function_exists('random_bytes')) {
            try {
                $random = random_bytes($bytes);
            } catch (\Throwable $e) {
                $random = null;
            }
        }
        if ($random === null && function_exists('openssl_random_pseudo_bytes')) {
            $random = openssl_random_pseudo_bytes($bytes);
        }
        if (is_string($random) && $random !== '') {
            return strtoupper(bin2hex($random));
        }
        $fallback = \wp_generate_password($bytes, false, false);
        $sanitized = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $fallback));
        if ($sanitized !== '') {
            return substr(strtoupper(hash('sha256', $sanitized.microtime(true))), 0, $bytes * 2);
        }
        $fallbackHash = strtoupper(sha1(uniqid('', true)));
        return substr($fallbackHash, 0, $bytes * 2);
    }

    private static function inclusivePrefixList(?\DOMNode $context, array $candidates) : string {
        if (!$context instanceof \DOMNode) {
            return '';
        }
        $found = [];
        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '') {
                continue;
            }
            $uri = $context->lookupNamespaceURI($candidate);
            if ($uri !== null && $uri !== '') {
                $found[$candidate] = true;
            }
        }
        if (!$found) {
            return '';
        }
        return implode(' ', array_keys($found));
    }

    private static function loadSigningMaterial(array $opts) {
        $priv_path = $opts['sp_private_key_path'] ?? '';
        if (!$priv_path || !file_exists($priv_path)) {
            return [null, null];
        }
        $pass = $opts['sp_key_passphrase'] ?? '';
        $pub_path = $opts['sp_public_cert_path'] ?? '';

        $ext = strtolower(pathinfo($priv_path, PATHINFO_EXTENSION));
        if (in_array($ext, ['pfx','p12'], true)) {
            $certs = \mpay_vg_read_pkcs12($priv_path, $pass ?: '');
            if (is_wp_error($certs)) {
                Logger::log('PKCS#12 nu poate fi încărcat.', [
                    'component' => 'security.pkcs12',
                    'code' => $certs->get_error_code(),
                    'path' => $priv_path,
                    'message' => $certs->get_error_message(),
                ], 'error');
                return [null, null];
            }
            $pkeyPem = $certs['pkey'] ?? '';
            if (!$pkeyPem) {
                Logger::log('PKCS#12 nu conține cheia privată.', [
                    'component' => 'security.pkcs12',
                    'code' => 'pkey_missing',
                    'path' => $priv_path,
                ], 'error');
                return [null, null];
            }
            if (!empty($certs['source']) && $certs['source'] === 'cli') {
                static $loggedCliFallback = false;
                if (!$loggedCliFallback) {
                    Logger::log('PKCS#12 încărcat folosind fallback OpenSSL CLI.', [
                        'component' => 'security.pkcs12',
                        'code' => 'cli_fallback',
                        'path' => $priv_path,
                    ]);
                    $loggedCliFallback = true;
                }
            }
            $pkey = openssl_pkey_get_private($pkeyPem, $pass ?: '');
            if (!$pkey) {
                Logger::log('Nu pot interpreta cheia privată din PKCS#12.', [
                    'component' => 'security.pkcs12',
                    'code' => 'pkey_parse_failed',
                    'path' => $priv_path,
                ], 'error');
                return [null, null];
            }
            $certBody = '';
            if (!empty($certs['cert'])) {
                $certBody = \mpay_vg_normalize_certificate($certs['cert']);
            } elseif ($pub_path && file_exists($pub_path)) {
                $certBody = \mpay_vg_normalize_certificate(@file_get_contents($pub_path));
            }
            return [$pkey, $certBody];
        }

        $priv_contents = @file_get_contents($priv_path);
        if ($priv_contents === false) { return [null, null]; }
        $pkey = openssl_pkey_get_private($priv_contents, $pass ?: '');
        if (!$pkey) { return [null, null]; }
        $certBody = '';
        if ($pub_path && file_exists($pub_path)) {
            $cert_raw = @file_get_contents($pub_path);
            if ($cert_raw !== false) {
                $certBody = \mpay_vg_normalize_certificate($cert_raw);
            }
        }
        return [$pkey, $certBody];
    }
}
