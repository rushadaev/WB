"use strict";
const winston = require('winston');
const loggerTelegram = winston.createLogger({
    level: 'info',
    format: winston.format.json(),
    defaultMeta: { service: 'nodejs-server' },
    transports: [
        new winston.transports.Console({
            timestamp: true,
            format: winston.format.simple(),
        }),
        new winston.transports.File({
            filename: 'telegram.log',
            format: winston.format.json(),
        }),
    ],
});
module.exports = loggerTelegram;
