# makeshop-smarty-emulator

![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Smarty](https://img.shields.io/badge/Template-Smarty-orange?style=for-the-badge&logo=smarty&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-2496ED?style=for-the-badge&logo=docker&logoColor=white)

![Modified by Claude](https://img.shields.io/badge/Modified%20by-Claude-D97757?style=flat-square&logo=claude)
![Modified by Gemini](https://img.shields.io/badge/Modified%20by-Gemini-4285F4?style=flat-square&logo=google-gemini)

本ツールは、GMO makeshopのデザインテンプレート開発を支援するためのSmartyデザインテンプレートエミュレータです（非公式）。

[do-mu-oi/makeshop-smarty](https://github.com/do-mu-oi/makeshop-smarty) をベースに、機能拡張と利便性の向上を図っています。

## 主要機能

- **CDARファイルへの完全対応**:
  - MakeShopからエクスポートされたcdarファイルをほぼそのまま利用可能です。
  - リモートCDARファイルのレンダリングにも対応しており、URL指定で自動ダウンロード、展開、レンダリングを実行します。
- **文字エンコーディングの自動判別**:
  - EUC-JPおよびUTF-8テンプレートに対応。ファイル内容から自動判別し、内部的にUTF-8へ変換することで文字化けを解消します。
- **makeshop独自Smartyタグへの対応**:
  - [リファレンスに記載の一部独自タグ](https://reference.makeshop.jp/creator-mode/contents/modifier/index.html)に対応しています。
- **開発を促進するWeb UI**:
  - 複数のデザインセット選択画面と、各デザインセットのテンプレート一覧表示機能を提供します。
- **APIによる動的テンプレートレンダリング**:
  - `POST /api/render` エンドポイントで、デザインセット名、テンプレート名、埋め込みデータをJSON形式で指定し、レンダリングされたHTMLをJSONで返却します。
  - アセット（CSS/JS）のインライン化も自動で行われます。
- **静的ファイルの配信**:
  - デザインセット内のCSS、JavaScript、画像ファイルなどを適切に配信します。
  - 文字エンコーディングの自動変換もサポートしており、EUC-JPで記述されたファイルも利用可能です。

## セットアップ

```bash
$ git clone https://github.com/Dosugamea/makeshop-smarty-emulator.git
$ cd makeshop-smarty-emulator/
$ docker-compose up
```

上記コマンド実行後、http://localhost:8080 でアクセス可能になります。

### Linux環境におけるPermission Errorへの対処

一部のLinux環境では、コンテナ内のApache実行ユーザーをコンテナ実行ユーザーに合わせる必要があります。エラーが発生した場合、以下のコマンドを試行してください。

```bash
$ docker exec -it makeshop-smarty_php_1 bash
# コンテナ実行ユーザーが 1000:1000 の場合
$ usermod -u 1000 www-data ; groupmod -g 1000 www-data ; /etc/init.d/apache2 reload
```

## 使用方法

### 1. ローカルでの開発 (Web UIの利用)

#### デザインセットの配置
デザインセットフォルダ（例：`designset-20250706003155-cdar`）を`html`フォルダ内に配置してください。

#### サンプルデータの作成
デザインセットフォルダ内に `data.json` ファイルを作成し、テンプレートで使用するサンプルデータを定義してください。

```json
{
  "shop": {
    "name": "サンプルショップ",
    "copyright": "© 2024 Sample Shop",
    "logo_url": "/images/logo.png"
  },
  "page": {
    "title": "ページタイトル",
    "css": "/designsets/YOUR_DESIGN_SET/standard/css/common.css",
    "javascript": "/designsets/YOUR_DESIGN_SET/standard/javascript/common.js"
  },
  "new_item": {
    "list": [
      {
        "name": "商品名",
        "price": 1000,
        "image_L": "/images/item.jpg"
      }
    ]
  }
}
```

#### ディレクトリ構成

以下のディレクトリ構成を推奨します。

```
makeshop-smarty/
└── html/                                    # Apacheドキュメントルート
    ├── designset-YYYYMMDDHHMMSS-NAME-extracted/  # デザインセットフォルダを配置する場所
    │   ├── config.json                           # デザインセット設定ファイル
    │   ├── data.json                            # テンプレートに引き渡すサンプルデータ
    │   ├── _module_/                            # モジュールテンプレート
    │   │   ├── header.html
    │   │   ├── footer.html
    │   │   ├── side.html
    │   │   └── ...
    │   └── standard/
    │       ├── html/                            # メインテンプレート
    │       │   ├── top.html
    │       │   ├── item.html
    │       │   ├── cart.html
    │       │   └── ...
    │       ├── css/                             # スタイルシート
    │       │   ├── common.css
    │       │   ├── item.css
    │       │   └── ...
    │       └── javascript/                      # JavaScriptファイル
    │           ├── common.js
    │           ├── item.js
    │           └── ...
    ├── index.php                               # デザインセット選択用Web UIファイル
    ├── makeshop.php                            # Smartyエンジン本体（テンプレートレンダリング、APIリクエスト処理）
    ├── api.php                                 # テンプレートレンダリングAPIのエントリポイント
    └── .htaccess                               # URLルーティング、静的ファイルキャッシュ設定
```

##### 遵守事項

*   デザインセットフォルダ名は、`designset-` で開始してください。
*   デザインセットフォルダは、`html/`フォルダ内に配置してください。
*   テンプレートファイルは、`.html`拡張子を使用してください。
*   `data.json`ファイルは、有効なJSON形式で記述してください。
*   (APIを使用しない場合) 静的ファイル（画像ファイルなど）は、適切なディレクトリ（例: `standard/images/`）に配置してください。

#### アクセス方法
1.  **デザインセット選択**: `http://localhost:8080/` にアクセスすると、デザインセットの一覧が表示されます。
2.  **テンプレート表示**: 目的のデザインセットを選択し、表示したいテンプレートをクリックしてください。

##### 静的ファイルへのアクセス
CSS、JavaScript、画像ファイルは、以下のURLで直接アクセス可能です。

```
http://localhost:8080/designsets/あなたのデザインセット名/standard/css/common.css
http://localhost:8080/designsets/あなたのデザインセット名/standard/javascript/common.js
http://localhost:8080/designsets/あなたのデザインセット名/standard/images/your_image.png
```

### 2. APIでの利用 (プログラムからのテンプレートレンダリング)

*   エンドポイント: `POST http://localhost:8080/api/render`
*   Content-Type: `application/json`

#### リクエストボディの例

```json
{
  "designset": "designset-20250706003155-cdar",
  "template": "top.html",
  "data": {
    "shop": {
      "name": "API経由のショップ名",
      "copyright": "© 2024 API Rendered"
    },
    "page": {
      "title": "APIで動的にレンダリングされたページ"
    }
  }
}
```

*   `designset` (string, 任意): レンダリング対象のデザインセットフォルダ名。省略した場合、最初に見つかったデザインセットが使用されます。
*   `cdar_url` (string, 任意): リモート`.cdar`ファイルのURL。指定した場合、ローカルのデザインセットよりも優先され、リモートのCDARファイルがダウンロードされてレンダリングされます。
*   `template` (string, 必須): レンダリング対象のテンプレートファイル名 (例: `item.html`)。
*   `data` (object, 任意): テンプレートに引き渡す追加データ。`data.json`のデータに上書きされます。

#### レスポンスボディの例 (成功時)

```json
{
  "status": "ok",
  "page": "<!-- レンダリングされたHTML/CSS/JavaScriptコンテンツ -->"
}
```

#### レスポンスボディの例 (エラー時)

```json
{
  "status": "fail",
  "reason": "Smartyなどのエラーメッセージ",
  "reason_code": 400
}
```

## デバッグガイド

### データ構造の確認

テンプレートに引き渡されているデータ構造を確認したい場合は、テンプレート名を指定せずにアクセスしてください。

```
http://localhost:8080/makeshop.php?designset=YOUR_DESIGN_SET
```

これにより、data.jsonで設定された利用可能なデータ構造が全て表示され、テンプレート開発に役立ちます。

### エラーの確認

*   ファイルが見つからない場合は、ブラウザに404エラーが表示されます。パスが正しいか確認してください。
*   データに問題がある場合や、PHP処理でエラーが発生した場合は、PHPのエラーが表示されます。関連するログも確認してください。

## ライセンス

本プロジェクトのライセンスは、以下の通りです。

*   [MIT License (do-mu-oi/makeshop-smarty)](./LICENSE)
*   [MIT License (Dosugamea/makeshop-smarty-emulator)](./LICENSE)