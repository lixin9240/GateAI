# Jetson Orin Nano 烧录指南 — VMware 方式（超详细版）

> 本文档假设你对虚拟机完全不了解。每一步都有截图级描述，跟着点就行。

---

## 前言：回答你可能的疑问

**Q: 为什么要装虚拟机？**
SDK Manager（Jetson 烧录工具）只能在 Ubuntu 上运行。你电脑是 Windows，不想再买一台 Ubuntu 电脑，所以在你 Windows 里用 VMware 套一个假的 Ubuntu。

**Q: 会搞坏我电脑吗？**
不会。虚拟机 = 一个大文件。删掉文件 = 什么都没发生过。不分区、不改启动项、不影响 Windows。

**Q: 什么是 Ubuntu？**
Ubuntu 是另一种操作系统，和你电脑上的 Windows 是同类东西。只不过 Windows 是微软做的，Ubuntu 是开源社区做的。

**Q: 什么又是 Jetson？**
Jetson 是一台巴掌大的小电脑（NVIDIA 出的），专门跑 AI。它出厂没有系统，你要给它装 Ubuntu。本文就是教你怎么装。

**Q: 我现在没 Jetson，可以先装虚拟机吗？**
可以。第 1-4 步不依赖 Jetson，随时能做。第 5 步需要 Jetson 到手才能继续。

---

## 准备清单

| 你需要的东西 | 哪里来 |
|-------------|--------|
| 一台 Windows 电脑 | 你现在用的这台 |
| 硬盘空闲 100GB+ | 装虚拟机 + 下载的东西 |
| 内存 ≥ 16GB（8GB 也能跑但慢） | 你电脑现成的 |
| NVIDIA 开发者账号 | 免费注册，第 4 步用到 |
| **以下 Jetson 到手后才需要:** | |
| Jetson Orin Nano | 买的 |
| USB-C 数据线（能传数据的，不能是纯充电线） | 一般买 Jetson 会送，没有就京东买一根 |
| 跳线帽 | Jetson 套件自带 |
| 键盘鼠标 + 显示器（或触摸屏） | Jetson 首次开机用 |

---

# 第一部分：装虚拟机（不需要 Jetson）

---

## 第 1 步：下载 VMware Workstation

1. 打开浏览器，访问: `https://www.vmware.com/products/workstation-pro.html`
2. 找到 **Workstation Pro 17 for Windows**，点下载
3. 如果提示注册/登录，用邮箱注册一个 VMware 账号（免费）
4. 下载完是一个 `.exe` 文件，比如 `VMware-workstation-full-17.x.x.exe`，约 600MB

**安装：**

1. 双击 `.exe` 文件
2. 出现安装向导 → 点「下一步」
3. 勾选「我接受许可协议」→ 点「下一步」
4. 安装位置不用改 → 点「下一步」
5. 两个勾都去掉（用户体验设置、加入客户体验计划）→ 点「下一步」
6. 点「下一步」→ 点「安装」
7. 等进度条走完 → 点「完成」
8. 桌面出现 VMware Workstation 图标

---

## 第 2 步：下载 Ubuntu 22.04 镜像

1. 打开浏览器，访问: `https://releases.ubuntu.com/jammy/`
2. 找到 `ubuntu-22.04.4-desktop-amd64.iso`
3. 点它，开始下载（约 4.7GB，看网速，可能要 10-30 分钟）

> ⚠️ 必须是 **22.04**，不能是 24.04。SDK Manager 目前不兼容 24.04。

---

## 第 3 步：创建 Ubuntu 虚拟机

### 3.1 启动 VMware

双击桌面 VMware 图标，出现主界面。

### 3.2 新建虚拟机

点主界面中上位置的 **「创建新的虚拟机」** 按钮。

### 3.3 选择配置类型

看到两个选项:
```
● 典型(推荐)
○ 自定义(高级)
```
选上面的 **「典型(推荐)」** → 点「下一步」

