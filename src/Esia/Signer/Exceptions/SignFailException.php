<?php

namespace Esia\Signer\Exceptions;

use Esia\Exceptions\AbstractEsiaException;

class SignFailException extends AbstractEsiaException
{
    protected function getMessageForCode($code)
    {
        return 'Signing is failed';
    }
}
