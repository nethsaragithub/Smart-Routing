<?php

namespace App\Exceptions;

use DomainException;

/**
 * Thrown when an operation would break a scheduling business rule.
 * Controllers catch it and show the message to the user.
 */
class SchedulingException extends DomainException
{
}