### 3.4 选择安装来源

看到三个选项:
```
● 安装程序光盘(D): [无]
○ 安装程序光盘映像文件(iso):
○ 稍后安装操作系统
```
选中间的 **「安装程序光盘映像文件(iso)」** → 点「浏览」→ 找到你第 2 步下载的那个 `ubuntu-22.04.4-desktop-amd64.iso` → 点「下一步」

### 3.5 选择操作系统类型

这就是你之前问的那一步:

```
客户机操作系统:
  ● Linux                    ← 点这里

版本(V):
  Ubuntu 64-bit              ← 下拉框选这个
```

点「下一步」

### 3.6 命名虚拟机

```
虚拟机名称(V): Ubuntu-Jetson
位置(L): D:\VMware\Ubuntu-Jetson
```
位置可以改到 D 盘（保证 D 盘有 100GB 以上空闲）。点「下一步」

### 3.7 设置磁盘大小

```
最大磁盘大小(GB): 80
```
改成 **80**。

下面两个选项:
```
● 将虚拟磁盘存储为单个文件   ← 选这个
○ 将虚拟磁盘拆分成多个文件
```
点「下一步」

### 3.8 自定义硬件

点 **「自定义硬件(C)」** 按钮，弹出硬件设置窗口:

**内存:** 左边点「内存」→ 右边把滑块拉到 **8192 MB (8GB)**
> 你电脑 ≥ 16GB 内存才能设 8GB。如果只有 8GB 内存，虚拟机设 4GB。

**处理器:** 左边点「处理器」→ 核心数改成 **4**
> 你电脑几核？不知道的话不改也行。

**网络适配器:** 左边看「网络适配器」→ 右边确保「启动时连接」是勾上的。默认「NAT 模式」不用改。

其他不用动。点「关闭」→ 点「完成」。

### 3.9 虚拟机自动启动

回到 VMware 主界面，左边列表出现 `Ubuntu-Jetson`。它会自动启动，进入 Ubuntu 安装画面。

---

## 第 4 步：在虚拟机里安装 Ubuntu

### 4.1 选择语言

虚拟机屏幕出现 Ubuntu 安装界面:
- 左边选 **中文(简体)** 或 **English**（建议英文避免乱码）
- 右边点 **「Install Ubuntu」**

### 4.2 键盘布局

默认 `Chinese` 或 `English (US)` → 点 **Continue**

### 4.3 更新选项

选 **「Normal installation」**（正常安装）
下面两个勾:
```
☑ Download updates while installing Ubuntu    ← 勾上
☑ Install third-party software...             ← 勾上
```
点 **Continue**

### 4.4 磁盘分区

选 **「Erase disk and install Ubuntu」**（别怕，这里擦的是虚拟机里那 80GB 假硬盘，不是你 Windows 的真硬盘）

点 **「Install Now」** → 弹出确认框点 **Continue**

### 4.5 时区

地图上点中国的位置（或直接输入 Shanghai）→ 点 **Continue**

### 4.6 创建用户

```
Your name:               hydropower
Your computer's name:    jetson-vm
Pick a username:         hydropower
Choose a password:       设一个简单密码, 比如 123456
Confirm your password:   再输一遍
```
下面选项选 **「Log in automatically」**（自动登录，省事）

点 **Continue**

### 4.7 等待安装

进度条走完约 10-15 分钟。去喝杯水。

### 4.8 安装完成

看到 **「Installation Complete」** → 点 **「Restart Now」**

可能出现一行小字 `Remove the installation medium and press Enter`，直接按回车就行。

重启后自动登录，**Ubuntu 桌面出现了**。

---

## 第 5 步：在 Ubuntu 里装 SDK Manager

### 5.1 打开终端

Ubuntu 桌面右下角有个 **「Show Applications」**（九个点图标），点开 → 搜 **Terminal** → 点开。

或者直接快捷键 `Ctrl + Alt + T`

