// nodejs-server/utils/telegram.js

const axios = require('axios');
const FormData = require('form-data');
const fs = require('fs');

// Load environment variables
const TELEGRAM_BOT_TOKEN = process.env.TELEGRAM_BOT_TOKEN || '7237021957:AAEBwCsrCFNLFGArfGys3rJgzqitL9Wsg8k';
const TELEGRAM_CHAT_ID = process.env.TELEGRAM_CHAT_ID || '782919745';

/**
 * Sends a CAPTCHA image to the specified Telegram chat.
 * @param {string} imagePath - The file path to the CAPTCHA image.
 * @returns {Promise<boolean>} - Returns true if sent successfully, else false.
 */
async function sendCaptchaToTelegram(imagePath) {
    const url = `https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/sendPhoto`;
    const form = new FormData();
    form.append('chat_id', TELEGRAM_CHAT_ID);
    form.append('photo', fs.createReadStream(imagePath), {
        filename: 'captcha.png',
        contentType: 'image/png',
    });

    try {
        const response = await axios.post(url, form, {
            headers: form.getHeaders(),
        });
        if (response.data.ok) {
            console.log('Captcha sent to Telegram successfully!');
            return true;
        } else {
            console.error('Failed to send captcha:', response.data);
            return false;
        }
    } catch (error) {
        console.error('Exception occurred while sending captcha:', error.message);
        return false;
    }
}

/**
 * Sends a text message to the specified Telegram chat.
 * @param {string} message - The message text to send.
 * @param telegram_chat_id - optional chat id to send message to
 * @returns {Promise<boolean>} - Returns true if sent successfully, else false.
 */
async function sendMessageToTelegram(message, telegram_chat_id = null) {
    const url = `https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/sendMessage`;
    const form = new FormData();
    form.append('chat_id', telegram_chat_id || TELEGRAM_CHAT_ID);
    form.append('text', message);

    try {
        const response = await axios.post(url, form, {
            headers: form.getHeaders(),
        });
        if (response.data.ok) {
            console.log('Message sent to Telegram successfully!');
            return true;
        } else {
            console.error('Failed to send message:', response.data);
            return false;
        }
    } catch (error) {
        console.error('Exception occurred while sending message:', error.message);
        return false;
    }
}

/**
 * Retrieves updates from the Telegram Bot API.
 * @param {number} [offset] - The update ID to start fetching from.
 * @returns {Promise<Object|null>} - Returns the JSON response or null on failure.
 */
async function getUpdates(offset = null) {
    const url = `https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/getUpdates`;
    const params = { timeout: 100 };
    if (offset !== null) {
        params.offset = offset;
    }

    try {
        const response = await axios.get(url, { params });
        if (response.data.ok) {
            return response.data;
        } else {
            console.error('Failed to get updates:', response.data);
            return null;
        }
    } catch (error) {
        console.error('Exception occurred while getting updates:', error.message);
        return null;
    }
}

/**
 * Polls Telegram for the user's response.
 * @param {number|null} lastUpdateId - The last processed update ID.
 * @param {number} timeout - Maximum time to wait in seconds.
 * @returns {Promise<{text: string, updateId: number} | null>} - Returns the user's response and new update ID or null.
 */
async function pollForUserResponse(lastUpdateId = null, timeout = 300) {
    console.log('Waiting for user to send a response...');
    const startTime = Date.now();

    while ((Date.now() - startTime) / 1000 < timeout) {
        const updates = await getUpdates(lastUpdateId ? lastUpdateId + 1 : undefined);
        if (updates && updates.result) {
            for (const update of updates.result) {
                const updateId = update.update_id;
                const message = update.message;
                if (message && message.chat.id.toString() === TELEGRAM_CHAT_ID && message.text) {
                    console.log(`Received user response: ${message.text}`);
                    return { text: message.text, updateId };
                }
                // Update the lastUpdateId to skip already processed messages
                lastUpdateId = updateId;
            }
        }
        await new Promise((resolve) => setTimeout(resolve, 2000)); // Wait 2 seconds before polling again
    }

    console.log('Timeout waiting for user response.');
    return null;
}

/**
 * Clears existing updates to avoid processing old messages.
 * @returns {Promise<number|null>} - Returns the last update ID or null.
 */
async function clearExistingUpdates() {
    const updates = await getUpdates();
    if (updates && updates.result && updates.result.length > 0) {
        const lastUpdateId = updates.result[updates.result.length - 1].update_id;
        console.log(`Cleared existing updates up to update_id: ${lastUpdateId}`);
        return lastUpdateId;
    }
    return null;
}

module.exports = {
    sendCaptchaToTelegram,
    sendMessageToTelegram,
    pollForUserResponse,
    clearExistingUpdates,
};
