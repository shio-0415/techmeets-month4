# Week 16 課題まとめ: Docker最適化・セキュリティ・ログ運用

> 注記: この環境にはDockerデーモンが無いため、実際の `docker build` 実行結果は
> 手元（またはCI）で再現してください。以下のイメージサイズは公開イメージの
> 一般的な実測値に基づく参考値です。ご自身の環境で `docker images` を実行し、
> 実測値に置き換えてレポートしてください。

---

## 基本課題: 最適化されたDockerfile

`app/Dockerfile` を参照。要件との対応:

| 要件 | 実装箇所 |
|---|---|
| マルチステージビルド | `deps` → `build` → `runtime` の3ステージ |
| Alpineベース | 全ステージで `node:20-alpine` |
| イメージサイズ300MB以下 | 最終ステージに本番依存とビルド成果物のみコピー(下記参照) |
| 非rootユーザー | `appuser`(UID 1001)を作成し `USER appuser` |
| ヘルスチェック | `/health` エンドポイント + `HEALTHCHECK` 命令 |
| .dockerignore | `app/.dockerignore` |

### 設計上のポイント
- **deps ステージ**: `npm ci --omit=dev` で本番用依存だけを確定的にインストール。
- **build ステージ**: devDependencies込みでビルド(今回はサンプルなので実質コピーのみ)。
  実プロジェクトでTypeScript/Webpackを使う場合はここで `tsc`/`webpack` を実行。
- **runtime ステージ**: `node_modules` とアプリコードのみをコピーし、
  ビルドツールやソースの中間生成物は最終イメージに残らない。
- `dumb-init` でPID1のシグナルハンドリング問題(SIGTERMが子プロセスに伝わらない、
  ゾンビプロセスが残る)を回避。オーケストレーション環境での正常終了に必須。

---

## 練習課題1: イメージサイズ削減(before/after)

典型的な削減パターンとその効果の目安:

| 段階 | Dockerfile構成 | イメージサイズ目安 |
|---|---|---|
| Before | `node:20` (フルDebianベース) + マルチステージなし + devDependencies込み | 約 950MB〜1.1GB |
| After (1) | ベースを `node:20-alpine` に変更のみ | 約 170〜200MB |
| After (2) | + マルチステージビルド(ビルドツールを最終イメージから除外) | 約 140〜160MB |
| After (3) | + `npm ci --omit=dev` + `npm cache clean` + apkキャッシュ削除 | 約 120〜140MB |

削減率の目安: **950MB → 130MB程度で 85%以上の削減**(要件の50%を大きく上回る)。

### 実測手順(お使いの環境で実行してください)
```bash
# Before: フルイメージでビルド
docker build -f Dockerfile.before -t week16-app:before ./app
docker images week16-app:before --format "{{.Size}}"

# After: 最適化済みDockerfileでビルド
docker build -t week16-app:after ./app
docker images week16-app:after --format "{{.Size}}"

# レイヤー内訳を確認
docker history week16-app:after
```
`Dockerfile.before` の例(比較用、シングルステージ・フルベース):
```dockerfile
FROM node:20
WORKDIR /app
COPY . .
RUN npm install
CMD ["node", "src/index.js"]
```

---

## 練習課題2: セキュリティ対策(Trivyスキャン)

### スキャン実行
```bash
docker run --rm -v /var/run/docker.sock:/var/run/docker.sock \
  aquasec/trivy image week16-app:after

# CRITICAL/HIGHのみに絞って確認
trivy image --severity CRITICAL,HIGH week16-app:after
```

### 典型的な脆弱性と対策
| 検出パターン | 対策 |
|---|---|
| ベースイメージのOSパッケージ脆弱性 | `apk update && apk upgrade --no-cache` をruntimeステージで実行(実装済み) |
| Node.js本体の既知CVE | ベースイメージを最新のLTSマイナーバージョンに固定・定期更新(`node:20-alpine` → `node:20.x-alpine` でピン留め) |
| npm依存パッケージの脆弱性 | `npm audit fix` をCIに組み込み、`package-lock.json` を必ずコミット |
| rootでの実行によるリスク増幅 | 非rootユーザーで実行(実装済み) |
| 不要なパッケージ・シェルの残存 | `--no-cache` オプションでapkキャッシュを残さない、alpineベースで攻撃対象面を最小化 |

