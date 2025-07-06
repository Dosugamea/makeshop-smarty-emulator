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
 * JSONレスポンスを送信して終了する
 */
function send_json_response(array $data): void {
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
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
        'data' => $requestData
    ];
}

/**
 * メイン処理
 */
function main(): void {
    // グローバル例外ハンドラを設定し、予期せぬエラーを捕捉する
    set_exception_handler(function($exception) {
        send_error_response($exception->getMessage(), 500);
    });

    // POSTリクエスト以外はエラー
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_error_response('POSTメソッドを使用してください。', 405);
    }

    try {
        // リクエストボディを解析
        $requestBody = parse_request_body();

        // パラメータを検証
        $params = validate_request_params($requestBody);

        // テンプレートをレンダリング
        $renderedHtml = render_template(
            $params['designset'],
            $params['template'],
            $params['data']
        );

        // 成功レスポンスを返す
        send_success_response($renderedHtml);
    } catch (Exception $e) {
        // レンダリングエラー（パラメータエラーも含む）
        send_error_response($e->getMessage(), 500);
    } catch (Throwable $e) {
        // その他の予期しないエラー
        send_error_response($e->getMessage(), 500);
    }
}

// メイン処理を実行
main();