"use strict";
var __awaiter = (this && this.__awaiter) || function (thisArg, _arguments, P, generator) {
    function adopt(value) { return value instanceof P ? value : new P(function (resolve) { resolve(value); }); }
    return new (P || (P = Promise))(function (resolve, reject) {
        function fulfilled(value) { try { step(generator.next(value)); } catch (e) { reject(e); } }
        function rejected(value) { try { step(generator["throw"](value)); } catch (e) { reject(e); } }
        function step(result) { result.done ? resolve(result.value) : adopt(result.value).then(fulfilled, rejected); }
        step((generator = generator.apply(thisArg, _arguments || [])).next());
    });
};
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
const express_1 = __importDefault(require("express"));
const body_parser_1 = __importDefault(require("body-parser"));
const winston_1 = __importDefault(require("winston")); // For logging
const clusterManager_1 = require("./utils/clusterManager");
const telegramController_1 = __importDefault(require("./telegraf/controllers/telegramController"));
// Import Routes
const drafts_1 = __importDefault(require("./routes/drafts"));
const orders_1 = __importDefault(require("./routes/orders"));
const acceptance_1 = __importDefault(require("./routes/acceptance"));
const auth_1 = __importDefault(require("./routes/auth"));
const app = (0, express_1.default)();
const PORT = process.env.PORT || 3000;
// Configure Winston (optional)
const logger = winston_1.default.createLogger({
    level: 'info',
    format: winston_1.default.format.json(),
    defaultMeta: { service: 'nodejs-server' },
    transports: [
        new winston_1.default.transports.Console({
            format: winston_1.default.format.simple(),
        }),
        new winston_1.default.transports.File({
            filename: 'combined.log', // Log file name
            format: winston_1.default.format.json(), // Optional: Can also use format like simple or custom formats
        }),
        // Add more transports like File if needed
    ],
});
// Middleware
app.use(body_parser_1.default.json());
// Routes
// Webhook route
app.use(telegramController_1.default.webhookCallback('/webhook/telegram'));
app.use('/api/drafts', drafts_1.default);
app.use('/api/orders', orders_1.default);
app.use('/api/acceptance', acceptance_1.default);
app.use('/api/auth', auth_1.default);
// Health Check Endpoint
app.get('/health', (req, res) => {
    res.status(200).send({ status: 'OK' });
});
// Start Server After Initializing Cluster
const startServer = () => __awaiter(void 0, void 0, void 0, function* () {
    try {
        yield (0, clusterManager_1.initializeCluster)(); // Initialize Playwright Cluster
        app.listen(PORT, () => {
            console.log(`Node.js server is running on port ${PORT}`);
        });
    }
    catch (error) {
        console.error('Failed to initialize Playwright cluster:', error.message);
        process.exit(1); // Exit process with failure
    }
});
startServer();
// Handle graceful shutdown
process.on('SIGINT', () => __awaiter(void 0, void 0, void 0, function* () {
    console.log('Shutting down server...');
    yield (0, clusterManager_1.shutdownCluster)();
    process.exit(0);
}));
process.on('SIGTERM', () => __awaiter(void 0, void 0, void 0, function* () {
    console.log('Shutting down server...');
    yield (0, clusterManager_1.shutdownCluster)();
    process.exit(0);
}));
