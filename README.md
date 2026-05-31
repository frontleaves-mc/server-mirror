# MohistMC Youer Minecraft 服务器 — Docker 镜像

用于 MohistMC Youer Minecraft 服务器（v1.21.1）的生产级 Docker 镜像，这是一个混合 Forge/Fabric + Bukkit 的服务器实现。支持独立模式和 PufferPanel 管理部署两种模式。

---

## 快速开始

使用 PufferPanel 管理界面运行：

```bash
docker compose up -d
```

---

## 构建

为 AMD64 平台构建镜像：

```bash
docker buildx build --platform linux/amd64 -t youer-server .
```

### 前置条件

- 构建前必须在项目根目录放置 `server-core.jar`。这是 MohistMC Youer 服务器核心二进制文件。
- 必须安装 Docker Buildx（Docker Desktop 和较新版本的 Docker Engine 已包含）。

---

## 环境变量

| 变量        | 默认值  | 说明                                                                         |
|-------------|---------|------------------------------------------------------------------------------|
| `MEMORY`    | `8G`    | 分配给 JVM 的最大堆内存（例如：`4G`、`8192M`）。                             |
| `EULA`      | `false` | 设置为 `true` 以在首次启动时自动接受 Minecraft EULA。                        |
| `JVM_FLAGS` | (无)    | 额外的 JVM 参数（例如：`-XX:+UseG1GC -XX:MaxGCPauseMillis=200`）。           |
| `JAVA_OPTS` | (无)    | 直接传递给 `java` 命令的额外 Java 选项。                                     |

### 变量优先级

服务器启动时，Java 选项按以下顺序应用（后指定的覆盖先指定的）：

1. `MEMORY` 设置 `-Xmx`
2. `JVM_FLAGS` 追加额外的 JVM 参数
3. `JAVA_OPTS` 追加最终覆盖参数

这意味着 `JAVA_OPTS` 优先级最高，其次是 `JVM_FLAGS`，最后是 `MEMORY`。

---

## 数据卷

挂载数据卷到 `/data` 以在容器重启后持久化服务器状态：

```bash
-v ./data:/data
```

`/data` 目录存储：

- 世界数据（`world/`、`world_nether/`、`world_the_end/`）
- 服务器配置（`server.properties`、`ops.json`、`whitelist.json` 等）
- 模组和插件（`mods/`、`plugins/`）
- 日志文件（`logs/`）

将模组 `.jar` 文件放置在主机的 `/data/mods` 目录中，服务器启动时会自动加载。

---

## 架构

此镜像专为 **`linux/amd64`** 构建。

- 基础镜像为 `azul/zulu-openjdk:21-jre`（Ubuntu 22.04），自带 GLIBC 2.35+。
- 通过此 GLIBC 版本和标准 C 库环境确保本地库兼容性。
- **不支持 ARM64 及其他架构。**

---

## PufferPanel

默认的 `docker-compose.yml` 包含 PufferPanel，用于基于 Web 的服务器管理。

### 访问面板

在浏览器中打开：

```
http://localhost:8080
```

### 创建管理员用户

容器启动后，运行：

```bash
docker exec -it pufferpanel /pufferpanel/pufferpanel user add
```

按照交互式提示创建管理员账户。

### 端口

| 端口     | 服务                           |
|----------|--------------------------------|
| `25565`  | Minecraft 服务器               |
| `8080`   | PufferPanel Web 界面           |
| `5657`   | PufferPanel SFTP 访问          |

PufferPanel 需要访问主机的 Docker 套接字（`/var/run/docker.sock`），以便为每个托管服务器启动同级容器。

---

## 独立模式

若要在没有 PufferPanel 的情况下运行 Minecraft 服务器，请使用独立 Compose 文件：

```bash
docker compose -f docker-compose.standalone.yml up -d
```

如果你只需要 Minecraft 服务器而不需要 Web 管理面板，此模式非常合适。

---

## Create: Aeronautics 兼容性

[Create: Aeronautics](https://modrinth.com/mod/create-aeronautics) 模组使用 Rapier 3D 物理引擎，该引擎通过 JNI 访问，需要 **GLIBC 2.27 或更高版本**。

此镜像使用 `azul/zulu-openjdk:21-jre`（Ubuntu 22.04，GLIBC 2.35+），满足该要求。为确保 Rapier 本地库能够正确加载，镜像已额外安装 `fontconfig`、`binutils`、`ca-certificates` 等运行时依赖，并在构建阶段执行 `ldconfig` 以注册系统库路径。启动脚本同时配置了 `LD_LIBRARY_PATH` 与 `-Djava.library.path`，覆盖 `/tmp`、`/app/nativelibs` 及默认路径。

Docker Compose 配置已固定 `platform: linux/amd64` 并放宽 `seccomp` 限制，避免 ARM 主机架构不匹配或 Docker 默认 seccomp 过滤器阻止 Rust FFI 系统调用。

### 版本配对要求

| 组件 | 推荐版本 | 备注 |
|------|---------|------|
| **Create** | `6.0.9` | `6.0.10` 与 Sable 存在已知兼容性问题，需降级 |
| **Sable** | `1.0.6+` | Create Aeronautics 的前置依赖 |
| **Create Aeronautics** | `1.1.3` | 与上述版本配对测试通过 |
| **Java** | `21` | Java 24/25 会导致崩溃，必须使用 Java 21 |

如果你正在构建自定义基础镜像或在 GLIBC 版本较旧的主机上运行，请将 C 库升级至至少 2.27，以维持与基于 Rapier 的模组的兼容性。

---

## 非 Root 用户

服务器以 `minecraft` 用户（UID 1000）运行。容器内的所有进程均在此非特权账户下运行，以提高安全性。
