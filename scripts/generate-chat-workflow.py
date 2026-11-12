#!/usr/bin/env python3
"""Genera el export importable; los secretos se asignan como credencial al arrancar."""
import json
from pathlib import Path
ROOT = Path(__file__).resolve().parents[1]
nodes = []
def node(name, kind, parameters, version=1, **extra):
    nodes.append(dict(id=name, name=name, type='n8n-nodes-base.'+kind, typeVersion=version, position=[len(nodes)*260, 300], parameters=parameters, **extra))
node('Recibir chat', 'webhook', {'httpMethod': 'POST', 'path': 'chat', 'responseMode': 'responseNode', 'options': {'allowedOrigins': 'http://localhost:8080'}}, 2.1, webhookId='balto-chat-webhook')
node('Detectar intención', 'code', {'jsCode': (ROOT/'n8n/code/detect.js').read_text()}, 2)
# Gemini interpreta la pregunta; Drupal conserva la autoridad sobre los datos.
node('Preparar Gemini', 'code', {'jsCode': 'return [{json: {...$input.first().json, geminiRequest: ' + (ROOT/'n8n/code/gemini-request.js').read_text().strip() + '}}];'}, 2)
node('Usar Gemini', 'if', {'conditions': {'options': {'caseSensitive': True, 'typeValidation': 'strict', 'version': 2}, 'conditions': [{'id': 'valid-message', 'leftValue': '={{ $json.status === 200 }}', 'rightValue': True, 'operator': {'type': 'boolean', 'operation': 'true', 'singleValue': True}}], 'combinator': 'and'}, 'options': {}}, 2.2)
node('Interpretar con Gemini', 'httpRequest', {
    'method': 'POST', 'url': 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent',
    'authentication': 'genericCredentialType', 'genericAuthType': 'httpHeaderAuth',
    'sendBody': True, 'specifyBody': 'json',
    'jsonBody': '={{ $json.geminiRequest }}',
    'options': {'timeout': 10000, 'response': {'response': {'responseFormat': 'json'}}}
}, 4.2, credentials={'httpHeaderAuth': {'id': 'baltoGeminiApi', 'name': 'BALTO Gemini'}}, onError='continueRegularOutput')
node('Validar interpretación', 'code', {'jsCode': (ROOT/'n8n/code/gemini-validate.js').read_text()}, 2)
def http(name, url):
    node(name, 'httpRequest', {'url': url, 'authentication': 'genericCredentialType', 'genericAuthType': 'httpHeaderAuth', 'options': {'timeout': 8000, 'response': {'response': {'responseFormat': 'json'}}}}, 4.2, credentials={'httpHeaderAuth': {'id': 'baltoDrupalApi', 'name': 'BALTO API interna'}}, onError='continueRegularOutput')
http('Leer contacto', 'http://drupal/api/vet/schedule')
http('Leer categorías', 'http://drupal/api/vet/categories')
node('Preparar consulta', 'code', {'jsCode': (ROOT/'n8n/code/plan.js').read_text()}, 2)
http('Consultar Drupal', "={{ 'http://drupal' + $json.path }}")
node('Responder en español', 'code', {'jsCode': (ROOT/'n8n/code/format.js').read_text()}, 2)
node('Enviar respuesta', 'respondToWebhook', {'respondWith': 'json', 'responseBody': '={{ $json.body }}', 'options': {'responseCode': '={{ $json.status }}', 'responseHeaders': {'entries': [{'name': 'Cache-Control', 'value': 'no-store'}, {'name': 'X-Content-Type-Options', 'value': 'nosniff'}]}}}, 1.4)
connections = {a['name']: {'main': [[{'node': b['name'], 'type': 'main', 'index': 0}]]} for a,b in zip(nodes,nodes[1:])}
connections['Usar Gemini'] = {'main': [[{'node': 'Interpretar con Gemini', 'type': 'main', 'index': 0}], [{'node': 'Validar interpretación', 'type': 'main', 'index': 0}]]}
workflow = {'id': 'baltoChatbot', 'name': 'BALTO · Asistente con Gemini y respaldo por reglas', 'active': False, 'nodes': nodes, 'connections': connections, 'settings': {'executionOrder': 'v1', 'saveDataSuccessExecution': 'none', 'saveDataErrorExecution': 'none', 'saveManualExecutions': False, 'executionTimeout': 45}, 'tags': []}
(ROOT/'n8n/workflows/chatbot.json').write_text(json.dumps(workflow, ensure_ascii=False, indent=2)+'\n')
