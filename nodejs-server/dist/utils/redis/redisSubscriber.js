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
// redisSubscriber.js
const redis = require('redis');
class RedisSubscriber {
    constructor() {
        this.subscriber = redis.createClient({
            url: 'redis://redis:6379/1' // Ensure using Database 1
        });
        this.isConnected = false;
        this.messageHandlers = {}; // To handle multiple channels
        this.subscriber.on('error', (err) => {
            console.error('Redis subscription error:', err);
        });
    }
    connect() {
        return __awaiter(this, void 0, void 0, function* () {
            if (!this.isConnected) {
                yield this.subscriber.connect();
                this.isConnected = true;
                console.log('Connected to Redis.');
            }
        });
    }
    /**
     * Subscribes to a Redis channel with a specific message handler.
     * @param {string} channel - The Redis channel to subscribe to.
     * @param {Function} messageHandler - The function to handle incoming messages.
     */
    subscribe(channel, messageHandler) {
        return __awaiter(this, void 0, void 0, function* () {
            yield this.connect();
            if (!this.messageHandlers[channel]) {
                this.messageHandlers[channel] = [];
                // Subscribe with a callback that iterates over all handlers for this channel
                yield this.subscriber.subscribe(channel, (message) => {
                    const parsedMessage = this.parseMessage(message, channel);
                    this.messageHandlers[channel].forEach(handler => {
                        try {
                            handler(parsedMessage);
                        }
                        catch (handlerError) {
                            console.error(`Error in handler for channel ${channel}:`, handlerError);
                        }
                    });
                });
                console.log(`Subscribed to Redis channel: ${channel}`);
            }
            this.messageHandlers[channel].push(messageHandler);
        });
    }
    /**
     * Unsubscribes a specific message handler from a Redis channel.
     * @param {string} channel - The Redis channel to unsubscribe from.
     * @param {Function} messageHandler - The handler to remove.
     */
    unsubscribe(channel, messageHandler) {
        return __awaiter(this, void 0, void 0, function* () {
            if (this.messageHandlers[channel]) {
                this.messageHandlers[channel] = this.messageHandlers[channel].filter((handler) => handler !== messageHandler);
                if (this.messageHandlers[channel].length === 0) {
                    delete this.messageHandlers[channel];
                    yield this.subscriber.unsubscribe(channel);
                    console.log(`Unsubscribed from Redis channel: ${channel}`);
                }
            }
        });
    }
    /**
     * Parses the incoming message and handles JSON parsing errors.
     * @param {string} message - The raw message string from Redis.
     * @param {string} channel - The Redis channel name.
     * @returns {Object|null} - The parsed message object or null if parsing fails.
     */
    parseMessage(message, channel) {
        try {
            const parsed = JSON.parse(message);
            console.log(`Message received from ${channel}:`, parsed);
            return parsed;
        }
        catch (error) {
            console.error(`Error parsing message from channel ${channel}:`, error);
            return null;
        }
    }
    /**
     * Disconnects the Redis subscriber gracefully.
     */
    disconnect() {
        return __awaiter(this, void 0, void 0, function* () {
            if (this.isConnected) {
                yield this.subscriber.disconnect();
                this.isConnected = false;
                console.log('Redis subscriber disconnected.');
            }
        });
    }
}
module.exports = new RedisSubscriber();
