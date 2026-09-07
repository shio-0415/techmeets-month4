# Week14 基本課題・練習課題: テストコード作成

Week5〜9で構築したLaravelプロジェクト（投稿機能・ユーザー認証・Service層を持つアプリケーション）に、
包括的なテストを追加しました。ベースとなっているのは `techmeets-month2` の `week9/基本課題`
（Repository/Serviceパターンへのリファクタリング済みアプリケーション）です。

## 実行結果（検証済み）

```
php artisan test
Tests: 63 passed (129 assertions)
```

```
composer audit
No security vulnerability advisories found.
```

カバレッジ: **85.1%**（目標70%をクリア）

## 基本課題

- **ユニットテスト（14ケース）**: `tests/Unit/Services/PostServiceTest.php`, `PostServiceLikeTest.php`
  - Service層はリポジトリに処理を委譲するだけなので、Mockeryでリポジトリをモック化しDBに依存しないテストにしている
  - 正常系・異常系（存在しないIDでの例外伝播など）・境界値（perPage=1/1000、id=1など）を網羅
- **Feature Test（CRUD全操作）**: `tests/Feature/PostControllerTest.php`（17ケース）
  - 投稿の作成・表示・更新・削除、バリデーション（タイトル255/256文字の境界値）、認可（他人の投稿は編集・削除不可）を検証
  - 認証系（登録・ログイン・ログアウト）は既存の `tests/Feature/Auth/*`（Breezeスキャフォールド）を活用
- **テストカバレッジ70%以上**: 85.1%（Xdebugで計測）
- **エッジケース**: 存在しないIDへのアクセス、未認証アクセス、二重いいねなど
- **バリデーションテスト**: 必須項目チェック、文字数の境界値

## 練習課題1: ユニットテスト（Service層）

`tests/Unit/Services/PostServiceTest.php` にて、`PostRepositoryInterface` をモック化し、
正常系・異常系・境界値の観点でテストを実施。

## 練習課題2: Feature Test

- ユーザー登録・ログイン・ログアウト: 既存のBreeze認証テストを活用
- 投稿のCRUD・認可: `tests/Feature/PostControllerTest.php` で新規実装

## 練習課題3: TDD実践（いいね機能）

「いいね機能」をTDD（Red→Green→Refactor）で追加。

1. **Red**: `tests/Unit/Services/PostServiceLikeTest.php`、`tests/Feature/PostLikeTest.php` を実装なしで先に作成し、失敗を確認
2. **Green**: `post_likes` テーブルのマイグレーション、`Post::likedByUsers()`、
   `PostRepositoryInterface::toggleLike()/likesCount()`、`PostService::toggleLike()`、
   `PostController::toggleLike()`、ルート追加で最小実装
3. **Refactor**: 「いいね済みか」の判定ロジックを `Post::isLikedBy()` としてモデルに切り出し、
   Service層の戻り値を `['liked' => bool, 'likes_count' => int]` に統一

## 静的解析・セキュリティスキャン

- **ESLint**: `eslint.config.js` を追加し、`npm run lint` で `resources/js` を静的解析（エラーなし）
- **composer audit**: 依存パッケージ（guzzle, commonmark等）を更新し、脆弱性0件を達成
- **npm audit**: 6件→1件（低リスクかつVite本体側の依存関係で対応不可）まで削減
- **CI**: `.github/workflows/ci.yml` で、push時にテスト・カバレッジ・composer audit・ESLint・npm auditを自動実行

## セットアップ

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
```

## テスト実行

```bash
php artisan test
```

## カバレッジ計測

```bash
XDEBUG_MODE=coverage php artisan test --coverage
```