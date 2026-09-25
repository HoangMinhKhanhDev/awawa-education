#!/usr/bin/env node
/**
 * Cầu nối tới Hostinger MCP qua stdio JSON-RPC.
 *
 * Xác thực dùng phiên MCP đã lưu sẵn trên máy (không chứa token trong repo).
 *
 * Cách dùng:
 *   node scripts/hostinger-call.cjs __list
 *   node scripts/hostinger-call.cjs __find "upload|domain"
 *   node scripts/hostinger-call.cjs __schema name=hosting_generateUploadURLV1
 *   node scripts/hostinger-call.cjs <tool> key=value,key2=value2
 *   node scripts/hostinger-call.cjs @payload.json        # {"tool":"...","args":{...}}
 *
 * Biến môi trường:
 *   HOSTINGER_MCP_PKG     gói MCP (mặc định @hostinger/mcp)
 *   HOSTINGER_CALL_LIMIT  giới hạn ký tự output (mặc định 6000)
 */

const { spawn } = require('node:child_process');
const fs = require('node:fs');

const PKG = process.env.HOSTINGER_MCP_PKG || '@hostinger/mcp';
const LIMIT = Number(process.env.HOSTINGER_CALL_LIMIT || 6000);
const PROTOCOL_VERSION = '2024-11-05';

function log(...args) {
    process.stderr.write('[hostinger-call] ' + args.join(' ') + '\n');
}

function truncate(text) {
    if (text.length <= LIMIT) {
        return text;
    }

    return text.slice(0, LIMIT) + `\n... [cắt bớt, tổng ${text.length} ký tự. Đặt HOSTINGER_CALL_LIMIT để xem đủ]`;
}

function parseValue(raw) {
    if (raw === 'true') return true;
    if (raw === 'false') return false;
    if (raw === 'null') return null;

    if (/^-?\d+(\.\d+)?$/.test(raw)) {
        return Number(raw);
    }

    if ((raw.startsWith('{') && raw.endsWith('}')) || (raw.startsWith('[') && raw.endsWith(']'))) {
        try {
            return JSON.parse(raw);
        } catch (error) {
            return raw;
        }
    }

    return raw;
}

function parseArgs(pairs) {
    const args = {};

    for (const pair of pairs) {
        const index = pair.indexOf('=');

        if (index === -1) {
            args[pair] = true;

            continue;
        }

        const key = pair.slice(0, index);
        const value = pair.slice(index + 1);

        if (args[key] === undefined) {
            args[key] = parseValue(value);
        } else if (Array.isArray(args[key])) {
            args[key].push(parseValue(value));
        } else {
            args[key] = [args[key], parseValue(value)];
        }
    }

    return args;
}

class McpClient {
    constructor() {
        this.child = spawn('npx', ['-y', PKG], {
            stdio: ['pipe', 'pipe', 'pipe'],
            shell: process.platform === 'win32',
            env: process.env,
        });

        this.buffer = '';
        this.nextId = 1;
        this.pending = new Map();

        this.child.stdout.on('data', (chunk) => this.onData(chunk));
        this.child.stderr.on('data', (chunk) => log('stderr: ' + chunk.toString().trim()));
        this.child.on('exit', (code) => {
            for (const { reject } of this.pending.values()) {
                reject(new Error('MCP process exited with code ' + code));
            }
            this.pending.clear();
        });
    }

    onData(chunk) {
        this.buffer += chunk.toString();

        let index;

        while ((index = this.buffer.indexOf('\n')) !== -1) {
            const line = this.buffer.slice(0, index).trim();
            this.buffer = this.buffer.slice(index + 1);

            if (!line) {
                continue;
            }

            let message;

            try {
                message = JSON.parse(line);
            } catch (error) {
                continue;
            }

            if (message.id !== undefined && this.pending.has(message.id)) {
                const { resolve, reject } = this.pending.get(message.id);
                this.pending.delete(message.id);

                if (message.error) {
                    reject(new Error(JSON.stringify(message.error)));
                } else {
                    resolve(message.result);
                }
            }
        }
    }

    send(method, params) {
        const id = this.nextId++;

        const payload = { jsonrpc: '2.0', id, method };

        if (params !== undefined) {
            payload.params = params;
        }

        this.child.stdin.write(JSON.stringify(payload) + '\n');

        return new Promise((resolve, reject) => {
            this.pending.set(id, { resolve, reject });

            setTimeout(() => {
                if (this.pending.has(id)) {
                    this.pending.delete(id);
                    reject(new Error('Timeout khi gọi ' + method));
                }
            }, 120000);
        });
    }

    notify(method, params) {
        const payload = { jsonrpc: '2.0', method };

        if (params !== undefined) {
            payload.params = params;
        }

        this.child.stdin.write(JSON.stringify(payload) + '\n');
    }

    async initialize() {
        await this.send('initialize', {
            protocolVersion: PROTOCOL_VERSION,
            capabilities: {},
            clientInfo: { name: 'awawa-hostinger-call', version: '1.0.0' },
        });

        this.notify('notifications/initialized');
    }

    close() {
        try {
            this.child.stdin.end();
        } catch (error) {
            // ignore
        }

        setTimeout(() => {
            try {
                this.child.kill();
            } catch (error) {
                // ignore
            }
        }, 200);
    }
}

function extractContent(result) {
    if (result === undefined || result === null) {
        return '';
    }

    if (Array.isArray(result.content)) {
        return result.content
            .map((item) => {
                if (item.type === 'text') return item.text;
                if (item.type === 'image') return '[image ' + (item.mimeType || '') + ']';

                return JSON.stringify(item);
            })
            .join('\n');
    }

    return JSON.stringify(result, null, 2);
}

async function main() {
    const argv = process.argv.slice(2);

    if (argv.length === 0) {
        log('Thiếu lệnh. Ví dụ: node scripts/hostinger-call.cjs __list');
        process.exit(1);
    }

    const client = new McpClient();

    try {
        await client.initialize();

        const first = argv[0];

        if (first === '__list' || first === '__find') {
            const result = await client.send('tools/list');
            const tools = result?.tools || [];

            if (first === '__list') {
                console.log(truncate(tools.map((tool) => tool.name).join('\n')));

                return;
            }

            const pattern = new RegExp(argv[1] || '.', 'i');

            const matched = tools.filter(
                (tool) =>
                    pattern.test(tool.name) ||
                    pattern.test(tool.description || ''),
            );

            console.log(
                truncate(
                    matched
                        .map((tool) => tool.name + ' :: ' + (tool.description || '').replace(/\s+/g, ' ').slice(0, 160))
                        .join('\n') || '(không có tool khớp)',
                ),
            );

            return;
        }

        if (first === '__schema') {
            const args = parseArgs(argv.slice(1));
            const result = await client.send('tools/list');
            const tools = result?.tools || [];
            const tool = tools.find((item) => item.name === args.name);

            console.log(truncate(tool ? JSON.stringify(tool.inputSchema || {}, null, 2) : 'Không tìm thấy tool ' + args.name));

            return;
        }

        let tool = first;
        let args = {};

        if (first.startsWith('@')) {
            const payload = JSON.parse(fs.readFileSync(first.slice(1), 'utf8'));
            tool = payload.tool;
            args = payload.args || {};
        } else {
            args = parseArgs(argv.slice(1));
        }

        const result = await client.send('tools/call', { name: tool, arguments: args });

        console.log(truncate(extractContent(result)));
    } catch (error) {
        log('LỖI: ' + error.message);
        process.exitCode = 1;
    } finally {
        client.close();
    }
}

main();