### 5.2 更新系统

在终端里（黑窗口）输入以下命令，每行输完按回车:

```bash
sudo apt update
```

会提示输密码，输你设的那个（123456），输的时候屏幕上不显示任何东西，这是正常的，输完回车。

```bash
sudo apt upgrade -y
```

等它跑完，几分钟。

### 5.3 安装必要工具

```bash
sudo apt install -y python3-pip ssh curl
```

### 5.4 下载 SDK Manager

打开 Ubuntu 里的 Firefox 浏览器，访问:

`https://developer.nvidia.com/sdk-manager`

1. 点 **Download .deb** （Ubuntu 版本的）
2. 会要求登录 NVIDIA 账号。没注册过就点注册，用邮箱注册一个，免费的。
3. 登录后自动开始下载。

### 5.5 安装 SDK Manager

下载完成后的 `.deb` 文件在 `~/Downloads/` 目录。

回到终端:

```bash
cd ~/Downloads
sudo dpkg -i sdkmanager*.deb
```

大概率会报一堆错，正常的。接着输入:

```bash
sudo apt install -f -y
```

这条命令会把缺的东西自动装上。然后:

```bash
sudo dpkg -i sdkmanager*.deb
```

这次应该成功了。

### 5.6 启动 SDK Manager

```bash
sdkmanager
```

SDK Manager 窗口出现了。**虚拟机这边准备就绪。**

---

# 第二部分：给 Jetson 烧录系统（Jetson 到手后）

---

## 第 6 步：Jetson 进入恢复模式

### 6.1 准备工作

拿出 Jetson 板子，你会看到一排排针脚（40 个金属针）。找到标有 **FC REC** 和 **GND** 的两个针脚。

套件自带的盒子/袋子里有一个小小的塑料帽（跳线帽），把它套在 FC REC 和 GND 这两个针上，让它们短接。

### 6.2 连接和上电

操作顺序很重要，必须按这个来:

```
① Jetson 断电状态（电源线拔掉）
② 跳线帽套在 FC REC 和 GND 上
③ USB-C 数据线一头插 Jetson USB-C 口，另一头插你 PC 的 USB 口
④ 插上 Jetson 电源
```
Jetson 风扇可能转可能不转，屏幕黑的是正常的——这就是恢复模式。

### 6.3 VMware 里连接 Jetson

回到 Windows，在 VMware 主界面右下角能看到一排小图标。找到一个 USB 相关的按钮（或者菜单点 **虚拟机 → 可移动设备 → NVIDIA Corp. APX → 连接(断开与主机的连接)**）。

验证连接: 在 Ubuntu 虚拟机终端里:
```bash
lsusb | grep NVIDIA
```
如果有输出 `NVIDIA Corp. APX`，说明连上了。没输出就重新插拔 USB-C，再试一次 VMware 连接。

---

## 第 7 步：SDK Manager 烧录

### 7.1 登录

SDK Manager 启动后第一步要求登录，用你注册的 NVIDIA 账号登录。

### 7.2 STEP 1 — 选择目标硬件

界面有两个 Tab:

```
Target Hardware:
  ☐ Host Machine              ← 取消勾选！
  ☑ Jetson Orin Nano 8GB      ← 勾上这个
```

> ⚠️ 一定取消 Host Machine，否则会在你虚拟机里也装一套 CUDA，完全不需要。

### 7.3 STEP 2 — 选择组件

JetPack 版本选 **JetPack 6.0**。

下面是一堆组件列表，确保勾选:

```
☑ Jetson Linux (Ubuntu 22.04)
☑ CUDA Toolkit
☑ cuDNN
☑ TensorRT
☑ OpenCV
☑ PyTorch                    ← 必须勾上
```

其他没提到的可以不勾。点 **Continue**。

### 7.4 STEP 3 — 配置

SDK Manager 会自动检测到处于恢复模式的 Jetson。

创建 Jetson 的用户名和密码:

```
Username: hydropower
Password: 设个密码, 比如 hydropower123
```

**记下来！这是 Jetson 开机后的登录密码。**

### 7.5 STEP 4 — 开始烧录

点 **Flash**，开始烧录。

进度条分两个阶段:
1. 烧录系统 (Flash) — 约 10-15 分钟
2. 安装组件 (Install SDK Components) — 约 20-40 分钟（取决于网速，SDK Manager 从 NVIDIA 服务器下载 PyTorch 等包）

总共约 30-60 分钟。中间可能需要输一次 Ubuntu 密码（你虚拟机的密码，不是 Jetson 的）。

### 7.6 烧录完成

看到 `Installation completed successfully` 就是好了。

---

## 第 8 步：Jetson 首次开机

### 8.1 断开连接

1. 拔掉 Jetson 电源
2. 拔掉 USB-C 数据线
3. **拔掉 FC REC 的跳线帽**（必须拔！不拔下次开机又进恢复模式）

### 8.2 开机

1. 插上 Jetson 电源
2. 插上显示器（用 HDMI 或 DP）或触摸屏
3. 插上键盘鼠标
4. Jetson 自动开机

等约 1-2 分钟，屏幕上出现 Ubuntu 22.04 桌面。用第 7.4 步设的用户名 `hydropower` 和密码登录。

### 8.3 验证 CUDA

登录后打开终端:

```bash
python3 -c "import torch; print(torch.cuda.is_available())"
```

输出 `True` → 一切正常！🎉

---

## 第 9 步：部署 AI 推理服务

Jetson 系统装好了，但没有部署包。把部署包拷贝上去:

```bash
# 1. 安装推理依赖
sudo apt install -y python3-pip libmysqlclient-dev
pip3 install numpy scikit-learn joblib mysqlclient minimalmodbus flask flask-cors

# 2. 拷贝部署包（U盘 或 scp）
# U盘方式:
sudo mkdir -p /opt/hydropower
sudo cp -r /media/hydropower/USB/hydropower_deploy/* /opt/hydropower/

# 3. 测试
cd /opt/hydropower
python3 api_server.py --port 5000

# 看到 "Listening on http://0.0.0.0:5000" 就是成功了
```

---

## 常见问题

**Q: VMware 里 Ubuntu 特别卡**
把 VMware 关了，编辑虚拟机设置，内存拉到 8GB，CPU 核心拉到 4。你电脑够的话。

**Q: SDK Manager 检测不到 Jetson**
1. Jetson 没进恢复模式（检查跳线帽是否套在 FC REC 和 GND 上，顺序是否对）
2. USB-C 线只能充电不能传数据（换一根）
3. VMware 没把 USB 设备连到虚拟机（菜单 → 可移动设备 → NVIDIA APX → 连接）

**Q: 烧录过程中网络出错**
NVIDIA 服务器在国外，下载约 8GB 可能很慢。如果失败，关了重新来一次，SDK Manager 会断点续传。

**Q: 不想用 VMware 了怎么办**
VMware 卸载，D 盘 `VMware` 文件夹删除。干干净净。

**Q: 可以跳过虚拟机，直接 SD 卡装吗**
可以。从 NVIDIA 官网下载 JetPack 镜像 → Windows 上用 BalenaEtcher 刷进 SD 卡 → SD 卡插 Jetson → 开机。比本文方式更简单，不用虚拟机。但 SD 卡要另外买（64GB, ¥30）。

---

## 步骤总览

```
阶段一: 准备虚拟机（不需要 Jetson）
  ① 装 VMware → ② 下载 Ubuntu ISO → ③ 创建虚拟机 → ④ 装 Ubuntu → ⑤ 装 SDK Manager

阶段二: 烧录 Jetson（Jetson 到手后）
  ⑥ Jetson 进恢复模式 → ⑦ SDK Manager 烧录 → ⑧ Jetson 首次开机 → ⑨ 部署推理服务
```
