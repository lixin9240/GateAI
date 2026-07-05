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

**Q: 不想折腾虚拟机，有更简单的办法吗？**
有。看下方 **SD 卡方式**——全程在 Windows 上操作，30 分钟搞定，无需任何 Linux 知识。

---

## 选择哪种方式？

| | **SD 卡方式（推荐）** | VMware 方式 |
|------|:--:|:--:|
| 需要装虚拟机吗 | ❌ | ✅ |
| 需要 Ubuntu | ❌ 不需要 | ✅ 需要 |
| 硬盘占用 | ~15GB | ~80GB |
| 额外硬件 | 64GB SD 卡 + 读卡器（¥40） | 不需要 |
| 操作难度 | ⭐ 极低 | ⭐⭐⭐ |
| 耗时 | 20-30 分钟 | 1-2 小时 |
| 适合谁 | **新手 / 只想快速上手** | 团队统一管理多台 Jetson |

> 如果你只是给一台 Jetson 装系统开发用，选 SD 卡方式。

---

# 方式一：SD 卡烧录（推荐新手，全程 Windows 操作）

> 不需要装虚拟机、不需要学 Linux、不需要敲命令行（除了最后部署那一步）。全程在 Windows 上点鼠标搞定，30 分钟内 Jetson 就能用。

---

## 准备清单（SD 卡方式）

| 你需要的东西 | 哪里来 | 大概多少钱 |
|-------------|--------|:--:|
| Windows 电脑 | 你现在的这台 | — |
| 64GB 以上 microSD 卡 | 京东/淘宝，搜 "microSD 64GB U3"，推荐闪迪/三星 | ¥30-50 |
| 读卡器 | 买 SD 卡一般会送。电脑有 SD 卡槽就不用 | ¥0-10 |
| Jetson Orin Nano | NVIDIA 官方或代理商 | — |
| 键盘 + 鼠标 + 显示器 | 家里现成的 | — |
| NVIDIA 开发者账号 | 免费注册 | 免费 |

**SD 卡怎么选？** microSD 卡就是手机上用的那种 TF 卡。容量 ≥ 64GB，速度等级写 U3 或 V30。闪迪红灰卡（¥35）就够用了，不需要买贵的。

**读卡器是干什么的？** 把 TF 卡插进去，USB 那端插电脑，电脑就能读写 TF 卡了。笔记本电脑侧面如果有 SD 大卡槽，你需要一个 TF 转 SD 的卡套（买 TF 卡一般附带）。台式机一般没卡槽，买个 USB 读卡器（¥10）。

---

## 第 1 步：注册 NVIDIA 开发者账号

1. 打开浏览器，访问: `https://developer.nvidia.com/`

2. 页面右上角，点那个小人图标（或者点 **「Join」** / **「Login」**）

3. 如果已经有账号，直接登录看第 2 步。没有的话，点 **「Create Account」** 注册。

4. 注册页填的内容：
   ```
   Email: 你的邮箱
   First Name: 名
   Last Name: 姓
   Password: 设一个密码（8位以上，含大小写字母+数字）
   Country: China
   ```
   下面有几个勾选框，意思是"要不要收 NVIDIA 的推广邮件"，全部取消勾选，点 **「Create Account」**。

5. NVIDIA 会往你邮箱发一封验证邮件。打开邮箱，找到那封邮件，点里面的 **「Verify Email」** 链接。没收到的话检查垃圾邮件箱。

6. 验证完后回到 NVIDIA 网站，显示已登录。

---

## 第 2 步：下载 JetPack SD 卡镜像

1. 在浏览器地址栏输入: `https://developer.nvidia.com/embedded/jetson-orin-nano`

2. 页面往下翻，你会看到一堆 Tab 和链接。找到标题 **"SD Card Image"** 的区域。

   > 页面上还有一个叫 "SDK Manager" 的下载按钮——**别点那个，那是给 VMware 方式用的**。我们要的是 SD Card Image。

3. 在 SD Card Image 区域，找到 **JetPack 6.0**，点下载。

4. 如果弹出协议条款（License Agreement），右下角勾 **「I Agree」**，然后点下载。

5. 浏览器底部会出现下载进度。文件名类似:
   ```
   jp60-jetson-orin-nano-sd-card-image.zip
   ```
   大小约 **10-15GB**。看你的网速，可能需要 20 分钟到 1 小时。

   > 下载期间可以去做别的事，但别关浏览器，也别让电脑睡眠。

