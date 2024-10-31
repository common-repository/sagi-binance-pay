<?php

declare(strict_types=1);

namespace GSMBinancePay\WC\Exception;

class BadRequestException extends RequestException
{
    public const STATUS = 400;
}
