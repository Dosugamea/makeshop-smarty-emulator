<?php
require_once('/app/vendor/smarty/smarty/libs/Smarty.class.php');

define('LEFT_DELIMITER', '<{');
define('RIGHT_DELIMITER', '}>');

function load_json($filename) {
    if (is_file($filename)) {
        $json = file_get_contents($filename);
        return json_decode($json, true);
    }

    return [];
}

function detect_file_encoding($filename) {
    if (!is_file($filename)) {
        return 'UTF-8'; // デフォルト
    }
    
    $content = file_get_contents($filename);
    
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

function convert_encoding_if_needed($content, $from_encoding, $to_encoding = 'UTF-8') {
    if ($from_encoding === $to_encoding) {
        return $content;
    }
    
    return mb_convert_encoding($content, $to_encoding, $from_encoding);
}

function load_design_set_data($designSetPath) {
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
        $data['config'] = $config;
    }
    
    return $data;
}

function load_modules($modulePath, $data) {
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

    foreach (glob($modulePath . '*.html') as $filename) {
        if (is_file($filename)) {
            preg_match('/([^\/]+)\.html$/i', $filename, $match);
            $id = $match[1];
            
            // ファイルのエンコーディングを判別
            $moduleEncoding = detect_file_encoding($filename);
            
            // モジュールファイルがEUC-JPの場合、一時的にUTF-8に変換してSmartyで処理
            if ($moduleEncoding === 'EUC-JP') {
                $moduleContent = file_get_contents($filename);
                $utf8Content = mb_convert_encoding($moduleContent, 'UTF-8', 'EUC-JP');
                
                // 一時ファイルを作成してSmartyで処理
                $tempFile = tempnam(sys_get_temp_dir(), 'smarty_module_');
                file_put_contents($tempFile, $utf8Content);
                
                $modules[$id] = $smarty->fetch($tempFile);
                
                // 一時ファイルを削除
                unlink($tempFile);
            } else {
                $modules[$id] = $smarty->fetch($filename);
            }
        }
    }

    return $modules;
}

