# 模型上传指南（对齐总接口文档 8.3）

> 生成时间: 2026-07-03 09:37:40
> 目标: 将 Physics-Informed 模型注册到 Laravel 云端管理系统

## 第 1 步：获取 Token

先登录获取管理员 Token:
```
curl -X POST http://{CLOUD_HOST}/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"account":"admin","password":"xxxx"}'
```

返回的 `token` 字段在后续步骤中使用。

## 第 2 步：上传 LSTM 预测模型

```
curl -X POST http://{CLOUD_HOST}/api/settings/models/upload \
  -H "Authorization: Bearer {TOKEN}" \
  -F "file=@lstm_physics_v5.0.pt" \
  -F "name=Physics-Informed LSTM v5.0" \
  -F "version=5.0.0" \
  -F "type=lstm_prediction" \
  -F "framework=pytorch" \
  -F "accuracy=99.993" \
  -F "description=水位MAE=0.067m 流量MAE=48.3m3/s 物理损失=0.00016 2000轮SWA"
```

返回示例: `{"code":0, "data":{"id": 1}, "success":true}`

## 第 3 步：上传 DQN 决策模型

```
curl -X POST http://{CLOUD_HOST}/api/settings/models/upload \
  -H "Authorization: Bearer {TOKEN}" \
  -F "file=@dqn_physics_v5.0.pth" \
  -F "name=Physics-Informed DQN v5.0" \
  -F "version=5.0.0" \
  -F "type=dqn_decision" \
  -F "framework=pytorch" \
  -F "accuracy=85.4" \
  -F "description=Best=81.7 稳定性=0.96 4场景 影子水位奖励"
```

## 第 4 步：激活模型

```
# 激活 LSTM (替换 {LSTM_ID} 为上一步返回的 id)
curl -X POST http://{CLOUD_HOST}/api/settings/models/{LSTM_ID}/activate \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{"force":true}'

# 激活 DQN
curl -X POST http://{CLOUD_HOST}/api/settings/models/{DQN_ID}/activate \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{"force":true}'
```

## 第 5 步（可选）：下发到边缘节点

```
curl -X POST http://{CLOUD_HOST}/api/settings/models/{MODEL_ID}/deploy \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{"edge_node_ids":[1],"strategy":"immediate"}'
```

## 模型信息速查

| | LSTM | DQN |
|---|---|---|
| 版本 | 5.0.0 | 5.0.0 |
| 类型 | lstm_prediction | dqn_decision |
| 框架 | PyTorch (.pt) | PyTorch (.pth) |
| 大小 | 1843 KB | 1122 KB |
| 参数量 | 470,124 | 284,798 |
| 精度/得分 | MAE 0.067m | Avg100 81.7 |
| MD5 | c199c2740f48ac19... | be40b6b15387da97... |
