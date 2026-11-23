// Este proceso se ejecuta antes de n8n. Ningún secreto se añade al workflow.
const fs = require('node:fs');
const crypto = require('node:crypto');
const origin = new URL(process.env.CHAT_ALLOWED_ORIGIN).origin;
const base = new URL(process.env.DRUPAL_BASE_URL).origin;
const workflow = JSON.parse(fs.readFileSync('/workflows/chatbot.json', 'utf8'));
workflow.nodes.find(n => n.name === 'Recibir chat').parameters.options.allowedOrigins = origin;
workflow.nodes.find(n => n.name === '¿Qué pregunta el cliente?').parameters.jsCode = workflow.nodes.find(n => n.name === '¿Qué pregunta el cliente?').parameters.jsCode.replace("const allowedOrigin = 'http://localhost:8080';", `const allowedOrigin = ${JSON.stringify(origin)};`);
workflow.nodes.find(n => n.name === 'Armar la respuesta').parameters.jsCode = workflow.nodes.find(n => n.name === 'Armar la respuesta').parameters.jsCode.replace("const publicBase = 'http://localhost:8080';", `const publicBase = ${JSON.stringify(base)};`);
const geminiKey = (process.env.GEMINI_API_KEY || '').trim();
const geminiEnabled = process.env.GEMINI_ENABLED === 'true' && !!geminiKey;
const model = process.env.GEMINI_MODEL || 'gemini-3.5-flash-lite';
if (!/^[a-z0-9.-]+$/.test(model)) throw new Error('GEMINI_MODEL inválido');
if (geminiEnabled) {
  const interpreter = workflow.nodes.find(node => node.name === 'Pedir ayuda a Gemini');
  interpreter.parameters.modelId.value = `models/${model}`;
} else {
  const optionalNodes = [
    '¿Hace falta Gemini?', 'Pedir ayuda a Gemini',
  ];
  workflow.nodes = workflow.nodes.filter(node => !optionalNodes.includes(node.name));
  workflow.connections['¿Se puede leer el mensaje?'].main[0] = workflow.connections['¿Hace falta Gemini?'].main[1];
  for (const name of optionalNodes) delete workflow.connections[name];

  const positions = {
    'Entrada': [-10, 0],
    'Interpretación': [475, 0],
    'Datos de Drupal': [700, 0],
    'Respuesta': [1500, 0],
    'Recibir chat': [40, 310],
    '¿Qué pregunta el cliente?': [220, 310],
    '¿Se puede leer el mensaje?': [400, 310],
    'Revisar qué pide el cliente': [580, 310],
    'Ver horarios y contacto': [760, 310],
    '¿Pidió un tipo de producto?': [940, 310],
    'Leer categorías': [1040, 145],
    'Elegir qué buscar': [1140, 310],
    '¿Faltan datos?': [1340, 310],
    'Buscar en la tienda': [1440, 145],
    'Armar la respuesta': [1560, 310],
    'Enviar respuesta': [1760, 310],
    'Explicar el problema': [490, 510],
    'Enviar error': [690, 510],
  };
  workflow.nodes.find(node => node.name === 'Interpretación').parameters.content =
    '## 2. ¿Qué quiere saber?';
  const widths = {'Entrada': 465, 'Interpretación': 205, 'Datos de Drupal': 780, 'Respuesta': 390};
  for (const node of workflow.nodes) {
    node.position = positions[node.name] ?? node.position;
    if (widths[node.name]) node.parameters.width = widths[node.name];
  }
}
const serialized = JSON.stringify(workflow);
fs.writeFileSync('/tmp/balto-chatbot.json', serialized, { mode: 0o600 });
const orderSecret = (process.env.BALTO_N8N_SECRET || '').trim();
const telegramToken = (process.env.TELEGRAM_BOT_TOKEN || '').trim();
const telegramChat = (process.env.TELEGRAM_CHAT_ID || '').trim();
if (telegramChat && !/^-?\d+$/.test(telegramChat)) throw new Error('TELEGRAM_CHAT_ID inválido');
const stockThreshold = Number.parseInt(process.env.STOCK_ALERT_THRESHOLD || '5', 10);
if (!Number.isInteger(stockThreshold) || stockThreshold < 0 || stockThreshold > 100000) throw new Error('STOCK_ALERT_THRESHOLD inválido');
const ordersEnabled = orderSecret.length >= 32 && !!telegramToken && !!telegramChat;
// El Telegram Trigger solo funciona con una URL pública HTTPS.
const botEnabled = ordersEnabled && /^https:\/\//.test(process.env.WEBHOOK_URL || '');
const fill = f => fs.readFileSync(f, 'utf8')
  .replaceAll('__TELEGRAM_CHAT_ID__', telegramChat || '0')
  .replaceAll('__STOCK_THRESHOLD__', String(stockThreshold))
  .replaceAll('__TELEGRAM_PAYMENT_BUTTON__', botEnabled ? 'enabled' : 'disabled');
const orders = fill('/workflows/pedidos.json');
const bot = fill('/workflows/bot.json');
fs.writeFileSync('/tmp/balto-pedidos.json', orders, { mode: 0o600 });
fs.writeFileSync('/tmp/balto-bot.json', bot, { mode: 0o600 });
fs.writeFileSync('/tmp/balto-orders-enabled', ordersEnabled ? '1' : '0', { mode: 0o600 });
fs.writeFileSync('/tmp/balto-bot-enabled', botEnabled ? '1' : '0', { mode: 0o600 });
fs.writeFileSync('/tmp/balto-workflow.sha256', crypto.createHash('sha256').update(serialized + orders + bot + ordersEnabled + botEnabled + (process.env.WEBHOOK_URL || '')).digest('hex'));
const key = process.env.VET_API_KEY;
if (!key || key.length < 32) throw new Error('Falta configurar VET_API_KEY.');
const credentials = [{ id: 'baltoDrupalApi', name: 'BALTO API interna', type: 'httpHeaderAuth', data: { name: 'X-API-KEY', value: key } }];
credentials.push({id: 'baltoGeminiApi', name: 'BALTO Gemini', type: 'googlePalmApi', data: {host: 'https://generativelanguage.googleapis.com', apiKey: geminiEnabled ? geminiKey : 'disabled'}});
credentials.push({ id: 'baltoPedidoSecret', name: 'BALTO secreto de pedidos', type: 'httpHeaderAuth', data: { name: 'X-Balto-Secret', value: ordersEnabled ? orderSecret : crypto.randomBytes(32).toString('hex') } });
credentials.push({ id: 'baltoTelegram', name: 'BALTO Telegram', type: 'telegramApi', data: { accessToken: telegramToken || 'disabled', baseUrl: 'https://api.telegram.org' } });
fs.writeFileSync('/tmp/balto-credentials.json', JSON.stringify(credentials), {mode: 0o600});
