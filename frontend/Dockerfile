# Stage 1: Dependencies
FROM node:20-alpine AS deps
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci

# Stage 2: Dev server (for docker-compose dev)
FROM node:20-alpine
WORKDIR /app
COPY --from=deps /app/node_modules ./node_modules
COPY . .
RUN addgroup -S soleil && adduser -S soleil -G soleil
RUN chown -R soleil:soleil /app
USER soleil
EXPOSE 5173
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]
