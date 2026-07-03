"""
模型导出打包工具（对齐接口文档 8.3.2/8.3.6）
============================================
把训练好的模型打包为云端可直接上传的格式。

用法:
  python model_package.py                          # 导出所有模型
  python model_package.py --lstm-only              # 只导出 LSTM
  python model_package.py --dqn-only               # 只导出 DQN
  python model_package.py --output ./release       # 指定输出目录

产出:
  release/
  ├── lstm_physics_v5.0.pt          ← LSTM 模型文件
  ├── lstm_metadata.json             ← LSTM 元数据（上传时填表单用）
  ├── dqn_physics_v5.0.pth           ← DQN 模型文件
  ├── dqn_metadata.json              ← DQN 元数据
  ├── scaler_X.pkl                   ← 归一化器
  ├── deploy_config.json             ← 部署配置
  └── UPLOAD_GUIDE.md                ← 上传步骤说明
"""

import os, sys, json, shutil, hashlib
from datetime import datetime

BASE_DIR = os.path.dirname(os.path.abspath(__file__))


def md5(filepath: str) -> str:
    with open(filepath, "rb") as f:
        return hashlib.md5(f.read()).hexdigest()


def create_package(output_dir: str = None, lstm_only: bool = False, dqn_only: bool = False):
    if output_dir is None:
        output_dir = os.path.join(BASE_DIR, "release")
    os.makedirs(output_dir, exist_ok=True)

    do_lstm = not dqn_only
    do_dqn = not lstm_only

    packages = []

    # ===== LSTM 模型 =====
    if do_lstm:
        lstm_src = os.path.join(BASE_DIR, "models", "lstm_state_dict.pt")
        lstm_dst = os.path.join(output_dir, "lstm_physics_v5.0.pt")
        shutil.copy2(lstm_src, lstm_dst)
        lstm_md5 = md5(lstm_dst)
        lstm_size = os.path.getsize(lstm_dst)

        lstm_meta = {
            "file": "lstm_physics_v5.0.pt",
            "name": "Physics-Informed LSTM v5.0",
            "version": "5.0.0",
            "type": "lstm_prediction",
            "framework": "pytorch",
            "description": "2层BiLSTM(96) + MultiheadAttention(4头) + 水量平衡物理约束。在线数据生成, 2000轮训练, SWA 80快照平均。水位MAE=0.067m, 流量MAE=48.3m3/s, 物理损失=0.00016",
            "accuracy": 99.993,
            "training_date": "2026-07-02",
            "size_mb": round(lstm_size / 1024 / 1024, 2),
            "md5": lstm_md5,
            "params": {
                "input_features": 5,
                "output_features": 2,
                "seq_length": 24,
                "pred_horizon": 6,
                "hidden_size": 96,
                "num_layers": 2,
                "dropout": 0.4,
                "param_count": 470124,
            },
            "training": {
                "epochs": 2000,
                "best_val_loss": 0.007573,
                "final_test_loss": 0.004942,
                "level_mae_m": 0.067,
                "flow_mae_m3s": 48.3,
                "physics_loss": 0.000160,
                "swa_snapshots": 80,
                "total_sequences": "~3,000,000",
                "augmentation": "online_generation + gaussian_noise(0.015)",
            },
        }
        with open(os.path.join(output_dir, "lstm_metadata.json"), "w", encoding="utf-8") as f:
            json.dump(lstm_meta, f, indent=2, ensure_ascii=False)
        packages.append(("LSTM", lstm_dst, lstm_meta))
        print(f"[LSTM] {lstm_dst} ({lstm_size/1024:.0f} KB, MD5={lstm_md5[:12]}...)")

    # ===== DQN 模型 =====
    if do_dqn:
        dqn_src = os.path.join(BASE_DIR, "models", "dqn_model.pth")
        dqn_dst = os.path.join(output_dir, "dqn_physics_v5.0.pth")
        shutil.copy2(dqn_src, dqn_dst)
        dqn_md5 = md5(dqn_dst)
        dqn_size = os.path.getsize(dqn_dst)

        dqn_meta = {
            "file": "dqn_physics_v5.0.pth",
            "name": "Physics-Informed DQN v5.0",
            "version": "5.0.0",
            "type": "dqn_decision",
            "framework": "pytorch",
            "description": "Dueling Double DQN + 软更新 + 影子水位奖励塑形。4000轮4场景(normal/flood/drought/storm)训练。Best=81.7, 稳定性=0.96, 12维增强状态, LayerNorm, 零初始化Q值",
            "accuracy": 85.4,
            "training_date": "2026-07-02",
            "size_mb": round(dqn_size / 1024 / 1024, 2),
            "md5": dqn_md5,
            "params": {
                "state_dim": 12,
                "action_dim": 125,
                "num_gates": 3,
                "action_bins": 5,
                "hidden_size": 256,
                "num_layers": 4,
                "param_count": 284798,
            },
            "training": {
                "episodes": 4000,
                "best_avg100": 81.7,
                "final_avg100": 78.5,
                "stability_ratio": 0.96,
                "scenarios": {
                    "normal": {"eps": 1181, "last20_avg": 84.6},
                    "flood": {"eps": 1019, "last20_avg": 73.5},
                    "drought": {"eps": 1009, "last20_avg": 84.0},
                    "storm": {"eps": 791, "last20_avg": 71.7},
                },
                "algorithm": "Dueling_Double_DQN",
                "features": ["soft_update(tau=0.003)", "reward_clip[-50,50]", "zero_init_Q", "physics_reward_shaping"],
            },
        }
        with open(os.path.join(output_dir, "dqn_metadata.json"), "w", encoding="utf-8") as f:
            json.dump(dqn_meta, f, indent=2, ensure_ascii=False)
        packages.append(("DQN", dqn_dst, dqn_meta))
        print(f"[DQN]  {dqn_dst} ({dqn_size/1024:.0f} KB, MD5={dqn_md5[:12]}...)")

    # ===== Scaler & Config =====
    shutil.copy2(
        os.path.join(BASE_DIR, "models", "scaler_X.pkl"),
        os.path.join(output_dir, "scaler_X.pkl"),
    )
    shutil.copy2(
        os.path.join(BASE_DIR, "deploy_config.json"),
        os.path.join(output_dir, "deploy_config.json"),
    )

    # ===== 上传指南 =====
    guide = f"""# 模型上传指南（对齐总接口文档 8.3）

> 生成时间: {datetime.now().strftime("%Y-%m-%d %H:%M:%S")}
> 目标: 将 Physics-Informed 模型注册到 Laravel 云端管理系统

## 第 1 步：获取 Token

先登录获取管理员 Token:
```
curl -X POST http://{{CLOUD_HOST}}/api/auth/login \\
  -H "Content-Type: application/json" \\
  -d '{{"account":"admin","password":"xxxx"}}'
```

返回的 `token` 字段在后续步骤中使用。

## 第 2 步：上传 LSTM 预测模型

```
curl -X POST http://{{CLOUD_HOST}}/api/settings/models/upload \\
  -H "Authorization: Bearer {{TOKEN}}" \\
  -F "file=@lstm_physics_v5.0.pt" \\
  -F "name=Physics-Informed LSTM v5.0" \\
  -F "version=5.0.0" \\
  -F "type=lstm_prediction" \\
  -F "framework=pytorch" \\
  -F "accuracy=99.993" \\
  -F "description=水位MAE=0.067m 流量MAE=48.3m3/s 物理损失=0.00016 2000轮SWA"
```

返回示例: `{{"code":0, "data":{{"id": 1}}, "success":true}}`

## 第 3 步：上传 DQN 决策模型

```
curl -X POST http://{{CLOUD_HOST}}/api/settings/models/upload \\
  -H "Authorization: Bearer {{TOKEN}}" \\
  -F "file=@dqn_physics_v5.0.pth" \\
  -F "name=Physics-Informed DQN v5.0" \\
  -F "version=5.0.0" \\
  -F "type=dqn_decision" \\
  -F "framework=pytorch" \\
  -F "accuracy=85.4" \\
  -F "description=Best=81.7 稳定性=0.96 4场景 影子水位奖励"
```

## 第 4 步：激活模型

```
# 激活 LSTM (替换 {{LSTM_ID}} 为上一步返回的 id)
curl -X POST http://{{CLOUD_HOST}}/api/settings/models/{{LSTM_ID}}/activate \\
  -H "Authorization: Bearer {{TOKEN}}" \\
  -H "Content-Type: application/json" \\
  -d '{{"force":true}}'

# 激活 DQN
curl -X POST http://{{CLOUD_HOST}}/api/settings/models/{{DQN_ID}}/activate \\
  -H "Authorization: Bearer {{TOKEN}}" \\
  -H "Content-Type: application/json" \\
  -d '{{"force":true}}'
```

## 第 5 步（可选）：下发到边缘节点

```
curl -X POST http://{{CLOUD_HOST}}/api/settings/models/{{MODEL_ID}}/deploy \\
  -H "Authorization: Bearer {{TOKEN}}" \\
  -H "Content-Type: application/json" \\
  -d '{{"edge_node_ids":[1],"strategy":"immediate"}}'
```

## 模型信息速查

| | LSTM | DQN |
|---|---|---|
| 版本 | 5.0.0 | 5.0.0 |
| 类型 | lstm_prediction | dqn_decision |
| 框架 | PyTorch (.pt) | PyTorch (.pth) |
| 大小 | {lstm_size/1024:.0f} KB | {dqn_size/1024:.0f} KB |
| 参数量 | 470,124 | 284,798 |
| 精度/得分 | MAE 0.067m | Avg100 81.7 |
| MD5 | {lstm_md5[:16]}... | {dqn_md5[:16]}... |
"""

    guide = guide.replace("lstm_size", str(lstm_size)).replace("dqn_size", str(dqn_size))
    guide = guide.replace("lstm_md5", lstm_md5).replace("dqn_md5", dqn_md5)
    with open(os.path.join(output_dir, "UPLOAD_GUIDE.md"), "w", encoding="utf-8") as f:
        f.write(guide)

    print(f"\n[OK] Package ready: {output_dir}/")
    print(f"     ├── lstm_physics_v5.0.pt")
    print(f"     ├── lstm_metadata.json")
    print(f"     ├── dqn_physics_v5.0.pth")
    print(f"     ├── dqn_metadata.json")
    print(f"     ├── scaler_X.pkl")
    print(f"     ├── deploy_config.json")
    print(f"     └── UPLOAD_GUIDE.md")
    print(f"\n  Next: 打开 UPLOAD_GUIDE.md 按步骤上传")


if __name__ == "__main__":
    import argparse
    p = argparse.ArgumentParser(description="Model Export Tool")
    p.add_argument("--output", "-o", help="输出目录")
    p.add_argument("--lstm-only", action="store_true")
    p.add_argument("--dqn-only", action="store_true")
    args = p.parse_args()
    create_package(
        output_dir=args.output,
        lstm_only=args.lstm_only,
        dqn_only=args.dqn_only,
    )
