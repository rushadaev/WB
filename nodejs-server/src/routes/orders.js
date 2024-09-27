// nodejs-server/routes/orders.js

const express = require('express');
const router = express.Router();
const ordersController = require('../controllers/ordersController');

// POST /api/orders/create
router.post('/create', ordersController.createOrder);

// GET /api/orders/warehouses
router.get('/warehouses', ordersController.listWarehouses);

module.exports = router;
