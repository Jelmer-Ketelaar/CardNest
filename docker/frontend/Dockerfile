FROM node:24-bookworm-slim

WORKDIR /app

ENV PLAYWRIGHT_BROWSERS_PATH=/ms-playwright

COPY frontend/package.json frontend/package-lock.json ./

RUN npm ci \
    && npx playwright install --with-deps chromium