6. 下载完成。确认文件大小对了（右键 → 属性 → 大小应该接近 15,000,000,000 字节左右）。注意：**不用解压这个 zip 文件**，后面工具直接读 zip。

---

## 第 3 步：下载 BalenaEtcher 烧录工具

1. 打开浏览器，访问: `https://etcher.balena.io/`

2. 页面中间有一个大大的 **「Download」** 按钮，下面写着 "for Windows (x64)"。点它。

3. 下载的文件叫 `balenaEtcher-Setup-x.x.x.exe`，约 150MB。

4. 下载完，双击 `.exe` 文件，开始安装向导：
   - 第一步：接受许可协议 → 点「Next」
   - 第二步：安装位置不用改 → 点「Install」
   - 等进度条走完 → 点「Finish」
   
5. 桌面出现 **BalenaEtcher** 的蓝色图标（像一团火焰）。双击打开。

> **这软件是干什么的？** 你把 SD 卡插电脑上，Etcher 会把 Jetson 系统镜像（那个 15GB 的大 zip）里面包含的操作系统、CUDA、PyTorch 等所有东西原封不动写到 SD 卡里。写完之后，SD 卡就是一个完整的 Jetson 启动盘——插进 Jetson 开机就能用。

---

## 第 4 步：烧录镜像到 SD 卡

### 4.1 插入 SD 卡

把 TF 卡（microSD）插进读卡器，读卡器插电脑 USB 口。

插上后，Windows 可能会弹出一个窗口显示"此驱动器需要格式化"——**不要格式化！点取消关掉！** 这是因为 U 盘/SD 卡出厂时用的文件系统 Windows 读不懂，不格式化也没事，Etcher 会全部覆盖掉。

### 4.2 打开 Etcher

桌面双击 BalenaEtcher 图标。界面分三块：

```
┌──────────────────────────────────────────┐
│                                          │
│          [    Flash from file    ]       │ ← 第一步：选镜像
│                                          │
│          [    Select target      ]       │ ← 第二步：选 SD 卡
│                                          │
│          [       Flash!          ]       │ ← 第三步：开始烧录
│                                          │
└──────────────────────────────────────────┘
```

### 4.3 第一步：选择镜像文件

点第一个按钮 **「Flash from file」**。

弹出文件选择窗口，找到你第 2 步下载的那个 `.zip` 文件（比如在 `下载` 或 `Downloads` 文件夹）。

> **不用解压 zip！** 直接选 `.zip` 文件，Etcher 会自动处理。

选中后，Etcher 会读两秒，然后第一个按钮上显示文件名。

### 4.4 第二步：选择 SD 卡

点第二个按钮 **「Select target」**。

弹出设备列表，你会看到类似这样的列表:

```
● Generic MassStorageClass USB Device (64.0 GB) ─ D:\\
○ ST1000DM010-2EP102 (931.5 GB) ─ C:\\
```

**第一个 (64GB) 是你的 SD 卡。第二个 (931GB/1TB) 是你的电脑硬盘。一定要选 SD 卡！**

每个选项前面有一个勾选框。**只勾 SD 卡那个**，点 **「Select (1)」** 确认。

> ⚠️ 这里是最容易出错的一步。选了硬盘的话，你电脑所有东西都会没！确认容量显示的是你的 SD 卡大小（64GB 或 128GB），不放心的话可以在 Windows 资源管理器里先看一下 SD 卡是哪个盘符。

### 4.5 第三步：开始烧录

点最下面那个大蓝色按钮 **「Flash!」**。

Etcher 开始干活。过程分两步：

1. **Flashing...**（写入数据）—— 进度条走，约 5-10 分钟
2. **Validating...**（校验数据）—— 再读一遍确认写入正确，约 3-5 分钟

**全程不要拔 SD 卡，不要关 Etcher，不要让电脑睡眠。** 可以去喝杯水，总共约 10-15 分钟。

### 4.6 烧录完成

进度条走完，界面变成绿色，中间显示：

```
┌──────────────────────────────────┐
│                                  │
│     ✓  Flash Complete!          │
│                                  │
│     1 successful target          │
│                                  │
└──────────────────────────────────┘
```

成功了！

### 4.7 弹出 SD 卡

1. Windows 可能会再次弹出"需要格式化"——**继续点取消**。
2. 右下角任务栏，找到一个 U 盘图标（"安全删除硬件并弹出媒体"）。
3. 点它 → 找到你的 SD 卡 → 点 **「弹出」**。
4. 提示"可以安全移除硬件"后，把读卡器从 USB 口拔下来，取出 TF 卡。

