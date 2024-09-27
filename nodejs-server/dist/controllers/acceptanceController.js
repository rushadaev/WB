"use strict";
// nodejs-server/controllers/acceptanceController.js
var __awaiter = (this && this.__awaiter) || function (thisArg, _arguments, P, generator) {
    function adopt(value) { return value instanceof P ? value : new P(function (resolve) { resolve(value); }); }
    return new (P || (P = Promise))(function (resolve, reject) {
        function fulfilled(value) { try { step(generator.next(value)); } catch (e) { reject(e); } }
        function rejected(value) { try { step(generator["throw"](value)); } catch (e) { reject(e); } }
        function step(result) { result.done ? resolve(result.value) : adopt(result.value).then(fulfilled, rejected); }
        step((generator = generator.apply(thisArg, _arguments || [])).next());
    });
};
const fs = require('fs');
const path = require('path');
const axios = require('axios');
const { solveTaskInNode, wasmPath } = require('../utils/pow/solveTask');
/**
 * Fetch Timeslots Endpoint
 * Expects a query parameter: userId and preorderId
 */
fetchTimeslots = (req, res) => __awaiter(void 0, void 0, void 0, function* () {
    var _a;
    const { userId, preorderId } = req.query;
    if (!userId || !preorderId) {
        return res.status(400).json({ error: 'Missing userId or preorderId parameter.' });
    }
    try {
        // Path to the user's state.json
        const statePath = path.join('/var/www/wb-back/storage/state', `${userId}.json`);
        if (!fs.existsSync(statePath)) {
            return res.status(404).json({ error: 'User state not found.' });
        }
        const storageState = JSON.parse(fs.readFileSync(statePath, 'utf-8'));
        // Extract cookies and WBTokenV3
        const cookies = storageState.cookies;
        let cookieHeader = cookies.map(cookie => `${cookie.name}=${cookie.value}`).join('; ');
        const originData = storageState.origins.find(origin => origin.origin === 'https://seller.wildberries.ru');
        if (!originData) {
            return res.status(400).json({ error: 'Origin data not found in state.' });
        }
        const wbTokenEntry = originData.localStorage.find(item => item.name === 'wb-eu-passport-v2.access-token');
        const wbTokenValue = wbTokenEntry ? wbTokenEntry.value : null;
        if (!wbTokenValue) {
            return res.status(400).json({ error: 'WBTokenV3 token not found in localStorage.' });
        }
        // Add WBTokenV3 to cookies
        cookieHeader += `; WBTokenV3=${wbTokenValue}`;
        // Define headers
        const headers = {
            'Content-Type': 'application/json',
            'Cookie': cookieHeader,
            'Accept': '*/*',
            'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
            'Origin': 'https://seller.wildberries.ru',
            'Referer': 'https://seller.wildberries.ru/',
            'Accept-Language': 'ru,en-GB;q=0.9,en-US;q=0.8,en;q=0.7',
            'sec-ch-ua': '"Chromium";v="128", "Not;A=Brand";v="24", "Google Chrome";v="128"',
            'sec-ch-ua-mobile': '?0',
            'sec-ch-ua-platform': '"macOS"',
            'sec-fetch-dest': 'empty',
            'sec-fetch-mode': 'cors',
            'sec-fetch-site': 'same-site',
        };
        // **a. Get Acceptance Costs**
        const acceptanceCostsUrl = 'https://seller-supply.wildberries.ru/ns/sm-supply/supply-manager/api/v1/supply/getAcceptanceCosts';
        const dateFrom = new Date().toISOString();
        const dateTo = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString(); // 30 days from now
        const acceptanceCostsData = {
            "params": {
                "dateFrom": dateFrom,
                "dateTo": dateTo,
                "preorderID": Number(preorderId)
            },
            "jsonrpc": "2.0",
            "id": "json-rpc_35"
        };
        const acceptanceCostsResponse = yield axios.post(acceptanceCostsUrl, acceptanceCostsData, { headers });
        const acceptanceCostsResult = (_a = acceptanceCostsResponse.data) === null || _a === void 0 ? void 0 : _a.result;
        // Filter coefficients > -1
        acceptanceCostsResult.costs = acceptanceCostsResult.costs.filter(coefficient => coefficient.coefficient > -1);
        res.status(200).json({
            message: 'Fetched acceptance costs and delivery date successfully.',
            data: {
                acceptanceCosts: acceptanceCostsResult,
            }
        });
    }
    catch (error) {
        console.error('Error fetching acceptance costs:', error.response ? error.response.data : error.message);
        res.status(500).json({ error: 'Internal Server Error.' });
    }
});
/**
 * Book Timeslot Endpoint
 * Expects a JSON body: { userId, preorderId, deliveryDate, warehouseId }
 */
