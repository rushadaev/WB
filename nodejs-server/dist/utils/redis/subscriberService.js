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
const redisSubscriber = require('./redisSubscriber');
const { sendMessageToTelegram } = require("../telegram");
function handleVerificationCodeMessage(message) {
    return __awaiter(this, void 0, void 0, function* () {
        if (message.action === 'collect_verification_code') {
            console.log(`User ${message.telegramId} sent verification code: ${message.code}`);
            yield sendMessageToTelegram(`Received verification code: ${message.code}`, message.telegramId);
            // Add your processing logic here, for example, validating the code
        }
    });
}
function startListeningForVerificationCode(channel) {
    return __awaiter(this, void 0, void 0, function* () {
        //with prefix wb_app_database_
        channel = `wb_app_database_${channel}`;
        // Subscribe to the channel where Laravel publishes verification code updates
        yield redisSubscriber.subscribe(channel, handleVerificationCodeMessage);
    });
}
module.exports = {
    startListeningForVerificationCode,
};
