// nodejs-server/controllers/draftsController.js

const fs = require('fs');
const path = require('path');
const axios = require('axios');

/**
 * List Drafts Endpoint
 * Expects a query parameter: userId
 */
exports.listDrafts = async (req, res) => {
    const { userId } = req.query;

    if (!userId) {
        return res.status(400).json({ error: 'Missing userId parameter.' });
    }

    try {
        // Path to the user's state.json
        const statePath = path.join('/var/www/wb-back/storage/state', `${userId}_1.json`);

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

        // Define the API endpoint
        const apiUrl = 'https://seller-supply.wildberries.ru/ns/sm-draft/supply-manager/api/v1/draft/listDrafts';

        // Define the request payload
        const data = {
            "params": {
                "filter": {
                    "orderBy": {
                        "createdAt": -1
                    }
                },
                "limit": 10,
                "offset": 0
            },
            "jsonrpc": "2.0",
            "id": "json-rpc_20"
        };

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

        // Make the API request using axios
        const response = await axios.post(apiUrl, data, { headers });

        // Extract and process drafts data
        const drafts = response.data.result.drafts;
        const filteredDrafts = drafts.filter(draft => draft.barcodeQuantity > 0);
        const rowDataArray = filteredDrafts.map(draft => ({
            createdAt: draft.createdAt,
            updatedAt: draft.updatedAt,
            barcodeQuantity: draft.barcodeQuantity.toString(),
            goodQuantity: draft.goodQuantity.toString(),
            author: draft.author,
            draftId: draft?.ID,
            url: `https://seller.wildberries.ru/supplies-management/drafts/draft-detail?draftNumber=${draft.ID}`,
        }));

        res.status(200).json({
            message: `Found ${rowDataArray.length} drafts with barcodeQuantity > 0.`,
            data: rowDataArray,
        });
    } catch (error) {
        console.error('Error fetching drafts data:', error.message);
        res.status(500).json({ error: 'Internal Server Error.' });
    }
};
