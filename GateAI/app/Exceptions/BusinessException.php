<?php
// 业务异常
namespace App\Exceptions;

use App\Enums\ResponseCode;
use Exception;

class BusinessException extends Exception
{
    public readonly ResponseCode $codeEnum;
    public readonly mixed $errorData;

    public function __construct(string $message = '', ?ResponseCode $code = null, mixed $data = null)
    {
        $codeEnum = $code ?? ResponseCode::BUSINESS_ERROR;
        parent::__construct($message, $codeEnum->value);
        $this->codeEnum  = $codeEnum;
        $this->errorData = $data;
    }
}
