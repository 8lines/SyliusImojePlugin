<?php

declare(strict_types=1);

namespace BitBag\SyliusIngPlugin\Exception;

interface IngClientExceptionInterface
{
    public function getStatusCode(): int;
}
