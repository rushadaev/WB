import {Scenes, Markup, Composer} from 'telegraf';
import { MyContext } from '../../types/MyContext';
import logger from '../../../utils/logger/loggerTelegram';
import LaravelService from "../../../services/laravelService";


const defaultButtons = [
    [Markup.button.callback('👈 Назад', 'back')],
    [Markup.button.callback('👌 Главное меню', 'mainmenu')],
];
const tariffHandler = new Composer<MyContext>();
tariffHandler.action(/tariff_\d+/, async (ctx) => {
    logger.info('Tariff selected:', { tariffId: ctx.match.input });
    logger.info('Received update', { update: ctx.update });
    const tariffId = ctx.match.input.split('_')[1];
    ctx.session.selectedTariff = tariffId;

    await ctx.answerCbQuery('😎 Выбран тариф' + tariffId);

    logger.info('entered confirm payment');
    try {
        // Simulate payment confirmation
        // In reality, you would handle this via a webhook endpoint
        const paymentSuccessful = true; // Replace with actual payment status

        if (paymentSuccessful) {
            // Update session or database as needed
            ctx.session.count = (ctx.session.count || 0) + 1;

           // answer notification
            await ctx.answerCbQuery('Оплата прошла успешно!', {
                show_alert: true,
            });

            return ctx.scene.enter('subscriptionWizard');
        } else {
            await ctx.editMessageText('Оплата не прошла. Пожалуйста, попробуйте снова.');
            await ctx.scene.enter('subscriptionWizard');
            return ctx.scene.leave();
        }
    } catch (error) {
        logger.error('Error confirming payment:', error);
        await ctx.reply('Произошла ошибка при подтверждении оплаты. Пожалуйста, попробуйте позже.');

        return ctx.scene.enter('main');
    }
});

const sendStartMessage = async (ctx: MyContext) => {

    let user = null;
    try{
        user = await LaravelService.getUserByTelegramId(ctx.from.id);
    } catch (error) {
        logger.error('Error getting user:', error);
        await ctx.reply('Произошла ошибка при получении данных пользователя. Попробуйте позже');
    }

    const message = `🫡 Подписка
Доступно автообронирований: ${user?.autobookings || 1}
Выберете необходимое кол-во автобронирований 🙌

1 автобронь – 250₽  
5 автоброней – 1.000₽  
10 автоброней – 1.850₽  
20 автоброней – 3.500₽  
50 автоброней – 6.800₽`;

    const keyboard = Markup.inlineKeyboard([
        [Markup.button.callback('😎 Выбрать тариф', 'choose_tariff')],
        [Markup.button.callback('👌 Главное меню', 'mainmenu')],
    ]);

    if (ctx.callbackQuery && ctx.callbackQuery.message) {
        // If the interaction is from a callback query, edit the existing message
        await ctx.editMessageText(message, keyboard);
        await ctx.answerCbQuery('💎 Подписка');
    } else {
        // Otherwise, send a new message
        await ctx.reply(message, keyboard);
    }

}

const subscriptionWizard = new Scenes.WizardScene<MyContext>(
    'subscriptionWizard',
    // Step 1: Show subscription options
    async (ctx) => {
        await sendStartMessage(ctx);
        return ctx.wizard.next();
    },

    // Step 2: Handle tariff selection
    async (ctx) => {
        // Игнорируем сообщения, не являющиеся callbackQuery
        if (!ctx.callbackQuery) return undefined;

        const tariffs = [
            { id: 1, name: '1 автобронь', price: 250 },
            { id: 5, name: '5 автоброней', price: 1000 },
            { id: 10, name: '10 автоброней', price: 1850 },
            { id: 20, name: '20 автоброней', price: 3500 },
            { id: 50, name: '50 автоброней', price: 6800 },
        ];

        const webUrl = 'https://botcomment.xyz';

        const tariffButtons = tariffs.map((tariff) => [
            Markup.button.url(`${tariff.name} – ${tariff.price}₽`, `${webUrl}/payment_link/${ctx.from.id}/${tariff.id}`)
        ]);



        await ctx.editMessageText('🫡 Выберите тариф:', Markup.inlineKeyboard([ ...tariffButtons, ...defaultButtons]));
        await ctx.answerCbQuery('😎 Выберите тариф');
        return ctx.wizard.next();
    },
    tariffHandler,
);

// Handle actions within the wizard
subscriptionWizard.action('mainmenu', async (ctx) => {
    await ctx.scene.enter('main');
    await ctx.answerCbQuery('👌 Главное меню');
});

subscriptionWizard.action('back', async (ctx) => {
    await ctx.wizard.back();
    await sendStartMessage(ctx);
});

subscriptionWizard.command('start', async (ctx) => {
    await ctx.scene.enter('main');
});

// Export the scene
export default subscriptionWizard;
