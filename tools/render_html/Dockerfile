# Node.js 18のDebian slimベースイメージを使用（より安定）
FROM node:18-slim

# 必要なパッケージをインストール
RUN apt-get update \
    && apt-get install -y \
        wget \
        gnupg \
        ca-certificates \
        procps \
        libxss1 \
        libgconf-2-4 \
        libxtst6 \
        libxrandr2 \
        libasound2 \
        libpangocairo-1.0-0 \
        libatk1.0-0 \
        libcairo-gobject2 \
        libgtk-3-0 \
        libgdk-pixbuf2.0-0 \
        libxcomposite1 \
        libxcursor1 \
        libxdamage1 \
        libxi6 \
        libxtst6 \
        libnss3 \
        libcups2 \
        libxss1 \
        libxrandr2 \
        libasound2 \
        libatk1.0-0 \
        libdrm2 \
        libxkbcommon0 \
        libatspi2.0-0 \
        fonts-liberation \
        fonts-dejavu-core \
        fonts-noto-cjk \
        fonts-noto-cjk-extra \
        fonts-ipafont-gothic \
        fonts-ipafont-mincho \
        fontconfig \
    && rm -rf /var/lib/apt/lists/*

# Chrome/Chromiumをインストール
RUN wget -q -O - https://dl-ssl.google.com/linux/linux_signing_key.pub | apt-key add - \
    && echo "deb [arch=amd64] http://dl.google.com/linux/chrome/deb/ stable main" >> /etc/apt/sources.list.d/google.list \
    && apt-get update \
    && apt-get install -y google-chrome-stable \
    && rm -rf /var/lib/apt/lists/*

# 環境変数を設定
ENV PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=true \
    PUPPETEER_EXECUTABLE_PATH=/usr/bin/google-chrome-stable

# アプリケーションディレクトリを作成
WORKDIR /app

# package.jsonとpackage-lock.jsonをコピー
COPY package*.json ./

# 依存関係をインストール
RUN npm install --omit=dev

# アプリケーションソースをコピー
COPY . .

# 非rootユーザーを作成
RUN groupadd -r nodejs && useradd -r -g nodejs -G audio,video nodejs \
    && mkdir -p /home/nodejs/Downloads \
    && chown -R nodejs:nodejs /home/nodejs \
    && chown -R nodejs:nodejs /app

USER nodejs

# ポート3000を公開
EXPOSE 3000

# ヘルスチェック
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
  CMD node -e "require('http').get('http://localhost:3000/health', (res) => { process.exit(res.statusCode === 200 ? 0 : 1) })"

# アプリケーションを起動
CMD ["npm", "start"] 