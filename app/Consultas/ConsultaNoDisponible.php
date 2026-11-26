<?php

namespace App\Consultas;

use RuntimeException;

/** SUNAT (u otra fuente) no respondió tras varios intentos: no significa que el documento no exista. */
class ConsultaNoDisponible extends RuntimeException {}