> 不弹出的直接拔也行，但偶尔会损坏数据。反正镜像已经烧好了，一般没事。

---

## 第 5 步：Jetson 首次开机连接

### 5.1 插卡

Jetson Orin Nano 板子翻到背面，右下角处有一个金属 SD 卡槽。

把 TF 卡正面（印了商标那面）朝下，金手指朝下，推进卡槽，直到听到轻轻的 "咔哒" 一声，卡就锁住了。

> 如果卡推进去弹出来，说明推得不够深。用指甲往里再推一点，卡到位后表面和板子齐平。

### 5.2 连接外设

1. **显示器/触摸屏**：用 HDMI 线连接 Jetson 的 HDMI 口（板子左侧，两个黑色接口中靠前那个）和显示器。如果你的屏幕只有 VGA 接口，需要一个 HDMI 转 VGA 转接头（¥20）。
2. **键盘鼠标**：如果是有线键鼠，直接插 Jetson 的 USB 口（板子上有 2 个标准 USB-A 口）。如果是无线键鼠，把接收器插进去。
3. **网线（可选）**：板子右侧有一个 RJ45 网口，插网线可以直接上网。不插的话首次开机时用 WiFi 也行。

### 5.3 通电开机

**最后一步**：把 USB-C 电源线插到 Jetson 的 USB-C 口。这个口在板子正面，旁边标了 "Power"。

> ⚠️ 必须是 USB-C PD 充电器（支持 15V/3A 以上输出），不能随便拿个手机充电器怼上去。Jetson 套件自带的那个电源适配器就是对的。普通的手机 5V 充电头供电不够，Jetson 不会启动。

插上电源后，Jetson 风扇会转起来，1-2 秒后显示器上出现 NVIDIA 的绿色 logo：

```
     ┌──────────┐
     │ NVIDIA   │
     │          │
     └──────────┘
```

下面有加载进度条。等约 1 分钟，进入 Ubuntu 启动画面（橙色/黑色背景）。

> 如果屏幕一直黑的，检查：① HDMI 线接的对不对 ② 显示器信号源选对了没有（有的显示器要手动切到 HDMI 输入）③ 电源是不是 Jetson 自带那个。

---

## 第 6 步：Ubuntu 首次设置向导

Jetson 启动完成后，显示器上出现 Ubuntu 22.04 的首次设置向导。全程 2 分钟，跟着屏幕点就行。

### 6.1 选择语言

第一个画面：
```
┌──────────────────────────────┐
│  Welcome                     │
│                              │
│  English                     │  ← 选这个
│  ...                         │
└──────────────────────────────┘
```
选 **English**（建议别选中文，后面终端里中文文件名容易出乱码）。如果实在想用中文，选 中文(简体) 也行。

### 6.2 键盘布局

自动检测到 `English (US)`，不用改，点 **「Continue」**。

如果你的键盘不是美式布局（比如欧版键盘），可以在这里选。不确认的话直接继续就行。

### 6.3 连接网络

此时 Jetson 会扫描附近的 WiFi，列成一个列表。找到你的 WiFi，点它，输入密码，点 **「Connect」**。

如果插了网线，这一步会自动跳过，直接显示已连接。

> WiFi 连不上怎么办？先点 **「Skip」** 跳过，后面进桌面了再连。不影响系统安装。

### 6.4 时区

地图界面，鼠标点一下**中国大致位置**（或者直接输入 `Shanghai`），时区变为 `Asia/Shanghai`，点 **「Continue」**。

### 6.5 创建用户

```
Your name:                hydropower
Your computer's name:     jetson-orin    ← 自动生成，不用改
Pick a username:          hydropower
Choose a password:        hydropower123
Confirm your password:    hydropower123
```

下面有一个选项 **「Require my password to log in」** vs **「Log in automatically」**。建议选 **「Log in automatically」**（自动登录），因为 Jetson 一般是放在电站机房柜子里，没人会去手动输密码。

> **记下这个密码！** 账号 `hydropower`，密码 `hydropower123`。后面远程 SSH 登录、安装软件都需要。

点 **「Continue」**。

### 6.6 等待配置完成

出现 "Setting up your system..." 进度条，等 1-2 分钟。

### 6.7 进入桌面

配置完成 → 自动登录 → **Ubuntu 桌面出现**！左边有一排图标，顶部有状态栏。

---

## 第 7 步：验证系统

### 7.1 打开终端

