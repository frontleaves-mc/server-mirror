<?php
/**
 * Minecraft Server List Ping (SLP) — 直接 TCP 连接，无需外部 API
 * 支持 Java 版 1.7+ 的 SLP 协议
 */

function pingMinecraftServer(string $host, int $port, float $timeout = 5.0): ?array {
    $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
    if (!$socket) return null;

    stream_set_timeout($socket, (int)$timeout);

    // Handshake packet: Protocol version, server address, port, next state=1 (status)
    $data = pack('c', 0x00);                          // packet id
    $data .= pack('c', 0x00);                          // protocol version (0 = dummy)
    $data .= pack('c', strlen($host)) . $host;         // server address (varint length + string)
    $data .= pack('n', $port);                          // port (unsigned short, big-endian)
    $data .= pack('c', 0x01);                          // next state: 1 = status

    $packet = pack('c', strlen($data)) . $data;        // length prefix (varint, <128 safe)
    fwrite($socket, $packet);

    // Status request packet
    fwrite($socket, "\x01\x00");                       // length=1, packet id=0

    // Read response
    $response = '';
    while (!feof($socket)) {
        $chunk = fread($socket, 4096);
        if ($chunk === false || $chunk === '') break;
        $response .= $chunk;
    }
    fclose($socket);

    if ($response === '') return null;

    // Parse varint at offset, returns [value, new_offset]
    $offset = 0;
    $readVarInt = function () use (&$response, &$offset): int {
        $result = 0;
        for ($shift = 0; ; $shift += 7) {
            if ($offset >= strlen($response)) return 0;
            $byte = ord($response[$offset++]);
            $result |= ($byte & 0x7F) << $shift;
            if (($byte & 0x80) === 0) break;
        }
        return $result;
    };

    // Skip packet length
    $readVarInt();
    // Skip packet id
    $readVarInt();
    // Read JSON string length
    $jsonLen = $readVarInt();

    if ($jsonLen <= 0 || ($offset + $jsonLen) > strlen($response)) return null;

    $jsonStr = substr($response, $offset, $jsonLen);
    $json = json_decode($jsonStr, true);

    if ($json === null) return null;

    return [
        'online'    => true,
        'host'      => $host,
        'port'      => $port,
        'version'   => $json['version']['name'] ?? 'Unknown',
        'protocol'  => $json['version']['protocol'] ?? 0,
        'motd'      => $json['description'] ?? '',
        'players'   => [
            'online' => $json['players']['online'] ?? 0,
            'max'    => $json['players']['max'] ?? 0,
            'list'   => $json['players']['sample'] ?? [],
        ],
        'favicon'   => $json['favicon'] ?? null,
    ];
}

function motdToHtml(array|string $motd): string {
    if (is_string($motd)) return htmlspecialchars($motd);

    $html = '';
    foreach ($motd['extra'] ?? [] as $part) {
        $text = htmlspecialchars($part['text'] ?? '');
        $style = '';
        if (isset($part['color'])) $style .= 'color:' . htmlspecialchars($part['color']) . ';';
        if (!empty($part['bold'])) $style .= 'font-weight:bold;';
        if (!empty($part['italic'])) $style .= 'font-style:italic;';
        if (!empty($part['underlined'])) $style .= 'text-decoration:underline;';
        if (!empty($part['strikethrough'])) $style .= 'text-decoration:line-through;';
        $html .= $style ? "<span style=\"$style\">$text</span>" : $text;
    }
    return $html ?: htmlspecialchars($motd['text'] ?? '');
}

$servers = [
    ['host' => 'youer-server', 'port' => 25565, 'name' => 'Frontleaves MC'],
];

$statuses = [];
foreach ($servers as $server) {
    $statuses[] = pingMinecraftServer($server['host'], $server['port']);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MC Server Status</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: -apple-system, 'Segoe UI', Roboto, sans-serif; background: #1a1a2e; color: #e0e0e0; min-height: 100vh; display: flex; flex-direction: column; align-items: center; padding: 40px 20px; }
h1 { font-size: 1.5rem; margin-bottom: 30px; color: #8be9fd; }
.grid { display: flex; flex-wrap: wrap; gap: 24px; justify-content: center; max-width: 1200px; }
.card { background: #282a36; border-radius: 12px; padding: 24px; min-width: 340px; max-width: 400px; box-shadow: 0 4px 20px rgba(0,0,0,0.4); border: 1px solid #44475a; }
.card-header { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
.dot { width: 14px; height: 14px; border-radius: 50%; flex-shrink: 0; }
.dot.on { background: #50fa7b; box-shadow: 0 0 8px #50fa7b; }
.dot.off { background: #ff5555; box-shadow: 0 0 8px #ff5555; }
.card-title { font-size: 1.2rem; font-weight: 600; }
.card-body { line-height: 1.8; font-size: 0.95rem; }
.motd { background: #1e1f29; padding: 8px 12px; border-radius: 6px; margin-bottom: 12px; min-height: 24px; }
.field { display: flex; justify-content: space-between; padding: 2px 0; }
.field-label { color: #6272a4; }
.field-value { font-weight: 500; }
.player-bar { height: 8px; background: #44475a; border-radius: 4px; margin-top: 8px; overflow: hidden; }
.player-bar-fill { height: 100%; border-radius: 4px; transition: width 0.5s ease; }
.player-list { margin-top: 12px; display: flex; flex-wrap: wrap; gap: 6px; }
.player-tag { background: #44475a; padding: 2px 8px; border-radius: 4px; font-size: 0.85rem; }
.offline-msg { color: #ff5555; text-align: center; padding: 20px 0; font-style: italic; }
.footer { margin-top: 40px; color: #6272a4; font-size: 0.8rem; }
</style>
</head>
<body>
<h1>Minecraft Server Status</h1>
<div class="grid">
<?php foreach ($servers as $i => $server): ?>
<?php $s = $statuses[$i]; ?>
<div class="card">
  <div class="card-header">
    <span class="dot <?= $s ? 'on' : 'off' ?>"></span>
    <span class="card-title"><?= htmlspecialchars($server['name']) ?></span>
  </div>
  <?php if ($s): ?>
  <div class="card-body">
    <div class="motd"><?= motdToHtml($s['motd']) ?></div>
    <div class="field"><span class="field-label">版本</span><span class="field-value"><?= htmlspecialchars($s['version']) ?></span></div>
    <div class="field"><span class="field-label">玩家</span><span class="field-value"><?= $s['players']['online'] ?> / <?= $s['players']['max'] ?></span></div>
    <?php
      $pct = $s['players']['max'] > 0 ? ($s['players']['online'] / $s['players']['max']) * 100 : 0;
      $barColor = $pct > 80 ? '#ff5555' : ($pct > 50 ? '#f1fa8c' : '#50fa7b');
    ?>
    <div class="player-bar"><div class="player-bar-fill" style="width:<?= min($pct, 100) ?>%;background:<?= $barColor ?>"></div></div>
    <?php if (!empty($s['players']['list'])): ?>
    <div class="player-list">
      <?php foreach ($s['players']['list'] as $p): ?>
      <span class="player-tag"><?= htmlspecialchars($p['name'] ?? '') ?></span>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <div class="offline-msg">Server offline</div>
  <?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<p class="footer">Auto-refresh: <span id="countdown">60</span>s &middot; Direct SLP ping</p>
<script>
let sec = 60;
setInterval(() => { if (--sec <= 0) location.reload(); document.getElementById('countdown').textContent = sec; }, 1000);
</script>
</body>
</html>
