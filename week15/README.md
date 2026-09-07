# Week15 基本課題・練習課題: GitHub Actionsで自動デプロイ

Week14までのLaravelプロジェクト（投稿機能・ユーザー認証・Service層 + テスト一式）に、
GitHub ActionsによるCI/CDパイプラインを追加しました。

> **デプロイ先について**: 実際の本番サーバー/ホスティングサービスはまだ用意していないため、
> 「サーバーへの転送」部分は `scripts/deploy.sh` の中でコマンドを表示するだけの
> **シミュレーション**にしています。テスト・Lint・ビルド・パッケージングまでは実際に動きます。
> 本物のサーバーが用意できたら、`scripts/deploy.sh` の中身を実際の `rsync`/`ssh`/デプロイAPI呼び出しに
> 差し替えるだけで、そのまま本番運用のパイプラインとして使えます。

## パイプライン全体像

```
git push (develop / main) または Pull Request
        │
        ▼
   ① lint ジョブ
      - PHP CS Fixer（コーディングスタイルチェック）
      - PHPStan（静的解析）
        │ (成功したら次へ)
        ▼
   ② test ジョブ
      - PHPUnit（Unit + Feature、63件）
      - カバレッジ計測（70%未満なら失敗）
      - カバレッジレポートをActionsの画面に表示 + HTMLレポートをアーティファクトとして保存
      - composer audit / npm audit
        │ (成功したら次へ、pushの場合のみ)
        ▼
   ③ deploy-staging / deploy-production ジョブ
      - developブランチへのpush → ステージング環境へデプロイ（シミュレーション）
      - mainブランチへのpush     → 本番環境へデプロイ（シミュレーション）
```

Pull Requestの場合は ①②のみ実行され、デプロイは行われません（mainへのpush時のみ本番デプロイ）。

## 含まれるファイル

```
week15/
├── .github/workflows/
│   ├── ci-cd.yml          ← メインパイプライン（lint→test→deploy）
│   └── pr-comment.yml     ← 【任意/ボーナス】PRへのテスト結果コメント
├── .php-cs-fixer.dist.php ← PHP CS Fixerの設定
├── phpstan.neon           ← PHPStanの設定
├── scripts/deploy.sh      ← デプロイスクリプト（シミュレーション）
└── composer.json          ← PHP CS Fixer / PHPStan を追加、lint/analyseスクリプトを追加
```

## 基本課題

1. **Lint自動チェック（PHP CS Fixer）**: `.php-cs-fixer.dist.php` で設定。CIの `lint` ジョブで
   `composer lint`（= `php-cs-fixer fix --dry-run --diff`）を実行し、スタイル違反があれば失敗する。
2. **自動テスト（PHPUnit）**: `test` ジョブで、Week14で作成した63件のテストを実行。
3. **mainブランチへのpush時に自動デプロイ**: `deploy-production` ジョブが
   `github.ref == 'refs/heads/main' && github.event_name == 'push'` の条件で発火。
4. **PRへのコメントでテスト結果を通知（任意）**: `.github/workflows/pr-comment.yml` に実装済み
   （`actions/github-script` を使用）。必須ではないため別ファイルに分離しています。

## 練習課題1: テスト自動化（カバレッジレポート表示）

`test` ジョブの中で:
- `php artisan test --coverage --coverage-html=coverage-html --min=70` でテキスト版とHTML版の
  両方のカバレッジレポートを同時に生成
- テキスト版の要約を `$GITHUB_STEP_SUMMARY` に書き込み、Actionsの実行結果画面に直接表示
- HTML版は `actions/upload-artifact` でアーティファクトとして保存し、ダウンロードして
  ブラウザで詳細を確認できるようにしている

## 練習課題2: Lint設定（PHPStan + PHP CS Fixer）

- `composer.json` の `require-dev` に `friendsofphp/php-cs-fixer` と `phpstan/phpstan` を追加
- `phpstan.neon` でレベル5の静的解析を `app/`, `routes/`, `database/` に対して実施
- `composer lint` / `composer analyse` としてスクリプト化し、ローカルでもCIでも同じコマンドで実行可能

## 練習課題3: デプロイ自動化（ステージング・本番）

- GitHub Actionsの `environment:` 機能を使い、`staging` と `production` の2つの環境を分離
- `develop` ブランチへのpush → `deploy-staging` ジョブが発火
- `main` ブランチへのpush → `deploy-production` ジョブが発火
- どちらも `needs: test` により、テストが通らない限りデプロイされない設計

## セットアップ（ローカル）

```bash
composer require --dev friendsofphp/php-cs-fixer phpstan/phpstan
```

もしくは、この一式をコピーした後に:

```bash
composer install
```

## ローカルでのLint/静的解析の実行

```bash
composer lint       # PHP CS Fixerでスタイルチェック（--dry-run、修正はしない）
composer lint:fix    # 実際にスタイルを自動修正する
composer analyse     # PHPStanで静的解析
```

## 動作確認の流れ（おすすめ）

1. `develop` ブランチを作って push → ステージングへのデプロイジョブが動くことを確認
2. `main` ブランチへマージ → 本番へのデプロイジョブが動くことを確認
3. Pull Requestを作ってみる → lint・testのみ動き、デプロイは動かないことを確認
4. GitHubの「Actions」タブで各ジョブの実行結果、「Summary」タブでカバレッジレポートを確認

