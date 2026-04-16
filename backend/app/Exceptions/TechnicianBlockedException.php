<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class TechnicianBlockedException extends HttpException
{
    public function __construct(string $message = 'Técnico bloqueado: possui selos com prazo PSEI vencido.')
    {
        parent::__construct(422, $message);
    }
}
