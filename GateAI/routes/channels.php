<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// ─── 数字孪生频道（LX 模块） ────────────────
Broadcast::channel('simulation.{simulationId}', function ($user, $simulationId) {
    return $user !== null;
});

// ─── 边缘端数据推送频道 ────────────────────
Broadcast::channel('edge.{edgeId}', function ($user, $edgeId) {
    return true;
});

// ─── 系统设置频道（GYZ 模块） ───────────────
Broadcast::channel('settings.models.health', function ($user) {
    return true;
});

Broadcast::channel('settings.config', function ($user) {
    return true;
});