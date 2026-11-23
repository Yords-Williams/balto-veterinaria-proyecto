#!/usr/bin/env python3
"""Genera el export importable; los secretos se asignan como credencial al arrancar."""
import json
from pathlib import Path
ROOT = Path(__file__).resolve().parents[1]
nodes = []
def node(name, kind, parameters, version=1, **extra):
    nodes.append(dict(id=name, name=name, type=kind if kind.startswith('@') else 'n8n-nodes-base.'+kind, typeVersion=version, position=[len(nodes)*260, 300], parameters=parameters, **extra))
node('Recibir chat', 'webhook', {'httpMethod': 'POST', 'path': 'chat', 'responseMode': 'responseNode', 'options': {'allowedOrigins': 'http://localhost:8080'}}, 2.1, webhookId='balto-chat-webhook')
node('¿Qué pregunta el cliente?', 'code', {'jsCode': (ROOT/'n8n/code/detect.js').read_text()}, 2)
# Gemini interpreta la pregunta; Drupal conserva la autoridad sobre los datos.
node('Pedir ayuda a Gemini', '@n8n/n8n-nodes-langchain.googleGemini', {
    'resource': 'text', 'operation': 'message',
    'modelId': {'__rl': True, 'mode': 'list', 'value': 'models/gemini-3.5-flash-lite'},
    'messages': {'values': [{'role': 'user', 'content': '={{ $("Recibir chat").first().json.body.message }}'}]},
    'simplify': False, 'jsonOutput': True,
    'options': {
        'systemMessage': (ROOT/'n8n/code/gemini-prompt.txt').read_text().strip(),
        'temperature': 0, 'maxOutputTokens': 256,
    },
}, 1.2, credentials={'googlePalmApi': {'id': 'baltoGeminiApi', 'name': 'BALTO Gemini'}}, onError='continueRegularOutput')
node('Revisar qué pide el cliente', 'code', {'jsCode': (ROOT/'n8n/code/gemini-validate.js').read_text()}, 2)
def http(name, url):
    node(name, 'httpRequest', {'url': url, 'authentication': 'genericCredentialType', 'genericAuthType': 'httpHeaderAuth', 'options': {'timeout': 8000, 'response': {'response': {'responseFormat': 'json'}}}}, 4.2, credentials={'httpHeaderAuth': {'id': 'baltoDrupalApi', 'name': 'BALTO API interna'}}, onError='continueRegularOutput')
def condition(name, expression):
    node(name, 'if', {'conditions': {
        'options': {'typeValidation': 'strict', 'version': 2},
        'conditions': [{'id': name, 'leftValue': expression,
                        'operator': {'type': 'boolean', 'operation': 'true', 'singleValue': True}}],
        'combinator': 'and',
    }, 'options': {}}, 2.2)

condition('¿Hace falta Gemini?', '={{ $json.intent === "unknown" }}')
condition('¿Se puede leer el mensaje?', '={{ $json.status === 200 }}')
node('Explicar el problema', 'code', {'jsCode': '''
const { status } = $input.first().json;
const reply = status === 403
  ? 'Este chat solo está habilitado desde la página de BALTO.'
  : 'Escribe un mensaje de entre 1 y 500 caracteres.';
return [{ json: { status, body: { reply, intent: 'unknown', engine: 'rules', links: [] } } }];
'''.strip()}, 2)

http('Ver horarios y contacto', 'http://drupal/api/vet/schedule')
condition('¿Pidió un tipo de producto?', '={{ $("Revisar qué pide el cliente").first().json.intent === "category" }}')
http('Leer categorías', 'http://drupal/api/vet/categories')
node('Elegir qué buscar', 'code', {'jsCode': (ROOT/'n8n/code/plan.js').read_text()}, 2)
condition('¿Faltan datos?', '={{ $json.path !== "/api/vet/schedule" }}')
http('Buscar en la tienda', "={{ 'http://drupal' + $json.path }}")
node('Armar la respuesta', 'code', {'jsCode': (ROOT/'n8n/code/format.js').read_text()}, 2)
response_parameters = {'respondWith': 'json', 'responseBody': '={{ $json.body }}', 'options': {'responseCode': '={{ $json.status }}', 'responseHeaders': {'entries': [{'name': 'Cache-Control', 'value': 'no-store'}, {'name': 'X-Content-Type-Options', 'value': 'nosniff'}]}}}
node('Enviar respuesta', 'respondToWebhook', response_parameters, 1.4)
node('Enviar error', 'respondToWebhook', response_parameters, 1.4)
def routes(*targets):
    return {'main': [[{'node': target, 'type': 'main', 'index': 0}] for target in targets]}

main_path = [
    'Recibir chat', '¿Qué pregunta el cliente?', '¿Se puede leer el mensaje?', '¿Hace falta Gemini?', 'Revisar qué pide el cliente',
    'Ver horarios y contacto', '¿Pidió un tipo de producto?', 'Elegir qué buscar',
    '¿Faltan datos?', 'Armar la respuesta',
    'Enviar respuesta',
]
connections = {source: routes(target) for source, target in zip(main_path, main_path[1:])}
connections.update({
    '¿Se puede leer el mensaje?': routes('¿Hace falta Gemini?', 'Explicar el problema'),
    'Explicar el problema': routes('Enviar error'),
    '¿Hace falta Gemini?': routes('Pedir ayuda a Gemini', 'Revisar qué pide el cliente'),
    'Pedir ayuda a Gemini': routes('Revisar qué pide el cliente'),
    '¿Pidió un tipo de producto?': routes('Leer categorías', 'Elegir qué buscar'),
    'Leer categorías': routes('Elegir qué buscar'),
    '¿Faltan datos?': routes('Buscar en la tienda', 'Armar la respuesta'),
    'Buscar en la tienda': routes('Armar la respuesta'),
})

positions = {name: [index * 190, 300] for index, name in enumerate(main_path)}
positions.update({
    'Explicar el problema': [380, 510],
    'Enviar error': [570, 510],
    'Pedir ayuda a Gemini': [655, 120],
    'Leer categorías': [1235, 120],
    'Buscar en la tienda': [1615, 120],
})
for item in nodes:
    item['position'] = positions[item['name']]

for name, title, description, x, y, width, color in [
    ('Entrada', '1. Llega un mensaje', '', -45, -70, 500, 7),
    ('Interpretación', '2. ¿Qué quiere saber?', 'Gemini ayuda si la pregunta no queda clara.', 475, -70, 410, 7),
    ('Datos de Drupal', '🗂️ 3. Buscar en la tienda', 'Busca datos de la tienda y la clínica.', 905, -70, 810, 7),
    ('Respuesta', '4. Responder al cliente', '', 1735, -70, 390, 7),
]:
    node(name, 'stickyNote', {
        'content': f'## {title}\n{description}', 'width': width, 'height': 95, 'color': color,
    })
    nodes[-1]['position'] = [x, y]

workflow = {'id': 'baltoChatbot', 'name': 'BALTO · Chatbot web', 'active': False, 'nodes': nodes, 'connections': connections, 'settings': {'executionOrder': 'v1', 'saveDataSuccessExecution': 'none', 'saveDataErrorExecution': 'none', 'saveManualExecutions': False, 'executionTimeout': 55}, 'tags': []}
(ROOT/'n8n/workflows/chatbot.json').write_text(json.dumps(workflow, ensure_ascii=False, indent=2)+'\n')
