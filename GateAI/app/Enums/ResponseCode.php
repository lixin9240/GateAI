<?php
// 响应码枚举
namespace App\Enums;

enum ResponseCode: int
{
    case SUCCESS = 0;

    case PARAM_ERROR = 10001;

    case UNAUTHORIZED = 20001;

    case FORBIDDEN = 20002;

    case DATA_NOT_FOUND = 30001;

    case BUSINESS_ERROR = 40001;

    case THIRD_PARTY_ERROR = 50001;

    case DATABASE_ERROR = 60001;

    case SYSTEM_ERROR = 90001;

    public function msg(): string
    {
        return match ($this) {
            self::SUCCESS           => '成功',
            self::PARAM_ERROR       => '参数错误',
            self::UNAUTHORIZED      => '未登录',
            self::FORBIDDEN         => '无权限访问',
            self::DATA_NOT_FOUND    => '记录不存在',
            self::BUSINESS_ERROR    => '业务处理失败',
            self::THIRD_PARTY_ERROR => '第三方服务异常',
            self::DATABASE_ERROR    => '数据库异常',
            self::SYSTEM_ERROR      => '系统异常',
        };
    }
}