bookTimeslot = (req, res) => __awaiter(void 0, void 0, void 0, function* () {
    const { userId, preorderId, deliveryDate, warehouseId, monopalletCount } = req.body;
    if (!userId || !preorderId || !deliveryDate || !warehouseId) {
        return res.status(400).json({ error: 'Missing required parameters.' });
    }
    try {
        // Path to the user's state.json
        const statePath = path.join('/var/www/wb-back/storage/state', `${userId}.json`);
        if (!fs.existsSync(statePath)) {
            return res.status(404).json({ error: 'User state not found.' });
        }
        const storageState = JSON.parse(fs.readFileSync(statePath, 'utf-8'));
        // Extract cookies and WBTokenV3
        const cookies = storageState.cookies;
        let cookieHeader = cookies.map(cookie => `${cookie.name}=${cookie.value}`).join('; ');
        const originData = storageState.origins.find(origin => origin.origin === 'https://seller.wildberries.ru');
        if (!originData) {
            return res.status(400).json({ error: 'Origin data not found in state.' });
        }
        const wbTokenEntry = originData.localStorage.find(item => item.name === 'wb-eu-passport-v2.access-token');
        const wbTokenValue = wbTokenEntry ? wbTokenEntry.value : null;
        if (!wbTokenValue) {
            return res.status(400).json({ error: 'WBTokenV3 token not found in localStorage.' });
        }
        // Add WBTokenV3 to cookies
        cookieHeader += `; WBTokenV3=${wbTokenValue}`;
        // Define headers
        const headers = {
            'Content-Type': 'application/json',
            'Cookie': cookieHeader,
            'Accept': '*/*',
            'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
            'Origin': 'https://seller.wildberries.ru',
            'Referer': 'https://seller.wildberries.ru/',
            'Accept-Language': 'ru,en-GB;q=0.9,en-US;q=0.8,en;q=0.7',
            'sec-ch-ua': '"Chromium";v="128", "Not;A=Brand";v="24", "Google Chrome";v="128"',
            'sec-ch-ua-mobile': '?0',
            'sec-ch-ua-platform': '"macOS"',
            'sec-fetch-dest': 'empty',
            'sec-fetch-mode': 'cors',
            'sec-fetch-site': 'same-site',
        };
        // **d. Book Timeslot**
        const bookTimeslotUrl = 'https://seller-supply.wildberries.ru/ns/sm/supply-manager/api/v1/plan/add';
        const bookTimeslotData = {
            "params": Object.assign({ "preOrderId": Number(preorderId), "deliveryDate": deliveryDate, "warehouseId": warehouseId }, (monopalletCount !== undefined && { monopalletCount }) // Add monopalletCount if provided
            ),
            "jsonrpc": "2.0",
            "id": "json-rpc_36"
        };
        // **Perform CAPTCHA Solving**
        const task = yield getPowTask();
        const startTime = Date.now();
        const answers = yield solvePowTask(task);
        const latency = (Date.now() - startTime).toFixed(3); // Latency in milliseconds
        console.log('answers', answers);
        const captchaToken = yield verifyPowAnswer(task, answers);
        console.log('captchaToken', captchaToken);
        // Include the CAPTCHA token and latency in headers
        const bookTimeslotHeaders = Object.assign(Object.assign({}, headers), { 'x-wb-captcha-token': captchaToken, 'x-wb-captcha-latency': latency });
        // Make the plan/add request with CAPTCHA headers
        const bookTimeslotResponse = yield axios.post(bookTimeslotUrl, bookTimeslotData, { headers: bookTimeslotHeaders });
        const bookTimeslotResult = bookTimeslotResponse.data.result;
        console.log('Book Timeslot Result:', bookTimeslotResult);
        res.status(200).json({
            message: 'Timeslot booked successfully.',
            data: bookTimeslotResult
        });
    }
    catch (error) {
        console.error('Error booking timeslot:', error.response ? error.response.data : error.message);
        res.status(500).json({ error: 'Internal Server Error.' });
    }
});
// Helper function to wait
const wait = (ms) => new Promise(resolve => setTimeout(resolve, ms));
// Functions for CAPTCHA solving
function getPowTask() {
    return __awaiter(this, arguments, void 0, function* (clientId = null) {
        clientId = clientId || 'e150c635-c6bb-4192-8046-97c2cf81e8b8'; // Use the actual client_id if required
        const getTaskUrl = `https://pow.wildberries.ru/api/v1/short/get-task?client_id=${clientId}`;
        const response = yield axios.get(getTaskUrl, {
            headers: {
                'Content-Type': 'application/json;charset=UTF-8',
                'Accept': '*/*',
                'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
                'Origin': 'https://seller.wildberries.ru',
                'Referer': 'https://seller.wildberries.ru/',
                'sec-ch-ua': '"Chromium";v="128", "Not;A=Brand";v="24", "Google Chrome";v="128"',
                'sec-ch-ua-mobile': '?0',
                'sec-ch-ua-platform': '"macOS"',
                'sec-fetch-dest': 'empty',
                'sec-fetch-mode': 'cors',
                'sec-fetch-site': 'same-site',
            },
        });
        return response.data;
    });
}
function solvePowTask(task) {
    return __awaiter(this, void 0, void 0, function* () {
        let resultArray = [];
        yield solveTaskInNode(wasmPath, task)
            .then(result => {
            var _a;
            resultArray = (_a = JSON.parse(result)) === null || _a === void 0 ? void 0 : _a.answers;
            console.log('solveTask result:', resultArray);
        })
            .catch(err => {
            console.error('Error running solveTask:', err);
        });
        return resultArray;
    });
}
function verifyPowAnswer(task, answers) {
    return __awaiter(this, void 0, void 0, function* () {
        const verifyUrl = 'https://pow.wildberries.ru/api/v1/short/verify-answer';
        const data = {
            task,
            answers,
        };
        console.log('data', data);
        const response = yield axios.post(verifyUrl, JSON.stringify(data), {
            headers: {
                'Content-Type': 'application/json;charset=UTF-8',
                'Accept': '*/*',
                'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
                'Origin': 'https://seller.wildberries.ru',
                'Referer': 'https://seller.wildberries.ru/',
                'sec-ch-ua': '"Chromium";v="128", "Not;A=Brand";v="24", "Google Chrome";v="128"',
                'sec-ch-ua-mobile': '?0',
                'sec-ch-ua-platform': '"macOS"',
                'sec-fetch-dest': 'empty',
                'sec-fetch-mode': 'cors',
                'sec-fetch-site': 'same-site',
            },
        });
        return response.data['wb-captcha-short-token'];
    });
}
module.exports = {
    getPowTask,
    solvePowTask,
    verifyPowAnswer,
    fetchTimeslots,
    bookTimeslot,
};