### CI組み込み
`.github/workflows/docker-build-push.yml` にTrivyスキャンを組み込み、
**CRITICAL/HIGHが検出された場合はビルドを失敗させ、pushをブロック**するゲートを実装済み。

---

## 練習課題3: ログ収集(集中管理)

構成: `logging/docker-compose.logging.yml`(Loki + Promtail + Grafana)

### 設計方針
1. **アプリ側**: `console.log` でJSON構造化ログをstdoutに出力するだけ
   (`app/src/index.js` の `log()` 関数)。ファイルに書き込まない
   ( = 12-Factor Appの「ログはイベントストリームとして扱う」原則)。
2. **収集**: PromtailがDockerソケット経由で全コンテナのログを検出し、
   `logging=promtail` ラベルが付いたコンテナのログだけをLokiへ転送。
3. **保存・検索**: Loki(Prometheusライクなラベルベースのログストア)。
4. **可視化**: GrafanaでLokiをデータソースとして接続し、
   `{container="week16-app"} |= "error"` のようなクエリでログを横断検索。

### 起動方法
```bash
docker compose -f logging/docker-compose.logging.yml up -d
# Grafana: http://localhost:3001 (admin / change_me)
# Loki直接クエリ: http://localhost:3100
```

### ログローテーション
`json-file` ドライバに `max-size`/`max-file` を必ず設定(実装済み)。
設定しないとホストのディスクを圧迫し続ける典型的な事故パターン。

---

## チェックリスト解説

- **マルチステージビルドを使える** → `app/Dockerfile` の3ステージ構成
- **Dockerイメージを最適化できる** → 練習課題1の削減手順
- **セキュリティを意識したDockerfileを書ける** → 非root・apk upgrade・Trivyゲート
- **Docker HubにDockerイメージをpushできた**:
  ```bash
  docker login
  docker tag week16-app:after your-dockerhub-username/week16-app:latest
  docker push your-dockerhub-username/week16-app:latest
  ```
- **GitHub Actionsでイメージの自動ビルド・pushを実装できた** →
  `.github/workflows/docker-build-push.yml`(build→scan→push→sizeレポート)
- **ロールバック手順を理解している**:
  1. 各pushでイメージにコミットSHAタグを付与しておく(`type=sha` を設定済み)
  2. 問題発生時は直前の正常なSHAタグに対して再デプロイ
     (例: `docker service update --image your-image:<previous-sha> <service>`、
     KubernetesならDeploymentの `kubectl rollout undo`)
  3. `latest` タグだけに依存した運用はロールバック不能になるため避ける
- **本番環境でのDocker運用を理解している** → ヘルスチェック、graceful shutdown
  (SIGTERM対応)、ログローテーション、リソース制限(`--memory`/`--cpus`)、
  読み取り専用ルートファイルシステム(`--read-only`)などが要点
- **ログ管理ができる** → 練習課題3のLoki/Promtail/Grafana構成
- **コンテナオーケストレーション概要を理解している**:
  - 単一ホストの `docker run`/`docker compose` の限界(単一障害点、手動スケーリング)
  - Kubernetes/ECS/Docker Swarmが解決する課題: 自動再起動、ローリングアップデート、
    サービスディスカバリ、水平スケーリング、ヘルスチェック連携による自動切り離し
  - 今回実装したHEALTHCHECKやgraceful shutdownは、これらオーケストレータが
    正しく機能するための前提条件になっている

---

## 次のステップ
Week 17: パフォーマンス最適化(N+1問題・クエリ最適化・キャッシュ戦略)へ進みます。
