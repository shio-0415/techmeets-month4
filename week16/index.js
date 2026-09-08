const express = require('express');
const app = express();
const PORT = process.env.PORT || 3000;

// 構造化ログ（stdout/stderrに出す = 12-Factor App準拠）
// これによりDockerのログドライバやFluentd/Promtail等の集約基盤に
// そのまま流し込める
function log(level, message, meta = {}) {
  console.log(JSON.stringify({
    timestamp: new Date().toISOString(),
    level,
    message,
    ...meta,
  }));
}

app.use((req, res, next) => {
  const start = Date.now();
  res.on('finish', () => {
    log('info', 'request', {
      method: req.method,
      path: req.path,
      status: res.statusCode,
      duration_ms: Date.now() - start,
    });
  });
  next();
});

app.get('/', (req, res) => {
  res.json({ message: 'Hello from Week16 optimized container!' });
});

// ヘルスチェック用エンドポイント（DockerfileのHEALTHCHECKから叩く）
app.get('/health', (req, res) => {
  res.status(200).json({ status: 'ok' });
});

app.listen(PORT, () => {
  log('info', `server started on port ${PORT}`);
});

// Graceful shutdown（コンテナオーケストレーション環境でのSIGTERM対応）
process.on('SIGTERM', () => {
  log('info', 'SIGTERM received, shutting down gracefully');
  process.exit(0);
});
