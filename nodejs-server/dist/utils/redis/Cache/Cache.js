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
const redisClient = require('../redisClient');
const { serialize, unserialize } = require('php-serialize');
const axios = require('axios');
class Cache {
    constructor() {
        this.prefix = 'wb_app_database_';
    }
    // Set a value in the cache
    set(key_1, value_1) {
        return __awaiter(this, arguments, void 0, function* (key, value, expirationInSeconds = 3600) {
            key = this.prefix + key;
            try {
                const serializedValue = serialize(value);
                yield redisClient.set(key, serializedValue, {
                    EX: expirationInSeconds // Expiration time in seconds
                });
                console.log(`Value set for key: ${key}`);
            }
            catch (err) {
                console.error(`Error setting cache value for key ${key}:`, err);
            }
        });
    }
    // Get a value from the cache
    get(key) {
        return __awaiter(this, void 0, void 0, function* () {
            key = this.prefix + key;
            try {
                const value = yield redisClient.get(key);
                if (value) {
                    try {
                        const deserializedValue = unserialize(value); // PHP unserialize
                        console.log(`Value retrieved for key ${key}:`, deserializedValue);
                        return deserializedValue;
                    }
                    catch (error) {
                        console.log(`Failed to deserialize, returning raw value for key ${key}:`, value);
                        return value; // Return raw value if not serialized
                    }
                }
                else {
                    console.log(`Key ${key} not found in cache.`);
                    return null;
                }
            }
            catch (err) {
                console.error(`Error getting cache value for key ${key}:`, err);
                return null;
            }
        });
    }
    getUserByTelegramId(telegramId) {
        return __awaiter(this, void 0, void 0, function* () {
            try {
                let user = yield this.get(`user_telegram_id_${telegramId}`);
                console.log('userInCache', user);
                if (user) {
                    return user;
                }
                const response = yield axios.get(`${process.env.LARAVEL_API_URL}/users/telegram/${telegramId}`);
                console.log('User:', response.data);
                return response.data;
            }
            catch (error) {
                console.error('Error fetching user:', error);
                return null;
            }
        });
    }
    // Delete a value from the cache
    forget(key) {
        return __awaiter(this, void 0, void 0, function* () {
            key = this.prefix + key;
            try {
                const result = yield redisClient.del(key);
                if (result === 1) {
                    console.log(`Successfully deleted key: ${key}`);
                    return true;
                }
                else {
                    console.log(`Key ${key} does not exist or could not be deleted.`);
                    return false;
                }
            }
            catch (err) {
                console.error(`Error deleting cache value for key ${key}:`, err);
                return false;
            }
        });
    }
}
module.exports = new Cache();
