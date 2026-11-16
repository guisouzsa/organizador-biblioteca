<?php

namespace App\Exceptions;

use RuntimeException;

/** Falha ao consultar uma fonte bibliográfica externa (rede, timeout, HTTP 5xx/429). */
class ExternalLookupException extends RuntimeException {}
