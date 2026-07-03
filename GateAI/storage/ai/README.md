# 水电站 AI 推理服务 — 使用说明

> **这个文件夹就是部署包。你只需要做 3 件事。**

---

## 第 1 步：安装依赖（就这一次）

打开终端，进入这个文件夹，输一行命令：

```powershell
cd D:\hydropower_deploy
pip install -r requirements.txt
```

等它跑完，一分钟左右。

---

## 第 2 步：启动服务

```powershell
python api_server.py --port 5000
```

看到下面这行就说明成功了：

```
Listening on http://0.0.0.0:5000
```

**不要关这个窗口**，让它一直跑着。

---

## 第 3 步：验证一下

新开一个终端：

```powershell
curl http://localhost:5000/api/health
```

返回 `{"status":"ok",...}` 就是好了。

---

## 然后在 Laravel 里用

`.env` 加一行：

```env
AI_INFERENCE_URL=http://localhost:5000
```

Controller 里调用：

```php
$response = Http::post('http://localhost:5000/api/infer', [
    'upstream_level'   => 182.0,
    'downstream_level' => 120.5,
    'inflow'           => 250.0,
    'rainfall'         => 3.0,
    'temperature'      => 22.0,
    'gate1_opening'    => 0.3,
    'gate2_opening'    => 0.2,
    'gate3_opening'    => 0.4,
]);

$result = $response->json();
// $result['data']['gate_openings']  →  [100, 100, 50]   ← 闸门建议开度
// $result['data']['safety_flag']    →  "safe"
// $result['data']['confidence']     →  1.0
```

---

## 文件夹里都有啥

| 文件 | 要不要管 |
|------|:--:|
| `api_server.py` | 启动这个 |
| `requirements.txt` | 第 1 步安装依赖用 |
| `models/` | 3 个模型文件，不用动 |
| `API_接口文档_给组长.md` | 详细接口文档 |
| `Laravel集成指南_给组长.md` | Laravel 集成代码（Service 类） |
| 其他文件 | 不用管 |

---

## 常见问题

**Q: 报错 `No module named 'flask'`**

```powershell
pip install flask flask-cors
```

**Q: 端口 5000 被占了**

换一个：

```powershell
python api_server.py --port 5001
```

然后 Laravel `.env` 也跟着改：

```env
AI_INFERENCE_URL=http://localhost:5001
```

**Q: 没有 GPU 能跑吗**

能。推理用 CPU 也只要几毫秒，不需要显卡。

**Q: 关了终端服务就停了**

Windows 上可以后台运行：

```powershell
start /B python api_server.py --port 5000
```
