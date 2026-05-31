# syntax=docker/dockerfile:1
FROM ubuntu:24.04

# 安装系统依赖 + Azul Zulu JRE 21（通过官方 APT 源）
# 创建应用目录与 mod 原生库提取目录，运行 ldconfig 注册系统库路径。
RUN apt-get update && apt-get install -y --no-install-recommends \
    gnupg \
    ca-certificates \
    curl \
    wget \
    fontconfig \
    binutils \
    && curl -s https://repos.azul.com/azul-repo.key \
        | gpg --dearmor -o /usr/share/keyrings/azul.gpg \
    && echo "deb [signed-by=/usr/share/keyrings/azul.gpg] https://repos.azul.com/zulu/deb stable main" \
        > /etc/apt/sources.list.d/zulu.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends zulu21-jre-headless \
    && rm -rf /var/lib/apt/lists/* \
    && mkdir -p /app /data /app/nativelibs /data/.sable/natives /data/.pg-native \
    && ldconfig

COPY server-core.jar /app/server-core.jar
COPY entrypoint.sh /app/entrypoint.sh

RUN chmod +x /app/entrypoint.sh

EXPOSE 25565

WORKDIR /data

ENTRYPOINT ["/app/entrypoint.sh"]
