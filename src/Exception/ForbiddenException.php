<?php

declare(strict_types=1);

namespace GSMBinancePay\WC\Exception;

class ForbiddenException extends RequestException
{
    public const STATUS = 403;
}
