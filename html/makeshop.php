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
 * メモリ上のJSONデータを配列として返す
 */
function load_json_from_memory(string $jsonContent): array {
    $data = json_decode($jsonContent, true);
    return is_array($data) ? $data : [];
}

/**
 * リモートファイルをダウンロードする
 */
function download_remote_file(string $url): string {
    // cURLを使用してファイルをダウンロード
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'MakeShop Template Renderer/1.0');

    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($data === false || !empty($error)) {
        throw new Exception("ファイルのダウンロードに失敗しました: {$error}");
    }

    if ($httpCode !== 200) {
        throw new Exception("ファイルのダウンロードに失敗しました: HTTP {$httpCode}");
    }

    return $data;
}

/**
 * ZIPファイルをメモリ上で展開してファイル構造を取得する
 */
function extract_zip_to_memory(string $zipData): array {
    // ZipArchiveが利用可能かチェック
    if (class_exists('ZipArchive')) {
        return extract_zip_to_memory_ziparchive($zipData);
    } else {
        return extract_zip_to_memory_phardata($zipData);
    }
}

/**
 * ZipArchiveを使用してZIPファイルをメモリ上で展開する
 */
function extract_zip_to_memory_ziparchive(string $zipData): array {
    $tempFile = tempnam(sys_get_temp_dir(), 'cdar_zip_');
    if ($tempFile === false) {
        throw new Exception("一時ファイルの作成に失敗しました");
    }

    try {
        // ZIPデータを一時ファイルに保存
        file_put_contents($tempFile, $zipData);
        $zip = new ZipArchive();
        $result = $zip->open($tempFile);
        if ($result !== TRUE) {
            throw new Exception("ZIPファイルの展開に失敗しました: エラーコード {$result}");
        }
        $files = [];
        
        // ZIP内の全ファイルを取得
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                continue;
            }

            $filename = $stat['name'];

            // ディレクトリは除外
            if (substr($filename, -1) === '/') {
                continue;
            }

            // ファイル内容を取得
            $content = $zip->getFromIndex($i);
            if ($content !== false) {
                $files[$filename] = $content;
            }
        }

        $zip->close();
        return $files;

    } finally {
        // 一時ファイルを削除
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    }
}

/**
 * PharDataを使用してZIPファイルをメモリ上で展開する（ZipArchiveが利用できない場合の代替手段）
 */
function extract_zip_to_memory_phardata(string $zipData): array {
    $tempFile = tempnam(sys_get_temp_dir(), 'cdar_zip_');
    $tempDir = sys_get_temp_dir() . '/cdar_extract_' . uniqid();
    
    if ($tempFile === false) {
        throw new Exception("一時ファイルの作成に失敗しました");
    }
    
    try {
        // ZIPデータを一時ファイルに保存
        file_put_contents($tempFile, $zipData);
        
        // 一時展開ディレクトリを作成
        if (!mkdir($tempDir, 0755, true)) {
            throw new Exception("一時ディレクトリの作成に失敗しました");
        }
        
        try {
            // PharDataでZIPファイルを展開
            $phar = new PharData($tempFile);
            $phar->extractTo($tempDir);
            
            $files = [];
            
            // 展開されたファイルを再帰的に取得
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $relativePath = str_replace($tempDir . '/', '', $file->getPathname());
                    $relativePath = str_replace('\\', '/', $relativePath); // Windows対応
                    
                    $content = file_get_contents($file->getPathname());
                    if ($content !== false) {
                        $files[$relativePath] = $content;
                    }
                }
            }
            
            return $files;
            
        } finally {
            // 一時展開ディレクトリを削除
            if (is_dir($tempDir)) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                
                foreach ($iterator as $file) {
                    if ($file->isDir()) {
                        rmdir($file->getPathname());
                    } else {
                        unlink($file->getPathname());
                    }
                }
                rmdir($tempDir);
            }
        }
        
    } finally {
        // 一時ファイルを削除
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    }
}

/**
 * メモリ上のファイル構造からデザインセット名を検出する
 */
