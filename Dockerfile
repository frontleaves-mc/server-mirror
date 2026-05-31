# syntax=docker/dockerfile:1
FROM --platform=$TARGETPLATFORM azul/zulu-openjdk:21-jre

# 安装 curl 和 wget 以供后续健康检查使用；
# 安装 fontconfig、binutils、ca-certificates，为 JNI 本地库（如 Rapier 物理引擎）提供必要的运行时依赖；
# 创建非 root 用户，创建应用目录，并确保 /tmp 可写以支持本地 .so 库提取。
# 最后运行 ldconfig 注册系统库路径，避免动态链接器缓存缺失导致 JNI 加载失败。
# 清理 apt 缓存以缩小镜像体积。
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    wget \
    fontconfig \
    binutils \
    ca-certificates \
    && rm -rf /var/lib/apt/lists/* \
    && groupadd --gid 1000 minecraft \
    && useradd --uid 1000 --gid minecraft --shell /bin/bash --create-home minecraft \
    && mkdir -p /app /data /app/nativelibs \
    && chmod 1777 /tmp \
    && ldconfig

# 复制服务器核心与入口脚本，并设置正确的文件所有者
COPY --chown=minecraft:minecraft server-core.jar /app/server-core.jar
COPY --chown=minecraft:minecraft entrypoint.sh /app/entrypoint.sh

# 赋予入口脚本可执行权限，并确保 /app 与 /data 目录的所有权正确
RUN chmod +x /app/entrypoint.sh \
    && chown -R minecraft:minecraft /app /data

# 暴露 Minecraft 服务器端口
EXPOSE 25565

# 切换至非 root 用户运行
USER minecraft

# 设置工作目录为数据卷
WORKDIR /data

# 设置容器入口点
ENTRYPOINT ["/app/entrypoint.sh"]
