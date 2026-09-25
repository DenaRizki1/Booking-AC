<?php

namespace App\Exceptions;

use DomainException;

/**
 * Perubahan status order tidak sesuai state machine atau aturan bisnis.
 */
class InvalidStatusTransitionException extends DomainException {}
