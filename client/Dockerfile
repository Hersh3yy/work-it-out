# Feetness app: a static Nuxt build served by nginx. Coolify builds this with
# base directory /client and NUXT_PUBLIC_API_BASE pointing at the API.
FROM node:24-alpine AS build
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .
ARG NUXT_PUBLIC_API_BASE=https://api.example.com/api
ARG NUXT_PUBLIC_TELEGRAM_BOT=LarryTheLobsterFromSpongebobBot
ENV NUXT_PUBLIC_API_BASE=$NUXT_PUBLIC_API_BASE NUXT_PUBLIC_TELEGRAM_BOT=$NUXT_PUBLIC_TELEGRAM_BOT
RUN npm run generate

FROM nginx:1.27-alpine AS serve
COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/.output/public /usr/share/nginx/html
EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD wget -qO- http://localhost/ >/dev/null || exit 1
