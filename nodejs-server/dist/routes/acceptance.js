"use strict";
const express = require('express');
const router = express.Router();
const acceptanceController = require('../controllers/acceptanceController');
// GET /api/acceptance/fetchTimeslots?userId=...&preorderId=...
router.get('/fetchTimeslots', acceptanceController.fetchTimeslots);
// POST /api/acceptance/bookTimeslot
router.post('/bookTimeslot', acceptanceController.bookTimeslot);
module.exports = router;
