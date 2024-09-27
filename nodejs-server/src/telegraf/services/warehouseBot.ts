import {Telegraf} from "telegraf";
import {MyContext} from "../types/MyContext";

import CacheService from '../../utils/redis/Cache/Cache';
class WarehouseBot {
    private bot: Telegraf<MyContext>;
    constructor(bot: Telegraf<MyContext>) {
        this.bot = bot;
    }

    async handleStart(chatId: number) {
        let user = null;
        // Fetch user data from Laravel API
        try {
            user = await this.fetchUserByTelegramId(chatId);
        } catch (error) {
            console.error('Error fetching user:', error);
            return;
        }

        let formattedDate = '';
        if (user?.subscription_until) {
            const subscriptionUntil = new Date(user.subscription_until);
            if (subscriptionUntil.getFullYear() >= 2124) {
                formattedDate = 'Ваша подписка действует навсегда';
            } else {
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
        } else {
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

        await this.bot.telegram.sendMessage(chatId, message, {
            parse_mode: 'HTML',
            reply_markup: keyboard,
        });
    }

    async fetchUserByTelegramId(telegramId: number) {
        try {
            return await CacheService.getUserByTelegramId(telegramId);
        } catch (error) {
            console.error('Error fetching user:', error);
            return null;
        }
    }

    // Implement other methods like handleNotification, handlePayment, etc.
}

export default WarehouseBot;
