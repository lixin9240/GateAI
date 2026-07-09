# GYZ 模块 — 近期新增/变更接口

> **Base URL：** `http://47.108.169.152:8089/api/v1`

---

## 权限总览

| 标记 | 需要哪个 token |
|:--:|------|
| 🔒 | 任意登录用户（`algorithm / algo123` 或 `admin / admin123` 都行） |
| 🔑 | `admin` 或 `algorithm` |
| 👑 | 必须 `admin / admin123` |

---

## 新增接口

### 1. 模型列表导出 CSV 🔒

**Apifox 操作步骤：**
1. 方法选 **GET**
2. URL 填 `{{baseUrl}}/settings/models/export`
3. **Params** 标签页：什么都不填，没有 Query 参数
4. **Headers** 标签页：什么都不填（公开数据，不需要 Token）
5. **Body** 标签页：选 `none`（GET 请求不带 Body）
6. 点 **「发送」**
7. 响应区显示 CSV 文本内容，浏览器里直接打开会下载 `.csv` 文件

**返回示例（CSV 文本）：**
```csv
ID,名称,版本,类型,框架,状态,准确率,训练日期,大小(MB),激活
1,Physics-Informed LSTM v5.0,5.0.0,lstm_prediction,pytorch,active,99.99%,2026-07-02,2MB,1
2,Physics-Informed DQN v5.0,5.0.0,dqn_decision,pytorch,deprecated,85.40%,2026-07-02,1MB,0
```

---

### 2. 用户列表导出 CSV 🔒

**Apifox 操作步骤：**
1. 方法选 **GET**
2. URL 填 `{{baseUrl}}/settings/users/export`
3. **Params** 标签页：什么都不填
4. **Headers** 标签页：什么都不填
5. **Body** 标签页：选 `none`
6. 点 **「发送」**
7. 响应区显示 CSV 文本

**返回示例（CSV 文本）：**
```csv
ID,账号,姓名,角色,手机,启用,锁定
1,admin,系统管理员,系统管理员,13800000001,是,否
6,locked_user,已被锁定,运维人员,13800000006,是,是
```

---

### 3. 执行回填 🔒

**用途：** 调度指令执行完后，把真实水位/流量/开度写回数据库，让模型评判系统知道"上次预测准不准"。

**Apifox 操作步骤：**
1. 方法选 **POST**
2. URL 填 `{{baseUrl}}/monitor/hydro-feedback`
3. **Params** 标签页：不填
4. **Headers** 标签页：点「+」添加一行，Key 填 `Authorization`，Value 填 `Bearer {{token}}`
5. **Body** 标签页：
   - 选 `JSON`
   - 粘贴下面内容（改成实际的决策 ID 和数值）：

```json
{
    "decision_id": 1,
    "actual_level": 180.5,
    "actual_flow": 345,
    "executed_opening": 40
}
```

**Body 参数说明：**

| 参数 | 类型 | 必填 | 填什么 | 示例 |
|------|------|:--:|------|------|
| decision_id | int | ✅ | 调度决策表中的 ID（`dispatch_decisions.id`） | 1 |
| actual_level | number | ✅ | 执行闸门动作后实际测到的上游水位（m） | 180.5 |
| actual_flow | number | ✅ | 执行后实际入库流量（m³/s） | 345 |
| executed_opening | number | ✅ | 实际执行的闸门开度（%） | 40 |

**返回示例：**
```json
{"code": 0, "msg": "回填成功", "data": null, "success": true}
```

**失败示例（填不存在的 decision_id）：**
```json
{"code": 10001, "msg": "参数错误", "data": null, "success": false}
```

---

## 变更接口

### 4. 水情检测 `POST /monitor/hydro-detect` 🔓

**变更点：** 加了可选参数 `reservoir_id`；返回加了 `auto_dispatch` 和 `reservoir` 字段。

**Apifox 操作步骤：**
1. 方法选 **POST**
2. URL 填 `{{baseUrl}}/monitor/hydro-detect`
3. **Params** 标签页：不填
4. **Headers** 标签页：不填（公开接口）
5. **Body** 标签页：选 `JSON`，粘贴：

```json
{
    "upstream_level": 180,
    "downstream_level": 118,
    "inflow": 350,
    "reservoir_id": 1,
    "rainfall": 5,
    "temperature": 22,
    "gate1_opening": 0.3,
    "gate2_opening": 0.2,
    "gate3_opening": 0.4
}
```

**Body 参数说明：**

| 参数 | 类型 | 必填 | 填什么 | 示例 |
|------|------|:--:|------|------|
| upstream_level | number | ✅ | 上游水位（m） | 180 |
| downstream_level | number | ✅ | 下游水位（m） | 118 |
| inflow | number | ✅ | 入库流量（m³/s） | 350 |
| **reservoir_id** | int | ❌ | 水库ID，传了用该水库专属阈值。1=三峡 2=溪洛渡 3=向家坝 4=示范 | 1 |
| rainfall | number | ❌ | 降雨量（mm/h），默认 0 | 5 |
| temperature | number | ❌ | 温度（℃），默认 20 | 22 |
| gate1_opening | number | ❌ | 闸门1开度（0~1），默认 0.3 | 0.3 |
| gate2_opening | number | ❌ | 闸门2开度（0~1），默认 0.2 | 0.2 |
| gate3_opening | number | ❌ | 闸门3开度（0~1），默认 0.4 | 0.4 |

