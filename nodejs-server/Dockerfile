FROM mcr.microsoft.com/playwright:v1.47.2-focal

# Set working directory
WORKDIR /usr/src/app

# Copy package.json and package-lock.json
COPY package*.json ./

# Install dependencies including devDependencies
RUN npm install

# Copy app source
COPY . .

# Build the TypeScript files
RUN npx tsc

# Expose port
EXPOSE 3000

# Start the server
CMD ["npm", "start"]
