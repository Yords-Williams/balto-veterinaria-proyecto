// La IA solo puede seleccionar consultas de lectura predefinidas.
const fallback = $('Detectar intención').first().json;
let context = {...fallback, engine: 'rules'};
if (fallback.status === 200) {
  try {
    const response = $input.first().json;
    if (response.candidates?.[0]?.finishReason !== 'STOP') throw new Error('Respuesta incompleta');
    const value = JSON.parse(response.candidates[0].content.parts.map(p => p.text || '').join(''));
    const intents = ['product','category','categories','services','schedule','greeting','unknown'];
    if (!intents.includes(value.intent) || typeof value.q !== 'string' || value.q.length > 100) throw new Error('Formato no válido');
    const q = value.q.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9\s-]/g, ' ').trim();
    if (value.intent === 'product' && !q) throw new Error('Falta producto');
    context = {...fallback, intent: value.intent, q, text: fallback.text + ' ' + q, engine: 'gemini'};
  } catch { /* Timeout, cuota o JSON inválido: conservar reglas existentes. */ }
}
return [{json: context}];