桌面右下角有一个 **「Show Applications」** 按钮（九个点组成的方格图标），点开 → 搜索框里打 `Terminal` → 点开。

或者直接快捷键 **`Ctrl + Alt + T`**。

终端是一个黑底白字的窗口，所有命令都在这里输入。**命令输入后按回车执行**。

### 7.2 检查 JetPack 版本

```bash
cat /etc/nv_tegra_release
```

输出类似：
```
# R36 (release), REVISION: 3.0, GCID: ...
```
显示 `R36` 就说明 JetPack 6.0 装好了。

### 7.3 检查 CUDA + PyTorch

```bash
python3 -c "import torch; print(torch.cuda.is_available())"
```

输出：
```
True
```

**看到 `True` 就说明一切正常！** 🎉

CUDA 意味着 AI 模型可以用 Jetson 的 GPU 加速推理。没有 CUDA 的话所有 AI 计算只能用 CPU，速度慢几十倍。

### 7.4 检查磁盘空间

```bash
df -h /
```

输出显示 `/dev/mmcblk0p1` 的 Size 大约是你的 SD 卡大小（如 59G），Available 应该还有 30GB 以上。

---

## 第 8 步：部署 AI 推理服务

Jetson 系统就绪，现在把部署包拷进去跑起来。

### 8.1 拷贝部署包

方式一：**U 盘拷贝**

把 `hydropower_deploy` 文件夹拷到 U 盘 → U 盘插 Jetson → Jetson 桌面会自动弹出一个窗口显示 U 盘内容。

打开终端，输入：

```bash
# 创建目录
sudo mkdir -p /opt/hydropower

# 拷贝部署包（把下面路径换成你 U 盘的实际路径）
sudo cp -r /media/hydropower/*/hydropower_deploy/* /opt/hydropower/

# 确认拷进去了
ls /opt/hydropower/
```

> U 盘的路径一般在 `/media/hydropower/` 下面，用 Tab 键自动补全可以找到。

方式二：**scp 网络传输**（Jetson 和你的电脑在同一局域网时）

在你电脑（Windows）上打开 PowerShell：

```powershell
scp -r D:\hydropower_deploy\* hydropower@jetson-ip:/opt/hydropower/
```

### 8.2 安装依赖

在 Jetson 的终端里：

```bash
# 安装系统依赖
sudo apt update
sudo apt install -y python3-pip libmysqlclient-dev

# 安装 Python 依赖
cd /opt/hydropower
pip3 install -r requirements.txt
```

等约 2-3 分钟。

### 8.3 测试推理

```bash
cd /opt/hydropower
echo '{"upstream_level":180,"downstream_level":118,"inflow":350}' | python3 infer_cli.py
```

输出一行 JSON，包含 `"success": true` → 推理正常！

### 8.4 启动 API 服务

```bash
cd /opt/hydropower
python3 api_server.py --port 5000
```

终端显示：
```
Listening on http://0.0.0.0:5000
```

**这个窗口不要关**，让它一直跑着。

新开一个终端，验证：

```bash
curl http://localhost:5000/api/health
```

返回 `{"status":"ok"}` → 服务已启动，Jetson AI 推理服务部署完成！

---

# 方式二：VMware 虚拟机（适合批量管理多台 Jetson）

## 准备清单

| 你需要的东西 | 哪里来 |
|-------------|--------|
| Windows 电脑 | 你现在用的 |
| 硬盘空闲 100GB+ | 装虚拟机 |
| 内存 ≥ 16GB（8GB 也能跑但慢） | 现成的 |
| NVIDIA 开发者账号 | 免费注册 |
| **以下 Jetson 到手后:** | |
| Jetson Orin Nano | 买的 |
| USB-C 数据线（能传数据，不是纯充电线） | 套件自带或另买 |
| 跳线帽 | 套件自带 |
| 键鼠 + 显示器 | Jetson 首次开机用 |

---

## 第 1 步：下载 VMware Workstation

访问 `https://www.vmware.com/products/workstation-pro.html` → 下载 **Workstation Pro 17 for Windows** → 一路默认安装。

## 第 2 步：下载 Ubuntu 22.04 镜像

访问 `https://releases.ubuntu.com/jammy/` → 下载 `ubuntu-22.04.4-desktop-amd64.iso`（约 4.7GB）。

> ⚠️ 必须用 **Ubuntu 22.04**，不能是 24.04。SDK Manager 不兼容 24.04。

## 第 3 步：创建 Ubuntu 虚拟机

