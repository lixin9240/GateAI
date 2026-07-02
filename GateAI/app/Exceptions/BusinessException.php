<?php
// 业务异常
namespace App\Exceptions;

use App\Enums\ResponseCode;
use Exception;

class BusinessException extends Exception
{
    public readonly ResponseCode $codeEnum;

    public function __construct(string $message = '', ?ResponseCode $code = null)
    {
        parent::__construct($message);
        $this->codeEnum = $code ?? ResponseCode::BUSINESS_ERROR;
    }
}
