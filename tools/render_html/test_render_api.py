#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
HTMLレンダリングAPI テストスクリプト
"""

import requests
import json
import base64
import os
from datetime import datetime

# APIエンドポイント
API_URL = "http://localhost:3000/render"

def load_test_html():
    """resp_example.jsonからHTMLコンテンツを読み込む"""
    try:
        with open('resp_example.json', 'r', encoding='utf-8') as f:
            data = json.load(f)
            return data.get('page', '')
    except FileNotFoundError:
        print("❌ resp_example.json が見つかりません")
        return None
    except json.JSONDecodeError:
        print("❌ resp_example.json の形式が正しくありません")
        return None

def test_render_api():
    """HTMLレンダリングAPIをテストする"""
    print("🚀 HTMLレンダリングAPI テスト開始")
    print(f"📡 API URL: {API_URL}")
    
    # テスト用HTMLを読み込み
    html_content = load_test_html()
    if not html_content:
        print("❌ HTMLコンテンツの読み込みに失敗しました")
        return False
    
    print(f"📄 HTMLコンテンツ長: {len(html_content)} 文字")
    
    # リクエストデータ
    request_data = {
        "page": html_content,
        "width": 1920,
        "height": 1080,
        "type": "png",
        "quality": 80
    }
    
    try:
        print("📤 APIリクエスト送信中...")
        response = requests.post(
            API_URL,
            json=request_data,
            headers={
                'Content-Type': 'application/json'
            },
            timeout=30
        )
        
        print(f"📊 レスポンスステータス: {response.status_code}")
        
        if response.status_code == 200:
            print("✅ API呼び出し成功！")
            
            # レスポンスを解析
            response_data = response.json()
            
            if response_data.get('success'):
                print("✅ 画像レンダリング成功！")
                print(f"📊 レスポンス情報:")
                print(f"   - フォーマット: {response_data.get('format', 'unknown')}")
                print(f"   - メッセージ: {response_data.get('message', 'なし')}")
                
                # Base64画像データを保存
                if 'image' in response_data:
                    save_image(response_data['image'], response_data.get('format', 'png'))
                    return True
                else:
                    print("❌ レスポンスに画像データが含まれていません")
                    return False
            else:
                print("❌ 画像レンダリング失敗")
                print(f"エラー: {response_data.get('error', '不明なエラー')}")
                return False
        else:
            print(f"❌ APIエラー: {response.status_code}")
            print(f"レスポンス: {response.text}")
            return False
            
    except requests.exceptions.Timeout:
        print("❌ タイムアウトエラー（30秒）")
        return False
    except requests.exceptions.ConnectionError:
        print("❌ 接続エラー - APIサーバーが起動していない可能性があります")
        return False
    except requests.exceptions.RequestException as e:
        print(f"❌ リクエストエラー: {e}")
        return False
    except json.JSONDecodeError:
        print("❌ レスポンスのJSONパースエラー")
        return False

def save_image(base64_data, format_type):
    """Base64画像データをファイルに保存"""
    try:
        # タイムスタンプ付きファイル名
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        filename = f"rendered_image_{timestamp}.{format_type}"
        
        # Base64データをデコード
        image_data = base64.b64decode(base64_data)
        
        # ファイルに保存
        with open(filename, 'wb') as f:
            f.write(image_data)
        
        print(f"💾 画像を保存しました: {filename}")
        print(f"📊 ファイルサイズ: {len(image_data)} bytes")
        
    except Exception as e:
        print(f"❌ 画像保存エラー: {e}")

def test_health_check():
    """ヘルスチェックエンドポイントをテスト"""
    print("\n🏥 ヘルスチェック実行中...")
    
    try:
        response = requests.get("http://localhost:3000/health", timeout=5)
        if response.status_code == 200:
            print("✅ ヘルスチェック成功")
            return True
        else:
            print(f"❌ ヘルスチェック失敗: {response.status_code}")
            return False
    except requests.exceptions.RequestException as e:
        print(f"❌ ヘルスチェックエラー: {e}")
        return False

def main():
    """メイン実行関数"""
    print("=" * 50)
    print("🎨 HTMLレンダリングAPI テストスクリプト")
    print("=" * 50)
    
    # ヘルスチェック
    if not test_health_check():
        print("\n❌ APIサーバーにアクセスできません")
        print("💡 docker-compose up でサーバーを起動してください")
        return
    
    # メインテスト
    print("\n" + "=" * 50)
    success = test_render_api()
    
    print("\n" + "=" * 50)
    if success:
        print("🎉 テスト完了！すべて成功しました")
    else:
        print("😞 テストに失敗しました")
    print("=" * 50)

if __name__ == "__main__":
    main() 