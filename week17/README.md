# Week17 実施結果まとめ

対象プロジェクト: `laravel-docker-app/week9/基本課題`(投稿一覧・PostController)

## 環境に関する注記

Lighthouseでの計測時、`/up`(DBもビューも通らない最軽量エンドポイント)ですら13秒以上かかることを確認した。
原因を切り分けた結果、プロジェクトがWindowsのファイルシステム上(`/c/Users/...`)に置かれており、
Docker Desktop(WSL2バックエンド)経由でLinuxコンテナからマウントする際のファイルI/O速度低下が
原因と判明した(Windows+Docker Desktop環境でよく知られた問題)。

この問題はアプリケーションコード・クエリ・インデックスとは無関係であり、
今回はアプリケーションロジック側の改善(N+1解消・インデックス改善)を優先して完了させた。
根本解決にはプロジェクトをWSL2のネイティブLinuxファイルシステムに移動する必要があり、
今後の環境整備課題として別途対応する。

---

## 基本課題1 / 練習課題1: N+1問題の特定と解消(実測)

### 対象
`app/Repositories/PostRepository.php` の `paginate()`、および `resources/views/posts/index.blade.php`

### 特定方法
デバッグ用ルート `/debug/n-plus-one?mode=before|after` を一時的に追加し、
`DB::enableQueryLog()` で実際に発行されたクエリ数を計測した。

### 実測結果

| | クエリ数 | 内訳 |
|---|---|---|
| **Before**(N+1あり) | **22** | posts取得(1) + user一括取得(1) + comments個別取得(20、記事ごとに1回ずつ) |
| **After**(with + withCount) | **2** | posts+コメント数を1クエリで取得(1) + user一括取得(1) |

**クエリ数を91%削減(22→2)。**

### 実装
```php
// Before
return Post::with('user')->latest()->paginate($perPage);
// ビュー側で $post->comments->count() を呼ぶたびに追加クエリが発生

// After
return Post::with('user')->withCount('comments')->latest()->paginate($perPage);
// コメント数をサブクエリで一括取得、追加クエリなし
```

ビュー側もコメント数表示に対応し、実際の投稿一覧ページで動作確認済み。

---

## 基本課題2: スロークエリの特定とインデックス改善(実測)

### 特定したクエリ
`PostRepository::paginate()` が使用している、投稿一覧のソートクエリ:
```sql
SELECT * FROM posts ORDER BY created_at DESC LIMIT 10;
```

検証用に投稿2,000件+コメント約10,000件を投入(`PostSeeder`)した上でEXPLAINを実行。

### EXPLAIN結果(実測)

| | Before(インデックスなし) | After(`posts_created_at_index`追加後) |
|---|---|---|
| `type` | **ALL**(フルテーブルスキャン) | **index** |
| `rows` | **1955**(ほぼ全件) | **10**(必要な分のみ) |
| `Extra` | **Using filesort** | **Backward index scan**(ソート不要) |

### 改善内容
```php
Schema::table('posts', function (Blueprint $table) {
    $table->index('created_at', 'posts_created_at_index');
});
```

複合インデックスではなく単一カラムのインデックスだが、`ORDER BY created_at DESC`だけで
完結するクエリのため、これだけでフルスキャン→インデックスオンリーアクセスに改善された。
1955行のフルスキャン+メモリ上ソートから、必要な10行だけを読む処理に変わった。

---

## Lighthouse計測

対象ページ: `/posts`(N+1・インデックス改善適用後)

| 指標 | スコア |
|---|---|
| Performance | 86 |
| Accessibility | 89 |
| Best Practices | 100 |
| SEO | 91 |

※上記の環境要因により、Waiting for server response(TTFB)が異常値(15秒)を示したため、
Performanceスコアはこの環境要因の影響を強く受けている。アプリケーションロジック単体の
改善効果(クエリ数91%減、フルスキャン解消)は、DBレベルの実測(EXPLAIN・クエリログ)で
別途確認済み。

---

## 未実施(今後の課題)

- 練習課題2: キャッシュ導入 → `Cache::remember`の実装は用意済みだが、今回の環境問題の切り分けを
  優先したため未反映。次回、WSL2移行と合わせて対応する。
- 練習課題3: 画像最適化(WebP)+CDN → AWSアカウント・S3バケット・CloudFrontの用意が必要なため、
  設計(`ImageOptimizationService`)のみ用意し、実インフラへの適用は未実施。
- 開発環境の根本改善: プロジェクトをWSL2のネイティブLinuxファイルシステムに移動し、
  Docker経由のファイルI/O速度を改善する。
