<?php
/**
 * MakeShop Smarty Template Engine
 * 
 * デザインセットのテンプレートをレンダリングするためのライブラリ
 * EUC-JP/UTF-8エンコーディング対応、Smarty独自モディファイア付き
 */

require_once('/app/vendor/smarty/smarty/libs/Smarty.class.php');

define('LEFT_DELIMITER', '<{');
define('RIGHT_DELIMITER', '}>');

// =============================================================================
// ユーティリティ関数
// =============================================================================

/**
 * JSONファイルを読み込んで配列として返す
 */
function load_json(string $filename): array {
    if (!is_file($filename)) {
        return [];
    }

    $json = file_get_contents($filename);
    if ($json === false) {
        return [];
    }

    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

/**
 * ファイルの文字エンコーディングを判別する
 */
function detect_file_encoding(string $filename): string {
    if (!is_file($filename)) {
        return 'UTF-8'; // デフォルト
    }

    $content = file_get_contents($filename);
    if ($content === false) {
        return 'UTF-8';
    }

    // より詳細な文字エンコーディング判別
    $encodings = ['UTF-8', 'EUC-JP', 'SJIS', 'ASCII', 'JIS'];
    $detected = mb_detect_encoding($content, $encodings, true);

    if ($detected === false) {
        // BOMをチェック
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            return 'UTF-8';
        }

        // HTMLファイルのmeta charset をチェック
        if (preg_match('/<meta[^>]+charset\s*=\s*["\']?([^"\'\s>]+)/i', $content, $matches)) {
            $charset = strtoupper($matches[1]);
            if (strpos($charset, 'EUC') !== false || strpos($charset, 'EUC-JP') !== false) {
                return 'EUC-JP';
            }
            if (strpos($charset, 'UTF-8') !== false) {
                return 'UTF-8';
            }
            if (strpos($charset, 'SHIFT_JIS') !== false || strpos($charset, 'SJIS') !== false) {
                return 'SJIS';
            }
        }

        // 判別できない場合は EUC-JP をデフォルトとする（MakeShopデザインセットの標準）
        return 'EUC-JP';
    }

    return $detected;
}

/**
 * 必要に応じて文字エンコーディングを変換する
 */
function convert_encoding_if_needed(string $content, string $from_encoding, string $to_encoding = 'UTF-8'): string {
    if ($from_encoding === $to_encoding) {
        return $content;
    }

    $converted = mb_convert_encoding($content, $to_encoding, $from_encoding);
    return $converted !== false ? $converted : $content;
}

// =============================================================================
// データ読み込み関数
// =============================================================================

/**
 * デザインセットのデータを読み込む
 */
function load_design_set_data(string $designSetPath): array {
    $data = [];

    // data.json を読み込み
    $dataJsonPath = $designSetPath . '/data.json';
    if (is_file($dataJsonPath)) {
        $data = array_merge($data, load_json($dataJsonPath));
    }

    // config.json から設定情報を読み込み
    $configJsonPath = $designSetPath . '/config.json';
    if (is_file($configJsonPath)) {
        $config = load_json($configJsonPath);
        if (!empty($config)) {
            $data['config'] = $config;
        }
    }

    return $data;
}

/**
 * モジュールファイルを読み込んでレンダリングする
 */
