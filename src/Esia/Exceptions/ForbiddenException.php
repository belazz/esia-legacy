<?php

namespace Esia\Exceptions;

class ForbiddenException extends AbstractEsiaException
{
    protected function getMessageForCode($code)
    {
        return 'Forbidden';
    }
}
