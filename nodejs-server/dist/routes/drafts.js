"use strict";
// nodejs-server/routes/drafts.js
const express = require('express');
const router = express.Router();
const draftsController = require('../controllers/draftsController');
// GET /api/drafts/list
router.get('/list', draftsController.listDrafts);
module.exports = router;
