# Use Playwright's official Docker image
FROM mcr.microsoft.com/playwright:focal

# Set working directory
WORKDIR /usr/src/app

# Copy package files
COPY package*.json ./

# Install dependencies
RUN npm install

# Copy application code
COPY . .

# Install Playwright browsers (if not already installed)
RUN npx playwright install --with-deps

# Expose necessary ports (adjust as needed)
EXPOSE 3000

# Set environment variables if needed
ENV NODE_ENV=production
ENV PORT=3000
# Start the application
CMD ["npm", "start"]
