<?php

/**
 * MakeShop Template Rendering API
 *
 * POST /api/render
 * Request: {"designset": "designset-name", "template": "template.html", "data": {}}
 * Response: {"status": "ok", "page": "rendered_html"} or {"status": "fail", "reason": "error", "reason_code": 400|500}
 */

// APIのレスポンスをJSON形式で返すための設定
header('Content-Type: application/json; charset=utf-8');

// makeshop.phpの関数群を読み込む
require_once 'makeshop.php';

/**
 * デバッグログを出力する
 */
function debug_log(string $message): void {
    error_log("[API DEBUG] " . $message);
}

/**
 * JSONレスポンスを送信して終了する
 */
function send_json_response(array $data): void {
    debug_log("Preparing JSON response...");
    
    // JSONエンコード前にデータサイズを確認
    debug_log("Data array keys: " . implode(', ', array_keys($data)));
    
    // JSONエンコードを実行
    $json_string = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
    // JSONエンコードエラーをチェック
    if ($json_string === false) {
        $json_error = json_last_error_msg();
        debug_log("JSON encoding failed: " . $json_error);
        // フォールバック：シンプルなエラーレスポンス
        echo json_encode(['status' => 'fail', 'reason' => 'JSON encoding error: ' . $json_error, 'reason_code' => 500]);
        exit;
    }
    
    $response_size = strlen($json_string);
    debug_log("JSON encoded successfully, size: {$response_size} bytes");
    
    if ($response_size > 10000) {
        debug_log("Large response ({$response_size} bytes) - content truncated for log");
    } else {
        debug_log("JSON response content: " . $json_string);
    }
    
    debug_log("About to output JSON response...");
    
    // Content-Length ヘッダーを明示的に設定
    header("Content-Length: " . $response_size);
    
    // JSONを出力
    echo $json_string;
    
    debug_log("JSON response sent successfully");
    exit;
}

/**
 * 成功レスポンスを送信して終了する
 */
function send_success_response(string $renderedHtml): void {
    send_json_response([
        'status' => 'ok',
        'page' => $renderedHtml
    ]);
}

/**
 * エラーレスポンスを送信して終了する
 */
function send_error_response(string $message, int $code): void {
    http_response_code($code);
    send_json_response([
        'status' => 'fail',
        'reason' => $message,
        'reason_code' => $code
    ]);
}

/**
 * リクエストボディのJSONをパースする
 */
function parse_request_body(): array {
    $json_input = file_get_contents('php://input');
    $request_body = json_decode($json_input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        send_error_response('無効なJSONリクエストです。', 400);
    }

    return $request_body ?? [];
}

/**
 * 利用可能なデザインセットフォルダを取得する
 */
function get_available_design_sets(): array {
    $designSetFolders = [];
    foreach (glob('designset-*') as $folder) {
        if (is_dir($folder)) {
            $designSetFolders[] = basename($folder);
        }
    }
    return $designSetFolders;
}

/**
 * リクエストパラメータを検証する
 */
function validate_request_params(array $params): array {
    $designSetName = $params['designset'] ?? null;
    $templateName = $params['template'] ?? null;
    $requestData = $params['data'] ?? [];
    $cdarUrl = $params['cdar_url'] ?? null;

    // .cdar URLが指定されている場合は、それを優先
    if (!empty($cdarUrl)) {
        return [
            'cdar_url' => $cdarUrl,
            'template' => $templateName,
            'data' => $requestData,
            'use_remote' => true
        ];
    }

    // デザインセットが指定されていない場合は、利用可能な最初のものを使用
    if (empty($designSetName)) {
        $availableDesignSets = get_available_design_sets();
        $designSetName = $availableDesignSets[0] ?? null;

        if (empty($designSetName)) {
            send_error_response('デザインセットが見つかりません。リクエストに "designset" を含めるか、htmlフォルダに designset-* フォルダを配置してください。', 400);
        }
    }

    if (empty($templateName)) {
        send_error_response('パラメータ "template" は必須です。', 400);
    }

    return [
        'designset' => $designSetName,
        'template' => $templateName,
        'data' => $requestData,
        'use_remote' => false
    ];
}

/**
 * メイン処理
 */
function main(): void {
    debug_log("API request started");
    
    // グローバル例外ハンドラを設定し、予期せぬエラーを捕捉する
    set_exception_handler(function($exception) {
        debug_log("Uncaught exception: " . $exception->getMessage());
        send_error_response($exception->getMessage(), 500);
    });

    // POSTリクエスト以外はエラー
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        debug_log("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
        send_error_response('POSTメソッドを使用してください。', 405);
    }

    try {
        debug_log("Parsing request body");
        // リクエストボディを解析
        $requestBody = parse_request_body();
        debug_log("Request body parsed: " . json_encode($requestBody));

        debug_log("Validating request parameters");
        // パラメータを検証
        $params = validate_request_params($requestBody);
        debug_log("Parameters validated: " . json_encode($params));

        // リモート .cdar ファイルを使用する場合
        if ($params['use_remote']) {
            debug_log("Using remote CDAR file: " . $params['cdar_url']);
            $renderedHtml = render_from_remote_cdar(
                $params['cdar_url'],
                $params['template'],
                $params['data']
            );
            debug_log("Remote CDAR rendered successfully, length: " . strlen($renderedHtml));
        } else {
            debug_log("Using local design set: " . $params['designset']);
            $renderedHtml = render_template(
                $params['designset'],
                $params['template'],
                $params['data']
            );
            debug_log("Local template rendered successfully, length: " . strlen($renderedHtml));
        }

        debug_log("Sending success response");
        // 成功レスポンスを返す
        send_success_response($renderedHtml);
    } catch (Exception $e) {
        debug_log("Exception caught: " . $e->getMessage());
        // レンダリングエラー（パラメータエラーも含む）
        send_error_response($e->getMessage(), 500);
    } catch (Throwable $e) {
        debug_log("Throwable caught: " . $e->getMessage());
        // その他の予期しないエラー
        send_error_response($e->getMessage(), 500);
    }
}

// メイン処理を実行
main();