const fs = require('fs');
const util = require('util');
const { execFile } = require('child_process');
const path = require('path');

// Step 1: Load wasm_exec.js (adjust the path to where you store the wasm_exec.js file)
const wasmExecPath = path.join(__dirname, 'wasm_exec.js');
require(wasmExecPath); // This defines `global.Go`

// Step 2: Create a function to run WebAssembly in Node.js
async function solveTaskInNode(wasmPath, taskInput) {
    return new Promise((resolve, reject) => {
        // Initialize the Go runtime
        const go = new Go();

        // Load the WebAssembly file from the file system
        const wasmBuffer = fs.readFileSync(wasmPath);

        // Step 3: Instantiate WebAssembly with the Go import object
        WebAssembly.instantiate(wasmBuffer, go.importObject).then((result) => {
            go.run(result.instance); // Run the Go runtime
            try {
                // Now call solveTask
                const solveTaskResult = global.solveTask(taskInput);
                resolve(solveTaskResult);
            } catch (error) {
                reject(error);
            }
        }).catch((err) => {
            reject(err);
        });
    });
}

// Step 4: Define the wasmPath and taskInput
const wasmPath = path.join(__dirname, 'solve.wasm'); // Path to your solve.wasm file
const taskInput = {
    "timestamp": 1727186437,
    "value": "c90e04190dd47f2f312b4c865e2f395d3967f5d097fbd13871b4e155e9d8aa01",
    "threshold_hex": "07ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff",
    "match_bits": 5,
    "buffer_len": 64,
    "answers_count": 5,
    "r": 8,
    "n": 8,
    "client_id": "",
    "sign": "5bbd6a2ebb9b7cea45e7bd619695c9ae7b0523eee1d8112621850cdbe2235e78"
};

// Export the function and related variables
module.exports = {
    solveTaskInNode,
    wasmPath,
    taskInput
};
