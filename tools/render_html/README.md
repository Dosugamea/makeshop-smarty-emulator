# HTML to Image API

HTMLコンテンツを画像に変換するシンプルなAPIサーバーです。

## 機能

- HTMLをPNG/JPEG画像に変換
- Base64エンコードされた画像を返す
- カスタマイズ可能な画像サイズと品質
- Docker対応

## API仕様

### POST /render

HTMLコンテンツを画像に変換します。

#### リクエスト

```json
{
  "page": "<!doctype html><html><head><title>Test</title></head><body><h1>Hello World!</h1></body></html>",
  "width": 1200,
  "height": 800,
  "type": "png",
  "quality": 80
}
```

#### パラメータ

- `page` (必須): 変換するHTMLコンテンツ
- `width` (オプション): 画像の幅（デフォルト: 1200px）
- `height` (オプション): 画像の高さ（デフォルト: 800px）
- `type` (オプション): 画像形式 "png" または "jpeg"（デフォルト: "png"）
- `quality` (オプション): JPEG品質 1-100（デフォルト: 80）

#### レスポンス

```json
{
  "success": true,
  "image": "iVBORw0KGgoAAAANSUhEUgAA...",
  "format": "png",
  "message": "Image rendered successfully"
}
```

### GET /health

サーバーの稼働状況を確認します。

#### レスポンス

```json
{
  "status": "OK",
  "message": "HTML to Image API is running!"
}
```

## 使用方法

### ローカル開発

```bash
# 依存関係をインストール
npm install

# サーバーを起動
npm start

# 開発モード（ホットリロード）
npm run dev
```

### Docker

```bash
# Dockerイメージをビルド
docker build -t html-to-image-api .

# コンテナを起動
docker run -p 3000:3000 html-to-image-api
```

### Docker Compose

```yaml
version: '3.8'
services:
  html-to-image-api:
    build: .
    ports:
      - "3000:3000"
    environment:
      - PORT=3000
```

## 使用例

### cURLでのテスト

```bash
curl -X POST http://localhost:3000/render \
  -H "Content-Type: application/json" \
  -d '{
    "page": "<!doctype html><html><head><title>Test</title></head><body><h1 style=\"color: blue;\">Hello World!</h1></body></html>",
    "width": 800,
    "height": 600,
    "type": "png"
  }'
```

### JavaScriptでの使用例

```javascript
const response = await fetch('http://localhost:3000/render', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    page: '<!doctype html><html><head><title>Test</title></head><body><h1>Hello World!</h1></body></html>',
    width: 1200,
    height: 800,
    type: 'png'
  })
});

const result = await response.json();
if (result.success) {
  // Base64画像データを使用
  const imageData = `data:image/${result.format};base64,${result.image}`;
  // 画像を表示または保存
}
```

## 注意事項

- 大きなHTMLコンテンツの場合、処理に時間がかかる場合があります
- Puppeteerを使用しているため、メモリ使用量が多くなる可能性があります
- 本番環境では適切なリソース制限を設定してください

## ライセンス

MIT