function load_modules(string $modulePath, array $data): array {
    $modules = [];
    if (!is_dir($modulePath)) {
        return $modules;
    }

    $smarty = new Smarty();
    $smarty->left_delimiter = LEFT_DELIMITER;
    $smarty->right_delimiter = RIGHT_DELIMITER;

    // MakeShop独自モディファイアを登録
    register_makeshop_modifiers($smarty);

    $smarty->assign($data, null, true);

    $moduleFiles = glob($modulePath . '*.html');
    if ($moduleFiles === false) {
        return $modules;
    }

    foreach ($moduleFiles as $filename) {
        if (!is_file($filename)) {
            continue;
        }

        if (preg_match('/([^\/]+)\.html$/i', $filename, $match)) {
            $id = $match[1];

            // ファイルのエンコーディングを判別
            $moduleEncoding = detect_file_encoding($filename);

            // モジュールファイルがEUC-JPの場合、一時的にUTF-8に変換してSmartyで処理
            if ($moduleEncoding === 'EUC-JP') {
                $moduleContent = file_get_contents($filename);
                if ($moduleContent !== false) {
                    $utf8Content = mb_convert_encoding($moduleContent, 'UTF-8', 'EUC-JP');

                    // 一時ファイルを作成してSmartyで処理
                    $tempFile = tempnam(sys_get_temp_dir(), 'smarty_module_');
                    if ($tempFile !== false) {
                        file_put_contents($tempFile, $utf8Content);
                        $modules[$id] = $smarty->fetch($tempFile);
                        unlink($tempFile);
                    }
                }
            } else {
                $modules[$id] = $smarty->fetch($filename);
            }
        }
    }

    return $modules;
}

/**
 * ページ共通データを設定する
 */
function setup_page_data(string $designSetName): array {
    return [
        'page' => [
            'title' => 'デザインテンプレート開発環境',
            'description' => 'MakeShop デザインテンプレート開発環境',
            'css' => "/designsets/{$designSetName}/standard/css/common.css",
            'javascript' => "/designsets/{$designSetName}/standard/javascript/common.js",
            'canonical_url' => 'http://localhost:8080'
        ],
        'shop' => [
            'name' => 'サンプルショップ',
            'copyright' => '© 2024 Sample Shop',
            'address' => '東京都渋谷区',
            'tel' => '03-1234-5678',
            'favicon_url' => '/favicon.ico',
            'logo_url' => '',
            'is_point_enabled' => true,
            'is_member_entry_enabled' => true
        ],
        'url' => [
            'top' => '/',
            'cart' => '/cart',
            'login' => '/login',
            'logout' => '/logout',
            'member_entry' => '/member_entry',
            'mypage' => '/mypage',
            'favorite' => '/favorite',
            'company' => '/company',
            'contract' => '/contract',
            'policy' => '/policy',
            'guide' => '/guide',
            'support' => '/support',
            'news' => '/news',
            'mail_magazine' => '/mail_magazine'
        ],
        'member' => [
            'is_logged_in' => false
        ],
        'cart' => [
            'has_item' => false,
            'total_quantity' => 0
        ],
        'makeshop' => [
            'head' => '',
            'body_top' => '',
            'body_bottom' => ''
        ]
    ];
}

/**
 * テンプレートと同名のCSSファイルが存在する場合、linkタグを生成する
 */
function generate_additional_css_link(string $designSetName, string $templateName): string {
    // テンプレート名から拡張子を除去
    $templateBaseName = pathinfo($templateName, PATHINFO_FILENAME);

    // 同名のCSSファイルが存在するかチェック
    $cssPath = "{$designSetName}/standard/css/{$templateBaseName}.css";

    if (is_file($cssPath)) {
        // CSSファイルが存在する場合、linkタグを生成
        $cssUrl = "/designsets/{$designSetName}/standard/css/{$templateBaseName}.css";
        return "<link href=\"{$cssUrl}\" rel=\"stylesheet\">\n";
    }

    return '';
}

// =============================================================================
// Smarty モディファイア関数
// =============================================================================

/**
 * HTMLタグを除去して文字数制限を適用する
 */
function smarty_modifier_cut_html(string $string, int $length = 100, string $etc = '...'): string {
    // HTMLタグを除去
    $string = strip_tags($string);

    // 文字数制限
    if (mb_strlen($string, 'UTF-8') > $length) {
        $string = mb_substr($string, 0, $length, 'UTF-8') . $etc;
    }

    return $string;
}

/**
 * 数値を3桁ごとにカンマ区切りで表示する
 */
function smarty_modifier_number_format($number, int $decimals = 0, string $dec_point = '.', string $thousands_sep = ','): string {
    if (!is_numeric($number)) {
        return (string)$number;
    }

    return number_format((float)$number, $decimals, $dec_point, $thousands_sep);
}

