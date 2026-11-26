<?php

namespace App\Facturacion\Core\Certificado;

use DateTimeImmutable;

/**
 * Certificado digital de firma (X.509 + clave privada) en formato PEM, que es lo que usa el firmador.
 */
final readonly class CertificadoDigital
{
    private function __construct(
        public string $certificadoPem,
        private string $clavePrivadaPem,
        public DateTimeImmutable $validoDesde,
        public DateTimeImmutable $validoHasta,
        public string $sujeto,
        public string $emisor,
        public string $numeroSerie,
    ) {}

    /**
     * Carga un .pfx/.p12 (binario, con contraseña) o un .pem (certificado + clave privada).
     *
     * @throws CertificadoInvalido
     */
    public static function cargar(string $contenido, ?string $clave = null): self
    {
        return str_contains($contenido, '-----BEGIN')
            ? self::desdePem($contenido, $clave)
            : self::desdePfx($contenido, (string) $clave);
    }

    public static function desdePfx(string $contenido, string $clave): self
    {
        self::erroresOpenssl();
        $partes = [];
        if (! openssl_pkcs12_read($contenido, $partes, $clave)) {
            $detalle = self::erroresOpenssl();

            if (str_contains($detalle, 'unsupported') || str_contains($detalle, 'RC2')) {
                throw new CertificadoInvalido(
                    'El .pfx usa un cifrado antiguo (RC2/3DES) que OpenSSL 3 ya no soporta por defecto. '
                    .'Conviértalo a PEM con: openssl pkcs12 -legacy -in certificado.pfx -out certificado.pem -nodes'
                );
            }

            throw new CertificadoInvalido('No se pudo abrir el .pfx: la contraseña es incorrecta o el archivo está dañado. '.$detalle);
        }

        return self::desdePartes($partes['cert'] ?? '', $partes['pkey'] ?? '');
    }

    public static function desdePem(string $contenido, ?string $clave = null): self
    {
        if (! preg_match('/-----BEGIN CERTIFICATE-----.+?-----END CERTIFICATE-----/s', $contenido, $cert)) {
            throw new CertificadoInvalido('El archivo PEM no contiene un certificado (BEGIN CERTIFICATE).');
        }

        if (! preg_match('/-----BEGIN (?:RSA |ENCRYPTED )?PRIVATE KEY-----.+?-----END (?:RSA |ENCRYPTED )?PRIVATE KEY-----/s', $contenido, $llave)) {
            throw new CertificadoInvalido('El archivo PEM no contiene la clave privada (BEGIN PRIVATE KEY).');
        }

        self::erroresOpenssl();
        $pkey = openssl_pkey_get_private($llave[0], $clave);
        if ($pkey === false) {
            throw new CertificadoInvalido('No se pudo leer la clave privada del PEM (¿contraseña?). '.self::erroresOpenssl());
        }

        // Sin cifrar se usa tal cual: openssl_pkey_export exige openssl.cnf y en Windows/Laragon suele no existir.
        $llavePlano = str_contains($llave[0], 'ENCRYPTED') ? self::exportarClave($pkey) : $llave[0];

        return self::desdePartes($cert[0], $llavePlano);
    }

    private static function exportarClave(\OpenSSLAsymmetricKey $pkey): string
    {
        $config = tempnam(sys_get_temp_dir(), 'ossl');
        file_put_contents($config, "[req]\ndistinguished_name=dn\n[dn]\n");

        try {
            if (! openssl_pkey_export($pkey, $salida, null, ['config' => $config])) {
                throw new CertificadoInvalido('No se pudo descifrar la clave privada. '.self::erroresOpenssl());
            }

            return $salida;
        } finally {
            @unlink($config);
        }
    }

    private static function desdePartes(string $certificadoPem, string $clavePrivadaPem): self
    {
        if ($certificadoPem === '' || $clavePrivadaPem === '') {
            throw new CertificadoInvalido('El certificado no incluye certificado y clave privada.');
        }

        $x509 = openssl_x509_read($certificadoPem);
        if ($x509 === false) {
            throw new CertificadoInvalido('Certificado X.509 ilegible. '.self::erroresOpenssl());
        }

        if (! openssl_x509_check_private_key($x509, $clavePrivadaPem)) {
            throw new CertificadoInvalido('La clave privada no corresponde al certificado.');
        }

        $info = openssl_x509_parse($x509);
        if ($info === false) {
            throw new CertificadoInvalido('No se pudo leer la información del certificado.');
        }

        return new self(
            certificadoPem: trim($certificadoPem)."\n",
            clavePrivadaPem: trim($clavePrivadaPem)."\n",
            validoDesde: (new DateTimeImmutable)->setTimestamp((int) $info['validFrom_time_t']),
            validoHasta: (new DateTimeImmutable)->setTimestamp((int) $info['validTo_time_t']),
            sujeto: self::nombreDistinguido($info['subject'] ?? []),
            emisor: self::nombreDistinguido($info['issuer'] ?? []),
            numeroSerie: (string) ($info['serialNumberHex'] ?? $info['serialNumber'] ?? ''),
        );
    }

    /**
     * Certificado + clave privada concatenados (formato que espera el firmador XMLDSig).
     */
    public function pem(): string
    {
        return $this->certificadoPem.$this->clavePrivadaPem;
    }

    public function estaVigente(DateTimeImmutable $ahora): bool
    {
        return $ahora >= $this->validoDesde && $ahora <= $this->validoHasta;
    }

    /**
     * Días que faltan para vencer (negativo si ya venció).
     */
    public function diasParaVencer(DateTimeImmutable $ahora): int
    {
        return (int) $ahora->diff($this->validoHasta)->format('%r%a');
    }

    /**
     * RUC encontrado en el sujeto del certificado, si lo hay (los certificados SUNAT/INDECOPI suelen incluirlo).
     */
    public function rucEnSujeto(): ?string
    {
        return preg_match('/\b(10|15|16|17|20)\d{9}\b/', $this->sujeto, $m) ? $m[0] : null;
    }

    /**
     * @param  array<string, string|list<string>>  $partes
     */
    private static function nombreDistinguido(array $partes): string
    {
        $texto = [];
        foreach ($partes as $clave => $valor) {
            foreach ((array) $valor as $v) {
                $texto[] = "{$clave}={$v}";
            }
        }

        return implode(', ', $texto);
    }

    private static function erroresOpenssl(): string
    {
        $errores = [];
        while (($error = openssl_error_string()) !== false) {
            $errores[] = $error;
        }

        return implode(' | ', $errores);
    }
}
