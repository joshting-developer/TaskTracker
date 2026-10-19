FROM node:24-bookworm-slim

WORKDIR /app
RUN mkdir -p /app/data && chown -R node:node /app
USER node

COPY --chown=node:node package.json package-lock.json ./
RUN npm ci --include=dev --no-audit --no-fund

COPY --chown=node:node . .

CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]
