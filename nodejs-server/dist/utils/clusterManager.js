"use strict";
// nodejs-server/utils/clusterManager.js
var __awaiter = (this && this.__awaiter) || function (thisArg, _arguments, P, generator) {
    function adopt(value) { return value instanceof P ? value : new P(function (resolve) { resolve(value); }); }
    return new (P || (P = Promise))(function (resolve, reject) {
        function fulfilled(value) { try { step(generator.next(value)); } catch (e) { reject(e); } }
        function rejected(value) { try { step(generator["throw"](value)); } catch (e) { reject(e); } }
        function step(result) { result.done ? resolve(result.value) : adopt(result.value).then(fulfilled, rejected); }
        step((generator = generator.apply(thisArg, _arguments || [])).next());
    });
};
const { Cluster } = require('playwright-cluster');
let cluster;
const initializeCluster = () => __awaiter(void 0, void 0, void 0, function* () {
    if (cluster) {
        return cluster;
    }
    cluster = yield Cluster.launch({
        concurrency: Cluster.CONCURRENCY_CONTEXT,
        maxConcurrency: 5,
        timeout: 120000,
        playwrightOptions: {
            headless: true,
        },
        taskTimeout: 60000,
    });
    cluster.on('taskerror', (err, data, willRetry) => {
        if (willRetry) {
            console.warn(`Error processing ${data}: ${err.message}. Retrying...`);
        }
        else {
            console.error(`Failed to process ${data}: ${err.message}`);
        }
    });
    cluster.on('active', () => {
        console.log('A new task has started. Active tasks:', cluster.idle);
    });
    cluster.on('idle', () => {
        console.log('All tasks are complete. Cluster is idle.');
    });
    return cluster;
});
const shutdownCluster = () => __awaiter(void 0, void 0, void 0, function* () {
    if (cluster) {
        yield cluster.close();
        console.log('Cluster has been shut down.');
    }
});
process.on('SIGINT', () => __awaiter(void 0, void 0, void 0, function* () {
    console.log('Received SIGINT. Shutting down cluster...');
    yield shutdownCluster();
    process.exit(0);
}));
process.on('SIGTERM', () => __awaiter(void 0, void 0, void 0, function* () {
    console.log('Received SIGTERM. Shutting down cluster...');
    yield shutdownCluster();
    process.exit(0);
}));
module.exports = {
    initializeCluster,
    shutdownCluster,
};
