<?php

namespace App\Facturacion\Core\Certificado;

use RuntimeException;

/**
 * Certificado autofirmado para SUNAT beta (beta no valida la cadena de confianza).
 * NUNCA sirve para producción.
 */
final class GeneradorCertificadoPrueba
{
    /**
     * @return array{pem: string, pfx: string, clave: string}
     */
    public static function generar(string $ruc, int $dias = 365, string $clave = '123456'): array
    {
        // Config mínima: en Windows/Laragon PHP no encuentra openssl.cnf y openssl_pkey_new falla.
        $config = tempnam(sys_get_temp_dir(), 'ossl');
        file_put_contents($config, "[req]\ndistinguished_name=dn\n[dn]\n");
        $opciones = ['config' => $config, 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'digest_alg' => 'sha256'];

        try {
            $llave = openssl_pkey_new($opciones);
            $csr = $llave ? openssl_csr_new(['countryName' => 'PE', 'organizationName' => 'CERTIFICADO DE PRUEBA', 'commonName' => $ruc], $llave, $opciones) : false;
            $x509 = ($llave && $csr instanceof \OpenSSLCertificateSigningRequest) ? openssl_csr_sign($csr, null, $llave, $dias, $opciones) : false;

            if (! $llave || ! $x509 || ! openssl_x509_export($x509, $cert) || ! openssl_pkey_export($llave, $llavePem, null, $opciones)) {
                throw new RuntimeException('No se pudo generar el certificado de prueba: '.openssl_error_string());
            }

            openssl_pkcs12_export($x509, $pfx, $llave, $clave);

            return ['pem' => $cert.$llavePem, 'pfx' => $pfx, 'clave' => $clave];
        } finally {
            @unlink($config);
        }
    }
}
