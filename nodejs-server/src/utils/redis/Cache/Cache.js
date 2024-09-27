const redisClient = require('../redisClient');
const { serialize, unserialize } = require('php-serialize');
const axios = require('axios');

class Cache {
    constructor() {
        this.prefix = 'wb_app_database_';
    }

    // Set a value in the cache
    async set(key, value, expirationInSeconds = 3600) {
        key = this.prefix + key;
        try {
            const serializedValue = serialize(value);
            await redisClient.set(key, serializedValue, {
                EX: expirationInSeconds // Expiration time in seconds
            });
            console.log(`Value set for key: ${key}`);
        } catch (err) {
            console.error(`Error setting cache value for key ${key}:`, err);
        }
    }

    // Get a value from the cache
    async get(key) {
        key = this.prefix + key;
        try {
            const value = await redisClient.get(key);
            if (value) {
                try {
                    const deserializedValue = unserialize(value); // PHP unserialize
                    console.log(`Value retrieved for key ${key}:`, deserializedValue);
                    return deserializedValue;
                } catch (error) {
                    console.log(`Failed to deserialize, returning raw value for key ${key}:`, value);
                    return value; // Return raw value if not serialized
                }
            } else {
                console.log(`Key ${key} not found in cache.`);
                return null;
            }
        } catch (err) {
            console.error(`Error getting cache value for key ${key}:`, err);
            return null;
        }
    }

    async getUserByTelegramId(telegramId) {
        try{
            let user = await this.get(`user_telegram_id_${telegramId}`);
            console.log('userInCache', user);
            if (user) {
                return user;
            }
            const response = await axios.get(`${process.env.LARAVEL_API_URL}/users/telegram/${telegramId}`);
            console.log('User:', response.data);
            return response.data;
        } catch (error) {
            console.error('Error fetching user:', error);
            return null;
        }
    }

    // Delete a value from the cache
    async forget(key) {
        key = this.prefix + key;
        try {
            const result = await redisClient.del(key);
            if (result === 1) {
                console.log(`Successfully deleted key: ${key}`);
                return true;
            } else {
                console.log(`Key ${key} does not exist or could not be deleted.`);
                return false;
            }
        } catch (err) {
            console.error(`Error deleting cache value for key ${key}:`, err);
            return false;
        }
    }
}

module.exports = new Cache();
