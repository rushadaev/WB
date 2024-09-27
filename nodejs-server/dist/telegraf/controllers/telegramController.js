"use strict";
// controllers/telegramController.ts
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
const telegraf_1 = require("telegraf");
const warehouseBot_1 = __importDefault(require("../services/warehouseBot"));
const loggerTelegram_1 = __importDefault(require("../../utils/logger/loggerTelegram")); // Ensure correct path
const redis_1 = require("@telegraf/session/redis");
// Define a custom context if needed
const botToken = process.env.TELEGRAM_BOT_TOKEN_SUPPLIES_NEW;
const bot = new telegraf_1.Telegraf(botToken);
const warehouseBot = new warehouseBot_1.default(bot);
const store = (0, redis_1.Redis)({
    url: 'redis://redis:6379/2',
});
// Middleware to log incoming updates
bot.use((0, telegraf_1.session)({ store }));
bot.use((ctx, next) => __awaiter(void 0, void 0, void 0, function* () {
    loggerTelegram_1.default.info('Received update', { update: ctx.update });
    yield next();
}));
// Handle /start command
bot.start((ctx) => __awaiter(void 0, void 0, void 0, function* () {
    var _a;
    const chatId = ((_a = ctx.chat) === null || _a === void 0 ? void 0 : _a.id) || 0;
    yield warehouseBot.handleStart(chatId);
}));
// Handle /ping command
bot.command('ping', (ctx) => {
    ctx.reply('pong!');
});
// Export the bot instance
exports.default = bot;
