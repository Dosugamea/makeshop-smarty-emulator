<?php
// デザインセットフォルダを検索（htmlフォルダ内）
$designSetFolders = [];
foreach (glob('designset-*') as $folder) {
    if (is_dir($folder)) {
        $designSetFolders[] = basename($folder);
    }
}

// 現在選択されているデザインセット
$currentDesignSet = $_GET['designset'] ?? $designSetFolders[0] ?? null;
?>
<html>
<head>
    <meta charset="UTF-8">
    <title>MakeShop Smarty デザインテンプレート開発環境</title>
    <style>
        body { font-family: sans-serif; margin: 20px; }
        .design-set-selector { background: #f5f5f5; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .template-list { background: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        ul { list-style-type: none; padding: 0; }
        li { margin: 5px 0; }
        a { text-decoration: none; color: #0066cc; }
        a:hover { text-decoration: underline; }
        .current { font-weight: bold; color: #333; }
    </style>
</head>
<body>
    <h1>MakeShop Smarty デザインテンプレート開発環境</h1>
    
    <?php if (!empty($designSetFolders)): ?>
        <div class="design-set-selector">
            <h2>デザインセット選択</h2>
            <ul>
                <?php foreach ($designSetFolders as $folder): ?>
                    <li>
                        <?php if ($folder === $currentDesignSet): ?>
                            <span class="current">📁 <?= htmlspecialchars($folder) ?> (現在選択中)</span>
                        <?php else: ?>
                            <a href="?designset=<?= urlencode($folder) ?>">📁 <?= htmlspecialchars($folder) ?></a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($currentDesignSet): ?>
        <div class="template-list">
            <h2>テンプレート一覧 (<?= htmlspecialchars($currentDesignSet) ?>)</h2>
            <ul>
                <?php
                $templatePath = "{$currentDesignSet}/standard/html/";
                if (is_dir($templatePath)) {
                    foreach (glob($templatePath . '*.html') as $filename) {
                        if (is_file($filename)) {
                            $templateName = basename($filename);
                            echo '<li><a href="makeshop.php?designset=' . urlencode($currentDesignSet) . '&template=' . urlencode($templateName) . '">' . htmlspecialchars($templateName) . '</a></li>';
                        }
                    }
                } else {
                    echo '<li>テンプレートフォルダが見つかりません: ' . htmlspecialchars($templatePath) . '</li>';
                }
                ?>
            </ul>
        </div>
    <?php else: ?>
        <div class="template-list">
            <p>デザインセットフォルダが見つかりません。</p>
            <p>html フォルダ内に <code>designset-*</code> フォルダを配置してください。</p>
        </div>
    <?php endif; ?>
</body>
</html>