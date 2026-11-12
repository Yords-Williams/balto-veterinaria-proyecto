// Este proceso se ejecuta antes de n8n. Ningún secreto se añade al workflow.
const fs = require('node:fs');
const crypto = require('node:crypto');
const origin = new URL(process.env.CHAT_ALLOWED_ORIGIN).origin;
const base = new URL(process.env.DRUPAL_BASE_URL).origin;
const workflow = JSON.parse(fs.readFileSync('/workflows/chatbot.json', 'utf8'));
workflow.nodes.find(n => n.name === 'Recibir chat').parameters.options.allowedOrigins = origin;
workflow.nodes.find(n => n.name === 'Detectar intención').parameters.jsCode = workflow.nodes.find(n => n.name === 'Detectar intención').parameters.jsCode.replace("const allowedOrigin = 'http://localhost:8080';", `const allowedOrigin = ${JSON.stringify(origin)};`);
workflow.nodes.find(n => n.name === 'Responder en español').parameters.jsCode = workflow.nodes.find(n => n.name === 'Responder en español').parameters.jsCode.replace("const publicBase = 'http://localhost:8080';", `const publicBase = ${JSON.stringify(base)};`);
const serialized = JSON.stringify(workflow);
fs.writeFileSync('/tmp/balto-chatbot.json', serialized, { mode: 0o600 });
fs.writeFileSync('/tmp/balto-workflow.sha256', crypto.createHash('sha256').update(serialized).digest('hex'));
const key = process.env.VET_API_KEY;
if (!key || key.length < 32) throw new Error('Falta configurar VET_API_KEY.');
fs.writeFileSync('/tmp/balto-credentials.json', JSON.stringify([{ id: 'baltoDrupalApi', name: 'BALTO API interna', type: 'httpHeaderAuth', data: { name: 'X-API-KEY', value: key } }]), { mode: 0o600 });
