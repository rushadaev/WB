// controllers/telegramController.ts

import {Telegraf, session} from 'telegraf';
import WarehouseBot from '../services/warehouseBot';
import logger from '../../utils/logger/loggerTelegram'; // Ensure correct path
import { Redis as RedisStore } from '@telegraf/session/redis';
import {MyContext, MySession} from "../types/MyContext";

// Define a custom context if needed
const botToken: string = process.env.TELEGRAM_BOT_TOKEN_SUPPLIES_NEW!;
const bot: Telegraf<MyContext> = new Telegraf(botToken);
const warehouseBot = new WarehouseBot(bot);

const store = RedisStore<MySession>({
    url: 'redis://redis:6379/2',
});


// Middleware to log incoming updates
bot.use(session({ store }));
bot.use(async (ctx: MyContext, next: () => Promise<void>) => {
    logger.info('Received update', { update: ctx.update });
    await next();
});

// Handle /start command
bot.start(async (ctx: MyContext) => {
    const chatId: number = ctx.chat?.id || 0;
    await warehouseBot.handleStart(chatId);
});

// Handle /ping command
bot.command('ping', (ctx: MyContext) => {
    ctx.reply('pong!');
});

// Export the bot instance
export default bot;
