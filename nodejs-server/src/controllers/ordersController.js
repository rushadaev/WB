// nodejs-server/controllers/ordersController.js

const fs = require('fs');
const path = require('path');
const axios = require('axios');

/**
 * Create Order Endpoint
 * Expects a JSON body: { userId, draftId, warehouseId }
 */
exports.createOrder = async (req, res) => {
    const { userId, draftId, warehouseId, boxTypeMask } = req.body;

    if (!userId || !draftId || !warehouseId || !boxTypeMask) {
        return res.status(400).json({ error: 'Missing userId or draftId or warehouseId or boxTypeMask in request body.' });
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
            'User-Agent': 'Mozilla/5.0 (compatible)',
            'Origin': 'https://seller.wildberries.ru',
            'Referer': 'https://seller.wildberries.ru/',
            'Accept-Language': 'ru,en-GB;q=0.9,en-US;q=0.8,en;q=0.7',
        };

        // **b. Create Supply**
        const createSupplyUrl = 'https://seller-supply.wildberries.ru/ns/sm-supply/supply-manager/api/v1/supply/create';
        //boxTypeMask 4 - Короб
        //boxTypeMask 32 - Монопалеты
        const createSupplyData = {
            "params": {
                "boxTypeMask": boxTypeMask,
                "draftID": draftId,
                "transitWarehouseId": null,
                "warehouseId": warehouseId
            },
            "jsonrpc": "2.0",
            "id": "json-rpc_26"
        };

        const createSupplyResponse = await axios.post(createSupplyUrl, createSupplyData, { headers });
        const createSupplyResult = createSupplyResponse.data;

        // Extract preorderID
        const preorderID = createSupplyResult?.result?.ids[0]?.Id;
        console.log('createSupplyResult:', createSupplyResult);

        res.status(200).json({
            message: 'Order created successfully.',
            preorderID: preorderID,
        });
    } catch (error) {
        console.error('Error during order creation:', error.message);
        res.status(500).json({ error: 'Internal Server Error.' });
    }
};

exports.listWarehouses = async (req, res) => {
    const { userId, draftId } = req.query

    if (!userId || !draftId) {
        return res.status(400).json({ error: 'Missing userId or draftId in request body.' });
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
        };

        // **a. Get Warehouse Recommendations**
        const recommendationsUrl = 'https://seller-supply.wildberries.ru/ns/sm-recommendations/supply-manager/api/v1/recommendations/getRecommendationsForWarehouses';
        const recommendationsData = {
            "params": {
                "draftId": draftId
            },
            "jsonrpc": "2.0",
            "id": "json-rpc_20"
        };

        const recommendationsResponse = await axios.post(recommendationsUrl, recommendationsData, { headers });
        const recommendationsResult = recommendationsResponse.data.result;

        // Filter active warehouses
        recommendationsResult.warehouses = recommendationsResult.warehouses.filter(warehouse => warehouse.isActive);
        if (recommendationsResult.warehouses.length === 0) {
            return res.status(400).json({ error: 'No active warehouses available.' });
        }

        res.status(200).json({
            message: 'Warehouse fetched successfully.',
            warehouses:  recommendationsResult.warehouses,
        });
    } catch (error) {
        console.error('Error during warehouse fetch:', error.message);
        res.status(500).json({ error: 'Internal Server Error.' });
    }
}