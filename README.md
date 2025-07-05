# makeshop-smarty-emulator

![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Smarty](https://img.shields.io/badge/Template-Smarty-orange?style=for-the-badge&logo=smarty&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-2496ED?style=for-the-badge&logo=docker&logoColor=white)

![Modified by Claude](https://img.shields.io/badge/Modified%20by-Claude-D97757?style=flat-square&logo=claude)
![Modified by Gemini](https://img.shields.io/badge/Modified%20by-Gemini-4285F4?style=flat-square&logo=google-gemini)


GMO makeshop用のsmartyデザインテンプレート開発環境です。(非公式)

このリポジトリは [do-mu-oi/makeshop-smarty](https://github.com/do-mu-oi/makeshop-smarty) をクローンし、改変したものです。

## 変更点
- 1 cdar(Creator-mode Design-set ARchive) を ほぼそのまま利用できるように
  - エクスポートしたcdarファイルをzipファイルにリネームしてhtmlフォルダ内に展開してください
- 2 EUC-JPとUTF-8の両方に対応
  - デフォルトのEUC-JPテンプレートをそのまま読み込めます
- 3 一部の独自タグに対応
  - [リファレンスに記載のある一部のタグ](https://reference.makeshop.jp/creator-mode/contents/modifier/index.html)に対応
- 3 デザインセット選択画面を追加
  - やや気の利いたデザインセット選択画面が追加されました
- 4 カラーミーショップのサポート廃止
  - (当方で利用していないため動作確認できず)

## セットアップ

```bash
$ git clone https://github.com/Dosugamea/makeshop-smarty-emulator.git
$ cd makeshop-smarty-emulator/
$ docker-compose up
```

http://localhost:8080 でアクセスできます。

### 一部 Linux 環境で Permission Error が発生する件

一部の Linux 環境ではコンテナ内の Apache 実行ユーザーをコンテナ実行ユーザーに合わせる必要があります。

```bash
$ docker exec -it makeshop-smarty_php_1 bash
# コンテナ実行ユーザー 1000:1000 の場合
$ usermod -u 1000 www-data ; groupmod -g 1000 www-data ; /etc/init.d/apache2 reload
```

## ディレクトリ構成

### デザインセット対応版の構成

htmlフォルダ内に `designset-*` フォルダを配置することで、任意のデザインセットを確認できます。

```
makeshop-smarty/
└── html/                                    # Apache document root
    ├── designset-YYYYMMDDHHMMSS-NAME-extracted/  # デザインセットフォルダ
    │   ├── config.json                           # デザインセット設定
    │   ├── data.json                            # テンプレート用サンプルデータ（★新規追加）
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
    │       └── javascript/                      # JavaScript
    │           ├── common.js
    │           ├── item.js
    │           └── ...
    ├── index.php                               # デザインセット選択画面
    ├── makeshop.php                            # テンプレートエンジン
    └── .htaccess                               # リライトルール
```

## 使用方法

### 1. デザインセットの配置

デザインセットフォルダ（例：`designset-20250706003155-cdar`）をhtmlフォルダ内に配置します。

### 2. サンプルデータの作成

デザインセットフォルダ内に `data.json` ファイルを作成し、テンプレートで使用するサンプルデータを定義します。

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

### 3. アクセス方法

1. **デザインセット選択**: `http://localhost:8080/` にアクセス
2. **テンプレート表示**: デザインセットを選択後、表示したいテンプレートをクリック

### 4. 静的ファイルへのアクセス

CSS、JavaScript、画像ファイルは以下のURLでアクセスできます：

```
http://localhost:8080/designsets/デザインセット名/standard/css/common.css
http://localhost:8080/designsets/デザインセット名/standard/javascript/common.js
```

## テンプレート記法

### Smarty デリミター

```smarty
<{$変数名}>              # 変数出力
<{if $条件}>...</{/if}>   # 条件分岐
<{section name=i loop=$配列}>...</{/section}>  # ループ
```

### モジュール呼び出し

```smarty
<{$module.header}>   # _module_/header.html を読み込み
<{$module.footer}>   # _module_/footer.html を読み込み
<{$module.side}>     # _module_/side.html を読み込み
```

### よく使用される変数

```smarty
<{$shop.name}>           # ショップ名
<{$page.title}>          # ページタイトル
<{$page.css}>            # CSSファイルのパス
<{$page.javascript}>     # JavaScriptファイルのパス
<{$new_item.list}>       # 新商品リスト
<{$recommend_item.list}> # おすすめ商品リスト
<{$category_menu.list}>  # カテゴリーメニュー
```

## デバッグ

### データ構造の確認

テンプレートパラメータを指定せずにアクセスすると、利用可能なデータ構造が表示されます：

```
http://localhost:8080/makeshop.php?designset=YOUR_DESIGN_SET
```

### エラーの確認

- ファイルが見つからない場合は404エラーが表示されます
- データの構造に問題がある場合はPHPエラーが表示されます

## 注意事項

- デザインセットフォルダ名は `designset-` で始まる必要があります
- デザインセットフォルダは `html/` フォルダ内に配置してください
- テンプレートファイルは `.html` 拡張子を使用します
- `data.json` ファイルは有効なJSON形式である必要があります
- 画像ファイルなどの静的ファイルは適切なディレクトリに配置してください

## ライセンス

- [MIT License (do-mu-oi/makeshop-smarty)](./LICENSE)
- [MIT License (Dosugamea/makeshop-smarty-emulator)](./LICENSE)