<?php

namespace App\Facturacion\Core\Sunat;

use App\Facturacion\Core\Dominio\DocumentoElectronico;

/**
 * Motor de XML + firma + envío. Hoy lo implementa Greenter; se puede reemplazar sin tocar el resto.
 *
 * Firmar y enviar están separados a propósito: el XML firmado se guarda ANTES de enviar,
 * así un corte de red permite reenviar exactamente el mismo documento.
 */
interface ServicioSunat
{
    public function firmar(DocumentoElectronico $documento, Credenciales $credenciales): XmlFirmado;

    /**
     * Nunca lanza excepción: cualquier falla se devuelve como ResultadoEnvio con estado ErrorEnvio.
     */
    public function enviar(XmlFirmado $xml, Credenciales $credenciales): ResultadoEnvio;
}
