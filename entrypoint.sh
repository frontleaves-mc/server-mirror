#!/bin/sh
set -e

# 设置文件权限掩码，确保组内可写
umask 002

# 确保数据目录存在，并预创建 mod 原生库提取目录（Sable/Rapier/PowerGrid 等）
mkdir -p /data
mkdir -p /data/.sable/natives
mkdir -p /data/.pg-native

# 读取环境变量并设置默认值
MEMORY="${MEMORY:-8G}"
JAVA_OPTS="${JAVA_OPTS:-}"
JVM_FLAGS="${JVM_FLAGS:-}"
EULA="${EULA:-false}"

# 输出配置信息
echo "=========================================="
echo "  MohistMC Youer 服务器启动"
echo "=========================================="
echo "内存:       ${MEMORY}"
echo "JVM 参数:   ${JVM_FLAGS:-(无)}"
echo "Java 选项:  ${JAVA_OPTS:-(无)}"
echo "EULA:       ${EULA}"
echo "=========================================="

# EULA 处理：如果 EULA=true 且 eula.txt 不存在，则创建
if [ "${EULA}" = "true" ]; then
    if [ ! -f /data/eula.txt ]; then
        echo "eula=true" > /data/eula.txt
        echo "[INFO] 已创建 /data/eula.txt（eula=true）"
    else
        echo "[INFO] /data/eula.txt 已存在，跳过 EULA 创建"
    fi
else
    if [ ! -f /data/eula.txt ]; then
        echo "[WARN] EULA 尚未接受。设置 EULA=true 以自动接受。"
        echo "[WARN] 没有 eula=true 服务器很可能无法启动"
    fi
fi

# 设置 JNI 本地库搜索路径，确保 Rapier 等物理引擎提取到 /tmp 或 /app/nativelibs 的 .so 可被加载
export LD_LIBRARY_PATH="/tmp:/app/nativelibs:${LD_LIBRARY_PATH:-}"

# 切换到数据目录
cd /data

# 构建启动命令
echo "[INFO] 正在启动服务器..."

# 使用 exec 替换当前 shell 进程，使 Java 成为 PID 1
# 这样信号可以直接传递给 Java 进程
exec java -Xmx"${MEMORY}" \
    -Djava.library.path="/tmp:/app/nativelibs:/usr/java/packages/lib" \
    ${JVM_FLAGS} ${JAVA_OPTS} \
    -jar /app/server-core.jar nogui