1. 打开 VMware → **「创建新的虚拟机」**
2. 选 **「典型(推荐)」** → 下一步
3. 选 **「安装程序光盘映像文件」** → 浏览选第 2 步的 `.iso` → 下一步
4. 客户机操作系统：**Linux** → 版本：**Ubuntu 64-bit** → 下一步
5. 虚拟机名称 `Ubuntu-Jetson`，位置改到 D 盘（≥100GB）→ 下一步
6. 磁盘大小 **80GB**，选 **「单个文件」** → 下一步
7. 点 **「自定义硬件」**：内存 → 8192MB，CPU → 4 核 → 关闭 → 完成

## 第 4 步：在虚拟机里安装 Ubuntu

虚拟机自动启动进入 Ubuntu 安装画面：
- 语言：English → Install Ubuntu
- 键盘：English (US) → Continue
- 安装类型：Normal installation + 两个勾都勾上 → Continue
- 磁盘：**Erase disk and install Ubuntu**（擦的是虚拟机假硬盘，不影响 Windows）→ Install Now
- 时区：Shanghai
- 创建用户：`hydropower` / 简单密码，选 Log in automatically
- 等待 10-15 分钟 → Restart Now → 进入 Ubuntu 桌面

## 第 5 步：在 Ubuntu 里装 SDK Manager

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y python3-pip ssh curl
```
访问 `https://developer.nvidia.com/sdk-manager` → 下载 `.deb` 文件

```bash
cd ~/Downloads
sudo dpkg -i sdkmanager*.deb
sudo apt install -f -y
sudo dpkg -i sdkmanager*.deb
sdkmanager
```

## 第 6 步：Jetson 进入恢复模式

1. **跳线帽套在 FC REC 和 GND 上**（两个金属针短接）
2. USB-C 线连 Jetson 和 PC
3. **最后插 Jetson 电源**
4. 屏幕黑、风扇转/不转 → 就是恢复模式

在 VMware 菜单：**虚拟机 → 可移动设备 → NVIDIA APX → 连接**。验证：
```bash
lsusb | grep NVIDIA
# 有输出就说明连上了
```

## 第 7 步：SDK Manager 烧录

1. 登录 NVIDIA 账号
2. STEP 1：勾选 **Jetson Orin Nano 8GB**，取消 Host Machine
3. STEP 2：选 JetPack 6.0，勾 PyTorch、CUDA、cuDNN、TensorRT、OpenCV
4. STEP 3：创建 Jetson 用户 `hydropower` / `hydropower123`
5. STEP 4：点 Flash → 等 30-60 分钟

## 第 8 步：Jetson 首次开机

1. 拔 Jetson 电源 → 拔 USB-C → **拔跳线帽**（必须拔！）
2. 接显示器 + 键鼠 → 插电源 → 开机
3. 等 1-2 分钟 → 用 `hydropower/hydropower123` 登录
4. 验证：`python3 -c "import torch; print(torch.cuda.is_available())"` → True

## 第 9 步：部署 AI 推理服务

```bash
sudo apt install -y python3-pip libmysqlclient-dev
pip3 install numpy scikit-learn joblib mysqlclient minimalmodbus flask flask-cors

sudo mkdir -p /opt/hydropower
sudo cp -r /media/hydropower/USB/hydropower_deploy/* /opt/hydropower/

cd /opt/hydropower
python3 api_server.py --port 5000
```

---

## 常见问题

**Q: VMware 里 Ubuntu 特别卡**
虚拟机关机，编辑设置 → 内存拉到 8GB，CPU 核心拉到 4。

**Q: SDK Manager 检测不到 Jetson**
1. 跳线帽是否套在 FC REC 和 GND 上？操作顺序是否对（先短接再上电）？
2. USB-C 线是否只能充电不能传数据？换一根试试
3. VMware 是否连接了 USB 设备？（菜单 → 可移动设备 → NVIDIA APX → 连接）

**Q: Etcher 烧录失败**
1. SD 卡容量是否 ≥ 64GB？
2. 读卡器接触不好？换个 USB 口或读卡器
3. 镜像文件下载完整吗？检查文件大小

**Q: Jetson 开机黑屏**
1. SD 卡方式：卡插好了吗？重新插拔试一下
2. VMware 方式：跳线帽拔了吗？（没拔就一直进恢复模式）
3. 电源是否接好？用 USB-C Power 口，不要用普通 USB 口

**Q: 烧录过程中网络出错**
NVIDIA 服务器在国外，下载约 8GB 可能很慢。重试一次，SDK Manager 会断点续传。