function setup_page_data($designSetName) {
    // ページ共通データの設定
    $pageData = [
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
    
    return $pageData;
}

function generate_additional_css_link($designSetName, $templateName) {
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

// MakeShop独自のSmartyモディファイア
function smarty_modifier_cut_html($string, $length = 100, $etc = '...') {
    // HTMLタグを除去
    $string = strip_tags($string);
    
    // 文字数制限
    if (mb_strlen($string, 'UTF-8') > $length) {
        $string = mb_substr($string, 0, $length, 'UTF-8') . $etc;
    }
    
    return $string;
}

function smarty_modifier_number_format($number, $decimals = 0, $dec_point = '.', $thousands_sep = ',') {
    // 数字を3桁ごとにカンマ区切りで表示
    return number_format($number, $decimals, $dec_point, $thousands_sep);
}

function smarty_modifier_count($array) {
    // 配列の要素数を取得
    if (is_array($array)) {
        return count($array);
    }
    return 0;
}

function smarty_modifier_nl2br($string) {
    // 改行コードの前に<br>タグを追加
    return nl2br($string);
}

function smarty_modifier_escape($string, $type = 'html') {
    // 文字列をエスケープ
    switch (strtolower($type)) {
        case 'html':
            return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
        case 'json':
            return json_encode($string, JSON_UNESCAPED_UNICODE);
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

function register_makeshop_modifiers($smarty) {
    // MakeShop独自モディファイアを登録
    $smarty->registerPlugin('modifier', 'cut_html', 'smarty_modifier_cut_html');
    $smarty->registerPlugin('modifier', 'number_format', 'smarty_modifier_number_format');
    $smarty->registerPlugin('modifier', 'count', 'smarty_modifier_count');
    $smarty->registerPlugin('modifier', 'nl2br', 'smarty_modifier_nl2br');
    $smarty->registerPlugin('modifier', 'escape', 'smarty_modifier_escape');
}

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

// テンプレートファイルのエンコーディングを判別
$templateEncoding = 'UTF-8'; // デフォルト
if (!empty($template)) {
    $templatePath = $designSetPath . '/standard/html/' . $template;
    if (!is_file($templatePath)) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "Error: テンプレートファイルが見つかりません: {$templatePath}";
        exit;
    }
    
    $templateEncoding = detect_file_encoding($templatePath);
}

// データの読み込み
$data = array_merge(
    setup_page_data($designSet),
    load_design_set_data($designSetPath)
);

// テンプレートと同名のCSSファイルがある場合、makeshop.headに追加
if (!empty($template)) {
    $additionalCss = generate_additional_css_link($designSet, $template);
    if ($additionalCss) {
        $data['makeshop']['head'] .= $additionalCss;
    }
}

// モジュールの読み込み
$modulePath = $designSetPath . '/_module_/';
$data['module'] = load_modules($modulePath, $data);

// Smarty の設定
$smarty = new Smarty();
$smarty->left_delimiter = LEFT_DELIMITER;
$smarty->right_delimiter = RIGHT_DELIMITER;

// MakeShop独自モディファイアを登録
register_makeshop_modifiers($smarty);

$smarty->assign($data, null, true);

// 静的ファイルのアクセス設定（CSS、JavaScript、画像など）
if (isset($_GET['static'])) {
    $staticPath = $_GET['static'];
    $fullPath = $designSetPath . '/' . $staticPath;
    
    if (is_file($fullPath)) {
        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        
        // 静的ファイルのエンコーディングも判別
        $staticEncoding = detect_file_encoding($fullPath);
        
        switch ($ext) {
            case 'css':
                header('Content-Type: text/css; charset=utf-8');
                // CSS内容がEUC-JPの場合はUTF-8に変換して出力
                if ($staticEncoding === 'EUC-JP') {
                    $content = file_get_contents($fullPath);
                    echo mb_convert_encoding($content, 'UTF-8', 'EUC-JP');
                } else {
                    readfile($fullPath);
                }
                break;
            case 'js':
                header('Content-Type: application/javascript; charset=utf-8');
                // JavaScript内容がEUC-JPの場合はUTF-8に変換して出力
                if ($staticEncoding === 'EUC-JP') {
                    $content = file_get_contents($fullPath);
                    echo mb_convert_encoding($content, 'UTF-8', 'EUC-JP');
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
    } else {
        header("HTTP/1.0 404 Not Found");
        echo "File not found: {$staticPath}";
        exit;
    }
}

// テンプレートの表示
if (!empty($template)) {
    $templatePath = $designSetPath . '/standard/html/' . $template;
    
    // 常にUTF-8でHTMLを出力（テンプレート内の<meta charset="utf-8">に対応）
    header('Content-Type: text/html; charset=utf-8');
    
    if ($templateEncoding === 'EUC-JP') {
        // EUC-JPテンプレートファイルの場合、UTF-8に変換してからSmartyで処理
        $templateContent = file_get_contents($templatePath);
        $utf8Content = mb_convert_encoding($templateContent, 'UTF-8', 'EUC-JP');
        
        // 一時ファイルを作成してSmartyで処理
        $tempFile = tempnam(sys_get_temp_dir(), 'smarty_template_');
        file_put_contents($tempFile, $utf8Content);
        
        // 出力もUTF-8のまま（EUC-JPに戻さない）
        $smarty->display($tempFile);
        
        // 一時ファイルを削除
        unlink($tempFile);
    } else {
        // UTF-8テンプレートの場合はそのまま処理
        $smarty->display($templatePath);
    }
} else {
    header('Content-Type: text/plain; charset=utf-8');
    echo "利用可能なデータ (エンコーディング判別機能付き):\n";
    echo "デザインセット: {$designSet}\n";
    echo "テンプレートファイルエンコーディング: {$templateEncoding}\n";
    echo "出力エンコーディング: UTF-8 (固定)\n\n";
    print_r($data);
}
?>