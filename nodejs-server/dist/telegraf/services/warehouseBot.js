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
const Cache_1 = __importDefault(require("../../utils/redis/Cache/Cache"));
class WarehouseBot {
    constructor(bot) {
        this.bot = bot;
    }
    handleStart(chatId) {
        return __awaiter(this, void 0, void 0, function* () {
            let user = null;
            // Fetch user data from Laravel API
            try {
                user = yield this.fetchUserByTelegramId(chatId);
            }
            catch (error) {
                console.error('Error fetching user:', error);
                return;
            }
            let formattedDate = '';
            if (user === null || user === void 0 ? void 0 : user.subscription_until) {
                const subscriptionUntil = new Date(user.subscription_until);
                if (subscriptionUntil.getFullYear() >= 2124) {
                    formattedDate = 'Ваша подписка действует навсегда';
                }
                else {
                    formattedDate = `Ваша подписка действует до ${subscriptionUntil.toLocaleDateString('ru-RU')}`;
                }
            }
            if (!user.is_paid) {
                formattedDate = 'У вас действует 3 дня бесплатного доступа🤝';
            }
            let message = '';
            if (!user.has_active_subscription) {
                message = `Найдите!!! бесплатную приемку на WB 🔥

Мы помогаем отслеживать доступные бесплатные приемки на Wildberries. Вы можете проверить текущий коэффициент онлайн или настроить уведомления о доступных бесплатных слотах для приемки товара. 🤙

Как это работает?

1. Выберите склад.
2. Укажите в чем будете отгружать.
3. Выберите тип приемки.
4. Ждите уведомления.

Как только появится подходящий тайм-слот, мы сразу же отправим вам уведомление. Вы можете ставить любое количество уведомлений

⚠️Подписка закончилась, необходимо оплатить`;
            }
            else {
                message = `Найдите бесплатную приемку на WB 🔥

Мы помогаем отслеживать доступные бесплатные приемки на Wildberries. Вы можете проверить текущий коэффициент онлайн или настроить уведомления о доступных слотах для приемки товара. 🤙

Как это работает?

1. Выберите склад.
2. Укажите в чем будете отгружать.
3. Выберите тип приемки.
4. Ждите уведомления.

Как только появится подходящий тайм-слот, мы сразу же отправим вам уведомление. Вы можете ставить любое количество уведомлений

${formattedDate}`;
            }
            const keyboard = {
                inline_keyboard: [
                    [
                        { text: '📦 Автобронирование', callback_data: 'wh_notification' },
                    ],
                    [
                        { text: '⚡ Поиск слотов', callback_data: 'wh_notification' },
                        { text: '📝 Заявки на поиск слотов', callback_data: 'wh_notification' },
                    ],
                    [
                        { text: '🙌 Мои кабинеты', callback_data: 'wh_payment' },
                        { text: '💎 Подписка', callback_data: 'wh_payment' },
                    ],
                    [
                        { text: '💬 Поддержка', url: 'https://t.me/dmitrynovikov21' },
                        { text: '📍 Инструкции', url: 'https://t.me/dmitrynovikov21' },
                    ],
                ],
            };
            yield this.bot.telegram.sendMessage(chatId, message, {
                parse_mode: 'HTML',
                reply_markup: keyboard,
            });
        });
    }
    fetchUserByTelegramId(telegramId) {
        return __awaiter(this, void 0, void 0, function* () {
            try {
                return yield Cache_1.default.getUserByTelegramId(telegramId);
            }
            catch (error) {
                console.error('Error fetching user:', error);
                return null;
            }
        });
    }
}
exports.default = WarehouseBot;