function detect_design_set_name_from_memory(array $files): string {
    // ファイルパスからデザインセット名を推測
    foreach ($files as $path => $content) {
        // config.jsonやdata.jsonが直接ルートにある場合
        if ($path === 'config.json' || $path === 'data.json') {
            // cdarファイルの中身が直接ルートにある場合は、デフォルト名を使用
            return 'remote-designset';
        }
        
        // 従来の階層構造（designset-xxx/config.json）の場合
        if (preg_match('/^([^\/]+)\/(config\.json|data\.json)$/', $path, $matches)) {
            return $matches[1];
        }
        
        // standardフォルダが直接ルートにある場合
        if (strpos($path, 'standard/') === 0) {
            return 'remote-designset';
        }
        
        // 従来の階層構造（designset-xxx/standard/）の場合
        if (preg_match('/^([^\/]+)\/standard\//', $path, $matches)) {
            return $matches[1];
        }
    }
    
    // デフォルト名を返す
    return 'remote-designset';
}

/**
 * メモリ上のファイル内容の文字エンコーディングを判別する
 */
function detect_encoding_from_memory(string $content): string {
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

    return detect_encoding_from_memory($content);
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
 * メモリ上のファイルからデザインセットデータを読み込む
 */
function load_design_set_data_from_memory(array $files, string $designSetName): array {
    // config.jsonを読み込み
    $config = null;
    $configPath = "{$designSetName}/config.json";
    $rootConfigPath = "config.json";
    
    if (isset($files[$configPath])) {
        $config = json_decode($files[$configPath], true);
    } elseif (isset($files[$rootConfigPath])) {
        // 直接ルートにconfig.jsonがある場合
        $config = json_decode($files[$rootConfigPath], true);
    }
    
    if (!$config) {
        $config = [];
    }
    
    // data.jsonを読み込み
    $data = null;
    $dataPath = "{$designSetName}/data.json";
    $rootDataPath = "data.json";
    
    if (isset($files[$dataPath])) {
        $data = json_decode($files[$dataPath], true);
    } elseif (isset($files[$rootDataPath])) {
        // 直接ルートにdata.jsonがある場合
        $data = json_decode($files[$rootDataPath], true);
    }
    
    if (!$data) {
        $data = [];
    }
    
    return array_merge($config, $data);
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
 * メモリ上のファイルからモジュールを読み込む
 */
function load_modules_from_memory(array $files, string $designSetName): array {
    $modules = [];
    
    // モジュールディレクトリのパスを決定
    $moduleDir = "{$designSetName}/_module_/";
    $rootModuleDir = "_module_/";
    
    foreach ($files as $path => $content) {
        // 従来の階層構造の場合
        if (strpos($path, $moduleDir) === 0) {
            $relativePath = substr($path, strlen($moduleDir));
            if (pathinfo($relativePath, PATHINFO_EXTENSION) === 'html') {
                $moduleName = pathinfo($relativePath, PATHINFO_FILENAME);
                $modules[$moduleName] = $content;
            }
        }
        // 直接ルートに_module_がある場合
        elseif (strpos($path, $rootModuleDir) === 0) {
            $relativePath = substr($path, strlen($rootModuleDir));
            if (pathinfo($relativePath, PATHINFO_EXTENSION) === 'html') {
                $moduleName = pathinfo($relativePath, PATHINFO_FILENAME);
                $modules[$moduleName] = $content;
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
 * ローカルファイルからCSSとJavaScriptを直接埋め込む形式で生成する
 */
function generate_inline_assets_from_local(string $designSetPath, string $templateName): string {
    $inlineAssets = '';
    
    // CSSファイルを探して埋め込む
    $cssFiles = [];
    
    // 1. common.css を探す
    $commonCssPath = "{$designSetPath}/standard/css/common.css";
    if (is_file($commonCssPath)) {
        $cssFiles['common.css'] = $commonCssPath;
    }
    
    // 2. テンプレート固有のCSSを探す
    $cssFileName = pathinfo($templateName, PATHINFO_FILENAME) . '.css';
    $cssPath = "{$designSetPath}/standard/css/{$cssFileName}";
    if (is_file($cssPath)) {
        $cssFiles[$cssFileName] = $cssPath;
    }
    
    // 3. その他のCSSファイルも探す
    $cssDir = "{$designSetPath}/standard/css/";
    if (is_dir($cssDir)) {
        $cssGlob = glob($cssDir . '*.css');
        if ($cssGlob !== false) {
            foreach ($cssGlob as $cssFile) {
                $fileName = basename($cssFile);
                if (!isset($cssFiles[$fileName])) {
                    $cssFiles[$fileName] = $cssFile;
                }
            }
        }
    }
    
    // CSSファイルを<style>タグで埋め込む
    foreach ($cssFiles as $fileName => $filePath) {
        $content = file_get_contents($filePath);
        if ($content !== false) {
            $cssEncoding = detect_file_encoding($filePath);
            
            // EUC-JPの場合はUTF-8に変換
            if ($cssEncoding === 'EUC-JP') {
                $content = mb_convert_encoding($content, 'UTF-8', 'EUC-JP');
            }
            
            $inlineAssets .= "<style type=\"text/css\">\n";
            $inlineAssets .= "/* {$fileName} */\n";
            $inlineAssets .= $content . "\n";
            $inlineAssets .= "</style>\n";
        }
    }
    
    // JavaScriptファイルを探して埋め込む
    $jsFiles = [];
    
    // 1. common.js を探す
    $commonJsPath = "{$designSetPath}/standard/js/common.js";
    if (is_file($commonJsPath)) {
        $jsFiles['common.js'] = $commonJsPath;
    }
    
    // 2. テンプレート固有のJavaScriptを探す
    $jsFileName = pathinfo($templateName, PATHINFO_FILENAME) . '.js';
    $jsPath = "{$designSetPath}/standard/js/{$jsFileName}";
    if (is_file($jsPath)) {
        $jsFiles[$jsFileName] = $jsPath;
    }
    
    // 3. その他のJavaScriptファイルも探す
    $jsDir = "{$designSetPath}/standard/js/";
    if (is_dir($jsDir)) {
        $jsGlob = glob($jsDir . '*.js');
        if ($jsGlob !== false) {
            foreach ($jsGlob as $jsFile) {
                $fileName = basename($jsFile);
                if (!isset($jsFiles[$fileName])) {
                    $jsFiles[$fileName] = $jsFile;
                }
            }
        }
    }
    
    // JavaScriptファイルを<script>タグで埋め込む
    foreach ($jsFiles as $fileName => $filePath) {
        $content = file_get_contents($filePath);
        if ($content !== false) {
            $jsEncoding = detect_file_encoding($filePath);
            
            // EUC-JPの場合はUTF-8に変換
            if ($jsEncoding === 'EUC-JP') {
                $content = mb_convert_encoding($content, 'UTF-8', 'EUC-JP');
            }
            
            $inlineAssets .= "<script type=\"text/javascript\">\n";
            $inlineAssets .= "/* {$fileName} */\n";
            $inlineAssets .= $content . "\n";
            $inlineAssets .= "</script>\n";
        }
    }
    
    return $inlineAssets;
}

/**
 * メモリ上のファイルからCSSとJavaScriptを直接埋め込む形式で生成する
 */
function generate_inline_assets_from_memory(array $files, string $designSetName, string $templateName): string {
    $inlineAssets = '';
    
    // CSSファイルを探して埋め込む
    $cssFiles = [];
    
    // 1. common.css を探す
    $commonCssPath = "{$designSetName}/standard/css/common.css";
    $rootCommonCssPath = "standard/css/common.css";
    
    if (isset($files[$commonCssPath])) {
        $cssFiles['common.css'] = $files[$commonCssPath];
    } elseif (isset($files[$rootCommonCssPath])) {
        $cssFiles['common.css'] = $files[$rootCommonCssPath];
    }
    
    // 2. テンプレート固有のCSSを探す
    $cssFileName = pathinfo($templateName, PATHINFO_FILENAME) . '.css';
    $cssPath = "{$designSetName}/standard/css/{$cssFileName}";
    $rootCssPath = "standard/css/{$cssFileName}";
    
    if (isset($files[$cssPath])) {
        $cssFiles[$cssFileName] = $files[$cssPath];
    } elseif (isset($files[$rootCssPath])) {
        $cssFiles[$cssFileName] = $files[$rootCssPath];
    }
    
    // 3. その他のCSSファイルも探す
    foreach ($files as $path => $content) {
        if (preg_match('/^(.*\/)?standard\/css\/(.+\.css)$/', $path, $matches)) {
            $fileName = $matches[2];
            if (!isset($cssFiles[$fileName]) && $fileName !== 'common.css' && $fileName !== $cssFileName) {
                $cssFiles[$fileName] = $content;
            }
        }
    }
    
    // CSSファイルを<style>タグで埋め込む
    foreach ($cssFiles as $fileName => $content) {
        $cssEncoding = detect_encoding_from_memory($content);
        
        // EUC-JPの場合はUTF-8に変換
        if ($cssEncoding === 'EUC-JP') {
            $content = mb_convert_encoding($content, 'UTF-8', 'EUC-JP');
        }
        
        $inlineAssets .= "<style type=\"text/css\">\n";
        $inlineAssets .= "/* {$fileName} */\n";
        $inlineAssets .= $content . "\n";
        $inlineAssets .= "</style>\n";
    }
    
    // JavaScriptファイルを探して埋め込む
    $jsFiles = [];
    
    // 1. common.js を探す
    $commonJsPath = "{$designSetName}/standard/js/common.js";
    $rootCommonJsPath = "standard/js/common.js";
    
    if (isset($files[$commonJsPath])) {
        $jsFiles['common.js'] = $files[$commonJsPath];
    } elseif (isset($files[$rootCommonJsPath])) {
        $jsFiles['common.js'] = $files[$rootCommonJsPath];
    }

    // 2. テンプレート固有のJavaScriptを探す
    $jsFileName = pathinfo($templateName, PATHINFO_FILENAME) . '.js';
    $jsPath = "{$designSetName}/standard/js/{$jsFileName}";
    $rootJsPath = "standard/js/{$jsFileName}";
    
    if (isset($files[$jsPath])) {
        $jsFiles[$jsFileName] = $files[$jsPath];
    } elseif (isset($files[$rootJsPath])) {
        $jsFiles[$jsFileName] = $files[$rootJsPath];
    }
    
    // 3. その他のJavaScriptファイルも探す
    foreach ($files as $path => $content) {
        if (preg_match('/^(.*\/)?standard\/js\/(.+\.js)$/', $path, $matches)) {
            $fileName = $matches[2];
            if (!isset($jsFiles[$fileName]) && $fileName !== 'common.js' && $fileName !== $jsFileName) {
                $jsFiles[$fileName] = $content;
            }
        }
    }
    
    // JavaScriptファイルを<script>タグで埋め込む
    foreach ($jsFiles as $fileName => $content) {
        $jsEncoding = detect_encoding_from_memory($content);
    
        // EUC-JPの場合はUTF-8に変換
        if ($jsEncoding === 'EUC-JP') {
            $content = mb_convert_encoding($content, 'UTF-8', 'EUC-JP');
        }
        
        $inlineAssets .= "<script type=\"text/javascript\">\n";
        $inlineAssets .= "/* {$fileName} */\n";
        $inlineAssets .= $content . "\n";
        $inlineAssets .= "</script>\n";
    }
    
    return $inlineAssets;
}

/**
 * テンプレートをレンダリングして結果を返す
 *
 * @param string $designSetName デザインセット名
 * @param string $templateName テンプレート名
 * @param array $additionalData 追加データ（data.jsonに上書きマージされる）
 * @param bool $inlineAssets アセットをインライン化するかどうか（API用）
 * @return string レンダリング結果
 * @throws Exception エラーが発生した場合
 */
function render_template(string $designSetName, string $templateName, array $additionalData = [], bool $inlineAssets = false): string {
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

    // インライン化する場合は、外部CSSリンクを削除
    if (!$inlineAssets) {
        // テンプレート固有のCSSファイルがあれば、<head>内に追加
        $additionalCss = generate_additional_css_link($designSetName, $templateName);
        if ($additionalCss) {
            if (!isset($data['makeshop']['head'])) {
                $data['makeshop']['head'] = '';
            }
            $data['makeshop']['head'] .= $additionalCss;
        }
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
    
    // インライン化が必要な場合は、外部リンクを削除してインライン化されたアセットに置き換える
    if ($inlineAssets) {
        $inlineAssetsContent = generate_inline_assets_from_local($designSetPath, $templateName);
        if ($inlineAssetsContent) {
            $output = replace_external_assets_with_inline($output, $inlineAssetsContent);
        }
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

/**
 * メモリ上のファイル構造からテンプレートをレンダリングする（API用・UTF-8出力）
 */
function render_template_from_memory(array $files, string $designSetName, string $templateName, array $data): string {
    // テンプレートファイルを探す
    $templatePath = "{$designSetName}/standard/html/{$templateName}";
    $rootTemplatePath = "standard/html/{$templateName}";
    
    $templateContent = null;
    if (isset($files[$templatePath])) {
        $templateContent = $files[$templatePath];
    } elseif (isset($files[$rootTemplatePath])) {
        // 直接ルートにstandardがある場合
        $templateContent = $files[$rootTemplatePath];
    }
    
    if ($templateContent === null) {
        throw new Exception("テンプレートファイルが見つかりません: {$templateName}");
    }
    
    // モジュールを読み込んでレンダリング
    $modules = [];
    $moduleFiles = load_modules_from_memory($files, $designSetName);
    
    if (!empty($moduleFiles)) {
        $smarty = new Smarty();
        $smarty->left_delimiter = LEFT_DELIMITER;
        $smarty->right_delimiter = RIGHT_DELIMITER;
        
        // MakeShop独自モディファイアを登録
        register_makeshop_modifiers($smarty);
        
        // データをアサイン
        $smarty->assign($data, null, true);
        
        foreach ($moduleFiles as $moduleId => $moduleContent) {
            // ファイルのエンコーディングを判別
            $moduleEncoding = detect_encoding_from_memory($moduleContent);
            
            // モジュールファイルがEUC-JPの場合、一時的にUTF-8に変換してSmartyで処理
            if ($moduleEncoding === 'EUC-JP') {
                $utf8Content = mb_convert_encoding($moduleContent, 'UTF-8', 'EUC-JP');
                
                // 一時ファイルを作成してSmartyで処理
                $tempFile = tempnam(sys_get_temp_dir(), 'smarty_module_memory_');
                if ($tempFile !== false) {
                    file_put_contents($tempFile, $utf8Content);
                    $modules[$moduleId] = $smarty->fetch($tempFile);
                    unlink($tempFile);
                }
            } else {
                // 一時ファイルを作成してSmartyで処理
                $tempFile = tempnam(sys_get_temp_dir(), 'smarty_module_memory_');
                if ($tempFile !== false) {
                    file_put_contents($tempFile, $moduleContent);
                    $modules[$moduleId] = $smarty->fetch($tempFile);
                    unlink($tempFile);
                }
            }
        }
    }
    
    // データにモジュールを追加
    $data['module'] = $modules;
    
    // Smarty の設定
    $smarty = new Smarty();
    $smarty->left_delimiter = LEFT_DELIMITER;
    $smarty->right_delimiter = RIGHT_DELIMITER;
    
    // MakeShop独自モディファイアを登録
    register_makeshop_modifiers($smarty);
    
    // データをアサイン
    $smarty->assign($data, null, true);
    
    // テンプレートのエンコーディングを判別
    $templateEncoding = detect_encoding_from_memory($templateContent);
    
    // テンプレートファイルがEUC-JPの場合、一時的にUTF-8に変換してSmartyで処理
    if ($templateEncoding === 'EUC-JP') {
        $utf8Content = mb_convert_encoding($templateContent, 'UTF-8', 'EUC-JP');
        
        // 一時ファイルを作成してSmartyで処理
        $tempFile = tempnam(sys_get_temp_dir(), 'smarty_template_memory_');
        if ($tempFile === false) {
            throw new Exception("一時ファイルの作成に失敗しました");
        }
        
        try {
        file_put_contents($tempFile, $utf8Content);
            $rendered = $smarty->fetch($tempFile);
            
            // API用：結果をUTF-8のまま返す
            return $rendered;
            
        } finally {
            unlink($tempFile);
        }
    } else {
        // 一時ファイルを作成してSmartyで処理
        $tempFile = tempnam(sys_get_temp_dir(), 'smarty_template_memory_');
        if ($tempFile === false) {
            throw new Exception("一時ファイルの作成に失敗しました");
        }
        
        try {
            file_put_contents($tempFile, $templateContent);
            $rendered = $smarty->fetch($tempFile);
            
            // UTF-8エンコーディングを確保
            if (!mb_check_encoding($rendered, 'UTF-8')) {
                $rendered = mb_convert_encoding($rendered, 'UTF-8', 'auto');
            }
            
            return $rendered;
            
        } finally {
            unlink($tempFile);
        }
    }
}

/**
 * メモリ上のファイル構造からテンプレート固有のCSSリンクを生成する
 */
function generate_additional_css_link_from_memory(array $files, string $designSetName, string $templateName): string {
    $cssFileName = pathinfo($templateName, PATHINFO_FILENAME) . '.css';
    
    // CSSファイルのパスを探す
    $cssPath = "{$designSetName}/standard/css/{$cssFileName}";
    $rootCssPath = "standard/css/{$cssFileName}";
    
    if (isset($files[$cssPath]) || isset($files[$rootCssPath])) {
        return "<link rel=\"stylesheet\" type=\"text/css\" href=\"makeshop.php?designset=" . urlencode($designSetName) . "&css=" . urlencode($cssFileName) . "\">\n";
    }
    
    return '';
}

/**
 * リモートCDARファイルからテンプレートをレンダリングする
 */
function render_from_remote_cdar(string $cdarUrl, string $templateName, array $additionalData = []): string {
    try {
        // リモートファイルをダウンロード
        $zipData = download_remote_file($cdarUrl);
        
        // ZIPファイルを展開
        $files = extract_zip_to_memory($zipData);
        
        // デザインセット名を検出
        $designSetName = detect_design_set_name_from_memory($files);
        
        // データの読み込みとマージ
        $data = setup_page_data($designSetName);
        $designSetData = load_design_set_data_from_memory($files, $designSetName);
        $data = array_merge($data, $designSetData);
        
        if (!empty($additionalData)) {
            // 追加データで既存のデータを上書き
            $data = array_replace_recursive($data, $additionalData);
        }
        
        // テンプレートをレンダリング
        $renderedHtml = render_template_from_memory($files, $designSetName, $templateName, $data);
        
        // リモートCDARファイルの場合、外部CSSとJavaScriptリンクを削除して、インライン化されたアセットに置き換える
        $inlineAssets = generate_inline_assets_from_memory($files, $designSetName, $templateName);
        if ($inlineAssets) {
            $renderedHtml = replace_external_assets_with_inline($renderedHtml, $inlineAssets);
        }
        
        return $renderedHtml;
        
    } catch (Exception $e) {
        throw new Exception("リモートCDARファイルの処理に失敗しました: " . $e->getMessage());
    }
}

/**
 * レンダリングされたHTMLから外部CSSとJavaScriptリンクを削除して、インライン化されたアセットに置き換える
 */
function replace_external_assets_with_inline(string $html, string $inlineAssets): string {
    // 同一ドメインのCSSリンクのみを削除（相対パスや /designsets/ などのパス）
    // 外部URL（http://、https://、//で始まるもの）は残す
    $html = preg_replace('/<link[^>]*href=["\'](?!https?:\/\/|\/\/)[^"\']*\.css["\'][^>]*>/i', '', $html);
    
    // 同一ドメインのJavaScriptリンクのみを削除（相対パスや /designsets/ などのパス）
    // 外部URL（http://、https://、//で始まるもの）は残す
    $html = preg_replace('/<script[^>]*src=["\'](?!https?:\/\/|\/\/)[^"\']*\.js["\'][^>]*><\/script>/i', '', $html);
    
    // スキーム省略のURL（//で始まる）をhttps://に変換
    $html = preg_replace('/(<link[^>]*href=["\'])\/\/([^"\']*["\'][^>]*>)/i', '$1https://$2', $html);
    $html = preg_replace('/(<script[^>]*src=["\'])\/\/([^"\']*["\'][^>]*><\/script>)/i', '$1https://$2', $html);
    
    // <head>タグの終了直前にインライン化されたアセットを挿入
    if (preg_match('/<\/head>/i', $html)) {
        $html = preg_replace('/(<\/head>)/i', $inlineAssets . "\n$1", $html);
    } else {
        // <head>タグがない場合は、<html>タグの直後に挿入
        if (preg_match('/<html[^>]*>/i', $html)) {
            $html = preg_replace('/(<html[^>]*>)/i', "$1\n<head>\n" . $inlineAssets . "\n</head>", $html);
        } else {
            // <html>タグもない場合は、先頭に挿入
            $html = "<head>\n" . $inlineAssets . "\n</head>\n" . $html;
        }
    }
    
    return $html;
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