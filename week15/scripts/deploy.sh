#!/usr/bin/env bash
#
# デプロイスクリプト（学習用シミュレーション）
#
# 実際のサーバー/ホスティングサービスがまだ用意されていないため、
# 「本番に転送する」部分はコマンドを画面に表示するだけのシミュレーションにしています。
# 本物のサーバーが用意できたら、echo の代わりに実際の rsync/ssh/デプロイAPI呼び出しに
# 差し替えるだけで、そのまま本番デプロイに使えます。
set -euo pipefail

ENVIRONMENT="${1:-staging}"

echo "=================================================="
echo " Deploying to: ${ENVIRONMENT}"
echo "=================================================="
echo "1. ビルド成果物を確認"
ls -la build/ 2>/dev/null || echo "  (build/ ディレクトリが見つかりません。先にビルドしてください)"

echo ""
echo "2. 本来ここで実行されるはずのコマンド（シミュレーション）:"
echo "   rsync -avz --delete ./build/ deploy@${ENVIRONMENT}.example.com:/var/www/app/"
echo "   ssh deploy@${ENVIRONMENT}.example.com 'cd /var/www/app && php artisan migrate --force'"
echo "   ssh deploy@${ENVIRONMENT}.example.com 'php artisan config:cache && php artisan route:cache'"
echo "   ssh deploy@${ENVIRONMENT}.example.com 'sudo systemctl reload php-fpm nginx'"

echo ""
echo "3. デプロイ完了（シミュレーション）: ${ENVIRONMENT} 環境へのデプロイが正常に完了しました"
echo "=================================================="
