// utils/redisHelper.js
const redisSubscriber = require('./redisSubscriber');

/**
 * Waits for a verification code from Redis on a specific channel.
 * @param {string} telegramId - The user's Telegram ID.
 * @param {number} timeoutMs - Timeout in milliseconds.
 * @returns {Promise<string>} - Resolves with the verification code.
 */
function waitForVerificationCode(telegramId, timeoutMs = 300000) { // Default timeout: 5 minutes
    return new Promise(async (resolve, reject) => {
        let channel = `verification_code_channel_${telegramId}`;
        // Channel with prefix wb_app_database_
        channel = `wb_app_database_${channel}`;

        const messageHandler = (message) => {
            if (message && message.action === 'collect_verification_code') {
                console.log(`Received verification code for Telegram ID ${telegramId}: ${message.code}`);
                cleanup();
                resolve(message.code);
            }
        };

        const cleanup = async () => {
            await redisSubscriber.unsubscribe(channel, messageHandler);
            clearTimeout(timer);
        };

        const timer = setTimeout(async () => {
            await redisSubscriber.unsubscribe(channel, messageHandler);
            reject(new Error('Verification code timeout.'));
        }, timeoutMs);

        try {
            await redisSubscriber.subscribe(channel, messageHandler);
            console.log(`Waiting for verification code on channel: ${channel}`);
        } catch (error) {
            clearTimeout(timer);
            reject(error);
        }
    });
}

module.exports = { waitForVerificationCode };
