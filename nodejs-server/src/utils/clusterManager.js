// nodejs-server/utils/clusterManager.js

const { Cluster } = require('playwright-cluster');

let cluster;

const initializeCluster = async () => {
    if (cluster) {
        return cluster;
    }

    cluster = await Cluster.launch({
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
        } else {
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
};

const shutdownCluster = async () => {
    if (cluster) {
        await cluster.close();
        console.log('Cluster has been shut down.');
    }
};

process.on('SIGINT', async () => {
    console.log('Received SIGINT. Shutting down cluster...');
    await shutdownCluster();
    process.exit(0);
});

process.on('SIGTERM', async () => {
    console.log('Received SIGTERM. Shutting down cluster...');
    await shutdownCluster();
    process.exit(0);
});

module.exports = {
    initializeCluster,
    shutdownCluster,
};
