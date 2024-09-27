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
// utils/redisHelper.js
const redisSubscriber = require('./redisSubscriber');
/**
 * Waits for a verification code from Redis on a specific channel.
 * @param {string} telegramId - The user's Telegram ID.
 * @param {number} timeoutMs - Timeout in milliseconds.
 * @returns {Promise<string>} - Resolves with the verification code.
 */
function waitForVerificationCode(telegramId, timeoutMs = 300000) {
    return new Promise((resolve, reject) => __awaiter(this, void 0, void 0, function* () {
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
        const cleanup = () => __awaiter(this, void 0, void 0, function* () {
            yield redisSubscriber.unsubscribe(channel, messageHandler);
            clearTimeout(timer);
        });
        const timer = setTimeout(() => __awaiter(this, void 0, void 0, function* () {
            yield redisSubscriber.unsubscribe(channel, messageHandler);
            reject(new Error('Verification code timeout.'));
        }), timeoutMs);
        try {
            yield redisSubscriber.subscribe(channel, messageHandler);
            console.log(`Waiting for verification code on channel: ${channel}`);
        }
        catch (error) {
            clearTimeout(timer);
            reject(error);
        }
    }));
}
module.exports = { waitForVerificationCode };
