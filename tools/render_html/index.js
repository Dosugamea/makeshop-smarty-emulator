const express = require("express");
const cors = require("cors");
const nodeHtmlToImage = require("node-html-to-image");

const app = express();
const port = process.env.PORT || 3000;

// ミドルウェア設定
app.use(cors());
app.use(express.json({ limit: "50mb" })); // HTMLが大きい場合に備えて制限を緩和
app.use(express.urlencoded({ extended: true, limit: "50mb" }));

// ヘルスチェック用エンドポイント
app.get("/health", (req, res) => {
  res.json({ status: "OK", message: "HTML to Image API is running!" });
});

// メインのレンダリングエンドポイント
app.post("/render", async (req, res) => {
  try {
    const {
      page,
      width = 1200,
      height = 800,
      type = "png",
      quality = 80,
    } = req.body;

    // HTMLが提供されているかチェック
    if (!page) {
      return res.status(400).json({
        error: "Missing required field: page",
        message: 'Please provide HTML content in the "page" field',
      });
    }

    console.log("Rendering HTML to image...");
    console.log("HTML length:", page.length);
    console.log("Requested dimensions:", `${width}x${height}`);
    console.log("Image type:", type);
    console.log("Parsed width:", parseInt(width));
    console.log("Parsed height:", parseInt(height));
    console.log("Chrome executable path:", "/usr/bin/google-chrome-stable");

    // Chromeの存在確認
    const fs = require("fs");
    const { execSync } = require("child_process");
    try {
      const exists = fs.existsSync("/usr/bin/google-chrome-stable");
      console.log("Chrome exists:", exists);
      if (exists) {
        const stats = fs.statSync("/usr/bin/google-chrome-stable");
        console.log(
          "Chrome is executable:",
          !!(stats.mode & parseInt("111", 8))
        );

        // Chrome version check
        try {
          const version = execSync("/usr/bin/google-chrome-stable --version", {
            encoding: "utf8",
            timeout: 5000,
          }).trim();
          console.log("Chrome version:", version);
        } catch (versionError) {
          console.log("Error getting Chrome version:", versionError.message);
        }
      }
    } catch (e) {
      console.log("Error checking chrome:", e.message);
    }

    // HTMLに固定サイズのスタイルを追加（既存のスタイルを上書き）
    let htmlContent = page;

    // 固定サイズのスタイルを強制的に追加
    const fixedSizeStyle = `
      <style>
        * {
          box-sizing: border-box !important;
        }
        body, html, div, span, p, h1, h2, h3, h4, h5, h6, a, input, button, select, textarea {
          font-family: "Noto Sans CJK JP", "Hiragino Kaku Gothic ProN", "Hiragino Sans", "IPAexGothic", "Yu Gothic", "MS Gothic", Meiryo, sans-serif !important;
        }
        html {
          width: ${width}px !important;
          height: ${height}px !important;
          margin: 0 !important;
          padding: 0 !important;
          overflow: hidden !important;
          zoom: 1 !important;
        }
        body {
          width: ${width}px !important;
          height: ${height}px !important;
          margin: 0 !important;
          padding: 0 !important;
          overflow: hidden !important;
          transform: none !important;
          zoom: 1 !important;
          min-width: ${width}px !important;
          max-width: ${width}px !important;
          min-height: ${height}px !important;
          max-height: ${height}px !important;
        }
        .wrap {
          width: ${width}px !important;
          max-width: ${width}px !important;
          margin: 0 !important;
        }
        /* 元のスタイルを保持するために、色やデザインには干渉しない */
        input, button, select, textarea {
          /* フォントのみ指定、色やサイズは元のまま */
        }
        /* レンダリング品質を向上 */
        * {
          -webkit-font-smoothing: antialiased !important;
          -moz-osx-font-smoothing: grayscale !important;
          text-rendering: optimizeLegibility !important;
        }
        @media screen and (max-width: ${width + 100}px) {
          body {
            width: ${width}px !important;
            height: ${height}px !important;
          }
        }
        @media screen and (min-width: ${width - 100}px) {
          body {
            width: ${width}px !important;
            height: ${height}px !important;
          }
        }
      </style>`;

    // <head>タグの直後に固定サイズスタイルを挿入
    if (htmlContent.includes("<head>")) {
      htmlContent = htmlContent.replace("<head>", `<head>${fixedSizeStyle}`);
    } else {
      // <head>タグがない場合は<html>の直後に追加
      htmlContent = htmlContent.replace(
        "<html>",
        `<html><head>${fixedSizeStyle}</head>`
      );
    }

    // node-html-to-imageを使用して画像を生成
    console.log("Starting image generation...");
    console.log("Waiting for external resources (CSS, JS) to load...");
    const startTime = Date.now();

    const image = await Promise.race([
      nodeHtmlToImage({
        html: htmlContent,
        type: type,
        quality: quality,
        timeout: 15000,
        waitUntil: "networkidle0",
        // ビューポートサイズを直接指定
        content: {
          width: parseInt(width),
          height: parseInt(height),
        },
        puppeteerArgs: {
          executablePath: "/usr/bin/google-chrome-stable",
          headless: "new",
          defaultViewport: {
            width: parseInt(width),
            height: parseInt(height),
            deviceScaleFactor: 1,
          },
          args: [
            `--window-size=${width},${height}`,
            `--force-device-scale-factor=1`,
            "--no-sandbox",
            "--disable-setuid-sandbox",
            "--disable-dev-shm-usage",
            "--disable-gpu",
            "--no-first-run",
            "--no-zygote",
            "--single-process",
            "--disable-extensions",
            "--disable-default-apps",
            "--disable-translate",
            "--disable-plugins",
            "--disable-background-timer-throttling",
            "--disable-renderer-backgrounding",
            "--disable-backgrounding-occluded-windows",
            "--disable-client-side-phishing-detection",
            "--disable-sync",
            "--hide-scrollbars",
            "--mute-audio",
            "--no-default-browser-check",
            "--disable-hang-monitor",
            "--disable-popup-blocking",
            "--disable-prompt-on-repost",
            "--disable-background-networking",
            "--disable-breakpad",
            "--disable-component-update",
            "--disable-domain-reliability",
            "--disable-features=TranslateUI,VizDisplayCompositor",
            "--disable-ipc-flooding-protection",
            "--font-render-hinting=none",
            "--disable-font-subpixel-positioning",
            "--force-color-profile=srgb",
            "--disable-features=VizDisplayCompositor",
            "--run-all-compositor-stages-before-draw",
            "--disable-new-content-rendering-timeout",
            "--disable-web-security",
            "--allow-running-insecure-content",
            "--disable-features=VizDisplayCompositor",
          ],
        },
      }),
      new Promise((_, reject) =>
        setTimeout(
          () => reject(new Error("Image generation timeout after 20 seconds")),
          20000
        )
      ),
    ]);

    const endTime = Date.now();
    console.log(`Image generation completed in ${endTime - startTime}ms`);

    console.log("Image generated successfully!");

    // 画像のメタデータを確認（可能な場合）
    if (Buffer.isBuffer(image)) {
      console.log("Generated image buffer size:", image.length, "bytes");
    }

    // BufferをBase64文字列に変換
    let base64Image;
    if (Buffer.isBuffer(image)) {
      base64Image = image.toString("base64");
      console.log("Converted Buffer to base64 string");
    } else if (typeof image === "string") {
      base64Image = image;
      console.log("Image is already a string");
    } else {
      console.log("Unexpected image type:", typeof image);
      base64Image = String(image);
    }

    console.log("Base64 image length:", base64Image.length);

    // base64形式で返す
    res.json({
      success: true,
      image: base64Image,
      format: type,
      message: "Image rendered successfully",
    });
  } catch (error) {
    console.error("Error rendering HTML to image:", error);
    res.status(500).json({
      error: "Internal server error",
      message: "Failed to render HTML to image",
      details: error.message,
    });
  }
});

// 404ハンドラー
app.use("*", (req, res) => {
  res.status(404).json({
    error: "Not found",
    message: "The requested endpoint does not exist",
  });
});

// エラーハンドラー
app.use((error, req, res, next) => {
  console.error("Unhandled error:", error);
  res.status(500).json({
    error: "Internal server error",
    message: "Something went wrong",
  });
});

app.listen(port, "0.0.0.0", () => {
  console.log(`🚀 HTML to Image API server is running on port ${port}`);
  console.log(`📍 Health check: http://localhost:${port}/health`);
  console.log(`🎨 Render endpoint: POST http://localhost:${port}/render`);
});
