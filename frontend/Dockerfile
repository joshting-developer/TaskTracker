FROM node:24-bookworm-slim
WORKDIR /app
RUN chown node:node /app
USER node
COPY --chown=node:node frontend/package.json frontend/package-lock.json ./
RUN npm ci --include=dev --no-audit --no-fund
COPY --chown=node:node frontend/ ./
EXPOSE 5174
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]
