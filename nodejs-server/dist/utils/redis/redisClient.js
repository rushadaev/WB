"use strict";
const redis = require('redis');
// Create a Redis client
const redisClient = redis.createClient({
    url: 'redis://redis:6379/1' // Use Redis container name as host
});
// Connect to Redis
redisClient.connect()
    .then(() => console.log('Connected to Redis'))
    .catch(err => console.error('Redis connection error:', err));
module.exports = redisClient;
