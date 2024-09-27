const redisSubscriber = require('./redisSubscriber');
const {sendMessageToTelegram} = require("../telegram");

async function handleVerificationCodeMessage(message) {
    if (message.action === 'collect_verification_code') {
        console.log(`User ${message.telegramId} sent verification code: ${message.code}`);

        await sendMessageToTelegram(`Received verification code: ${message.code}`, message.telegramId);
        // Add your processing logic here, for example, validating the code
    }
}

async function startListeningForVerificationCode(channel) {
    //with prefix wb_app_database_
    channel = `wb_app_database_${channel}`;
    // Subscribe to the channel where Laravel publishes verification code updates
    await redisSubscriber.subscribe(channel, handleVerificationCodeMessage);
}

module.exports = {
    startListeningForVerificationCode,
};