/**
 * 配列の要素数を取得する
 */
function smarty_modifier_count($array): int {
    if (is_array($array)) {
        return count($array);
    }
    if (is_countable($array)) {
        return count($array);
    }
    return 0;
}

/**
 * 改行コードを<br>タグに変換する
 */
function smarty_modifier_nl2br(string $string): string {
    return nl2br($string);
}

/**
 * 文字列をエスケープする
 */
function smarty_modifier_escape(string $string, string $type = 'html'): string {
    switch (strtolower($type)) {
        case 'html':
            return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
        case 'json':
            return json_encode($string, JSON_UNESCAPED_UNICODE) ?: $string;
        case 'url':
            return urlencode($string);
        case 'javascript':
        case 'js':
            return addslashes($string);
        default:
            // デフォルトはHTML
            return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Smartyにカスタムモディファイアを登録する
 */
function register_makeshop_modifiers(Smarty $smarty): void {
    $smarty->registerPlugin('modifier', 'cut_html', 'smarty_modifier_cut_html');
    $smarty->registerPlugin('modifier', 'number_format', 'smarty_modifier_number_format');
    $smarty->registerPlugin('modifier', 'count', 'smarty_modifier_count');
    $smarty->registerPlugin('modifier', 'nl2br', 'smarty_modifier_nl2br');
    $smarty->registerPlugin('modifier', 'escape', 'smarty_modifier_escape');
}

// =============================================================================
// メイン処理関数
// =============================================================================

/**
 * テンプレートをレンダリングして結果を返す
 *
 * @param string $designSetName デザインセット名
 * @param string $templateName テンプレート名
 * @param array $additionalData 追加データ（data.jsonに上書きマージされる）
 * @return string レンダリング結果
 * @throws Exception エラーが発生した場合
 */
function render_template(string $designSetName, string $templateName, array $additionalData = []): string {
    // デザインセットのパスを検証
    $designSetPath = $designSetName;
    if (!is_dir($designSetPath)) {
        throw new Exception("デザインセットフォルダが見つかりません: {$designSetPath}");
    }

    // テンプレートファイルのパスを検証
    $templatePath = "{$designSetPath}/standard/html/{$templateName}";
    if (!is_file($templatePath)) {
        throw new Exception("テンプレートファイルが見つかりません: {$templatePath}");
    }

    $templateEncoding = detect_file_encoding($templatePath);

    // データの読み込みとマージ
    $data = setup_page_data($designSetName);
    $data = array_merge($data, load_design_set_data($designSetPath));
    if (!empty($additionalData)) {
        // 追加データで既存のデータを上書き
        $data = array_replace_recursive($data, $additionalData);
    }

    // テンプレート固有のCSSファイルがあれば、<head>内に追加
    $additionalCss = generate_additional_css_link($designSetName, $templateName);
    if ($additionalCss) {
        if (!isset($data['makeshop']['head'])) {
            $data['makeshop']['head'] = '';
        }
        $data['makeshop']['head'] .= $additionalCss;
    }

    // モジュールを読み込む
    $modulePath = $designSetPath . '/_module_/';
    $data['module'] = load_modules($modulePath, $data);

    // Smarty の設定
    $smarty = new Smarty();
    $smarty->left_delimiter = LEFT_DELIMITER;
    $smarty->right_delimiter = RIGHT_DELIMITER;
    register_makeshop_modifiers($smarty);
    $smarty->assign($data, null, true);

    // テンプレートを描画して文字列として取得
    if ($templateEncoding === 'EUC-JP') {
        $templateContent = file_get_contents($templatePath);
        if ($templateContent === false) {
            throw new Exception("テンプレートファイルの読み込みに失敗しました: {$templatePath}");
        }

        $utf8Content = mb_convert_encoding($templateContent, 'UTF-8', 'EUC-JP');
        $tempFile = tempnam(sys_get_temp_dir(), 'smarty_template_');
        if ($tempFile === false) {
            throw new Exception("一時ファイルの作成に失敗しました");
        }
        file_put_contents($tempFile, $utf8Content);
        $output = $smarty->fetch($tempFile);
        unlink($tempFile);
    } else {
        $output = $smarty->fetch($templatePath);
    }
    return $output;
}

/**
 * 静的ファイルを処理する
 *
 * @param string $designSetName デザインセット名
 * @param string $staticPath 静的ファイルのパス
 */
function serve_static_file(string $designSetName, string $staticPath): void {
    $designSetPath = $designSetName;
    $fullPath = $designSetPath . '/' . $staticPath;

    if (!is_file($fullPath)) {
        header("HTTP/1.0 404 Not Found");
        echo "File not found: {$staticPath}";
        exit;
    }

    $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

    // 静的ファイルのエンコーディングも判別
    $staticEncoding = detect_file_encoding($fullPath);

    switch ($ext) {
        case 'css':
            header('Content-Type: text/css; charset=utf-8');
            // CSS内容がEUC-JPの場合はUTF-8に変換して出力
            if ($staticEncoding === 'EUC-JP') {
                $content = file_get_contents($fullPath);
                if ($content !== false) {
                    echo mb_convert_encoding($content, 'UTF-8', 'EUC-JP');
                }
            } else {
                readfile($fullPath);
            }
            break;
        case 'js':
            header('Content-Type: application/javascript; charset=utf-8');
            // JavaScript内容がEUC-JPの場合はUTF-8に変換して出力
            if ($staticEncoding === 'EUC-JP') {
                $content = file_get_contents($fullPath);
                if ($content !== false) {
                    echo mb_convert_encoding($content, 'UTF-8', 'EUC-JP');
                }
            } else {
                readfile($fullPath);
            }
            break;
        case 'jpg':
        case 'jpeg':
            header('Content-Type: image/jpeg');
            readfile($fullPath);
            break;
        case 'png':
            header('Content-Type: image/png');
            readfile($fullPath);
            break;
        case 'gif':
            header('Content-Type: image/gif');
            readfile($fullPath);
            break;
        case 'svg':
            header('Content-Type: image/svg+xml');
            readfile($fullPath);
            break;
        default:
            header('Content-Type: application/octet-stream');
            readfile($fullPath);
    }

    exit;
}

// =============================================================================
// 直接実行時の処理
// =============================================================================

// 直接実行された場合のみ、従来の処理を実行
if (basename($_SERVER['PHP_SELF']) === 'makeshop.php') {
    // デザインセットの取得
    $designSet = $_GET['designset'] ?? '';
    $template = $_GET['template'] ?? '';

    if (empty($designSet)) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "Error: デザインセットが指定されていません。\n";
        echo "使用方法: makeshop.php?designset=DESIGN_SET_NAME&template=TEMPLATE_NAME.html";
        exit;
    }

    $designSetPath = $designSet;
    if (!is_dir($designSetPath)) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "Error: デザインセットフォルダが見つかりません: {$designSetPath}";
        exit;
    }

    // 静的ファイルのアクセス設定（CSS、JavaScript、画像など）
    if (isset($_GET['static'])) {
        serve_static_file($designSet, $_GET['static']);
        // serve_static_file内でexit処理される
    }

    // テンプレートの表示
    if (!empty($template)) {
        // 常にUTF-8でHTMLを出力（テンプレート内の<meta charset="utf-8">に対応）
        header('Content-Type: text/html; charset=utf-8');
        try {
            echo render_template($designSet, $template);
        } catch (Exception $e) {
            header('Content-Type: text/plain; charset=utf-8');
            echo "Error: " . $e->getMessage();
        }
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        // データの読み込み
        $data = array_merge(
            setup_page_data($designSet),
            load_design_set_data($designSetPath)
        );
        // モジュールの読み込み
        $modulePath = $designSetPath . '/_module_/';
        $data['module'] = load_modules($modulePath, $data);

        echo "利用可能なデータ (エンコーディング判別機能付き):\n";
        echo "デザインセット: {$designSet}\n";
        echo "出力エンコーディング: UTF-8 (固定)\n\n";
        print_r($data);
    }
}
?>