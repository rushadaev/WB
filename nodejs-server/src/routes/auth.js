// nodejs-server/routes/auth.js

const express = require('express');
const router = express.Router();
const authController = require('../controllers/authController');

// POST /api/auth/authenticate
router.post('/authenticate', authController.authenticateUser);

module.exports = router;