**返回新增字段说明：**

| 新增字段 | 含义 | 示例 |
|------|------|------|
| `auto_dispatch` | `true`=可自动执行（L3_AUTO+置信度≥85%+安全）| `true` |
| `reservoir` | 传了 reservoir_id 时返回水库名，没传则 null | `{"id":1,"name":"三峡水库"}` |

**6. 点「发送」返回示例：**
```json
{
    "code": 0,
    "data": {
        "gate_openings": [40, 30, 50],
        "auto_dispatch": true,
        "reservoir": {"id": 1, "name": "三峡水库"},
        "models_used": {
            "lstm_prediction": {"name": "...", "version": "5.0.0"},
            "dqn_decision": {"name": "...", "version": "5.2.0"}
        }
    }
}
```

---

### 5. 用户列表 `GET /settings/users` 🔒 — 返回字段变更

**变更点：** 每条用户多了 4 个字段：`is_locked`、`lock_reason`、`lock_expire_time`、`login_fail_count`。

**Apifox 操作：** 和以前一样，GET + URL + Headers 加 `Authorization: Bearer {{token}}`，发送即可。

**返回新增的字段：**
```json
{
    "code": 0,
    "data": {
        "list": [{
            "id": 6,
            "account": "locked_user",
            "is_locked": true,
            "lock_expire_time": "2026-07-08 10:13:09",
            "lock_reason": "连续5次密码错误",
            "login_fail_count": 5
        }]
    }
}
```

---

### 6. 登录 `POST /login` 🔓 — 错误返回字段变更

**变更点：** 密码错误（code=20008）多了 `fail_count` + `remaining_attempts`；已锁定（code=20007）多了 `lock_remain_seconds` + `lock_expire_time`。

**Apifox 操作：** 和以前一样，POST + Body 填 `{"account":"xxx","password":"xxx"}`，Headers 不填。

**密码错误返回（code=20008）：**
```json
{
    "code": 20008,
    "msg": "账号或密码错误",
    "data": {
        "fail_count": 3,
        "remaining_attempts": 2
    },
    "success": false
}
```

**已锁定返回（code=20007）：**
```json
{
    "code": 20007,
    "msg": "账号已锁定，请稍后再试",
    "data": {
        "lock_remain_seconds": 1800,
        "lock_expire_time": "2026-07-08 10:13:09"
    },
    "success": false
}
```

---

### 7. 下发模型 `POST /settings/models/{id}/deploy` 🔑 — 校验和返回变更

**变更点：** 填不存在的节点 ID 会明确报哪个不存在；返回多了 `total`/`success`/`failed` 汇总。

**Apifox 操作：** 和以前一样，POST + URL 改 `{id}` + Headers 加 Token + Body 选 JSON。

**填非法节点的返回：**
```json
{"code": 10001, "msg": "边缘节点 ID 999 不存在，当前可用节点ID：1~8"}
```

**正常返回：**
```json
{
    "code": 0,
    "msg": "下发成功，共 3 个节点",
    "data": {
        "total": 3,
        "success": 3,
        "failed": 0,
        "details": [...]
    }
}
```

---

### 8. 创建用户 `POST /settings/users` 👑 — 校验变更

**变更点：** 账号不再要求字母数字下划线，密码不再要求字母+数字组合，只限制长度。

**Apifox 操作：** POST + Headers 加 `admin` 的 Token + Body 填 JSON。

**现在可以这样填：**
```json
{
    "account": "张调度",
    "password": "12345678",
    "realname": "张三",
    "role_id": 2
}
```

**校验规则：** `account` 3~50 字符，`password` ≥8 位，不限制字符类型。`realname`、`role_id` 必填，`phone`、`email` 可选。

---

### 9. 激活模型 `POST /settings/models/{id}/activate` 🔑 — 校验变更

**变更点：** Body 可以完全不传，`force` 和 `rollback_on_failure` 有默认值。

**Apifox 操作：** POST + URL 改 `{id}` + Headers 加 Token + Body 可以选 `none` 或 `JSON` 填 `{}`，直接点发送。

---

## 权限变更速查（已存在接口，只换 Token）

| 接口 | 旧权限 | 新权限 | `algorithm` token 调会怎样 |
|------|:--:|:--:|------|
| PUT 阈值/权重 | 🔒 | 🔑 | 正常，不变 |
| POST 模型上传/激活/回滚/下发 | 🔒 | 🔑 | 正常，不变 |
| DELETE 模型 | 🔒 | 🔑 | 正常，不变 |
| POST 创建用户 | 🔒 | 👑 | `20005 当前角色无此操作权限` |
| PUT 更新用户 | 🔒 | 👑 | `20005 当前角色无此操作权限` |
| 重置密码/锁定/解锁 | 🔒 | 👑 | `20005 当前角色无此操作权限` |
| DELETE 用户 | 🔒 | 👑 | `20005 当前角色无此操作权限` |
