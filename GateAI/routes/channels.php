<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// ─── 边缘端数据推送频道 ────────────────────
Broadcast::channel('edge.{edgeId}', function ($user, $edgeId) {
    return true; // 认证用户可订阅（前端需登录）
});

// ─── 系统设置频道 ──────────────────────────
Broadcast::channel('settings.models.health', function ($user) {
    return true; // 所有登录用户可接收模型健康告警
});

Broadcast::channel('settings.config', function ($user) {
    return true;
});
