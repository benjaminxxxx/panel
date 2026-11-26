<?php

namespace App\Facturacion;

use DomainException;

/**
 * La empresa no puede emitir (sin certificado, vencido, sin clave SOL, desactivada).
 */
final class EmpresaNoHabilitada extends DomainException {}
