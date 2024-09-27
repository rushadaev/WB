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
const redisClient = require('./redisClient');
const { serialize, unserialize } = require('php-serialize');
// Serialize values to match Laravel's expected format (JSON serialization)
function setCacheValue(key_1, value_1) {
    return __awaiter(this, arguments, void 0, function* (key, value, expirationInSeconds = 3600) {
        try {
            // Custom key format: wb_app_database_{key}
            key = `wb_app_database_${key}`;
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
// Get a value from Laravel Redis cache
function getCacheValue(key) {
    return __awaiter(this, void 0, void 0, function* () {
        try {
            // Custom key format: wb_app_database_{key}
            key = `wb_app_database_${key}`;
            const value = yield redisClient.get(key);
            if (value) {
                try {
                    const deserializedValue = unserialize(value); // PHP unserialize
                    console.log(`Value retrieved for key ${key}:`, deserializedValue);
                    return deserializedValue;
                }
                catch (error) {
                    console.log(`Failed to deserialize, returning raw value for key ${key}:`, value);
                    return value; // If not serialized, return raw value
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
// Clear (delete) a specific cache key from Redis
function clearCacheValue(key) {
    return __awaiter(this, void 0, void 0, function* () {
        try {
            // Custom key format: wb_app_database_{key}
            key = `wb_app_database_${key}`;
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
module.exports = {
    setCacheValue,
    getCacheValue,
    clearCacheValue
};
