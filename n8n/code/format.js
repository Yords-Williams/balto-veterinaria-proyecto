const context = $('Validar interpretación').first().json;
const business = $('Leer contacto').first().json.data;
const response = $input.first().json;
const publicBase = 'http://localhost:8080'; // El instalador adapta esta URL.
let status = context.status;
let reply = '';
let links = [];
const contact = business?.whatsapp ? `Puedes escribirnos al WhatsApp ${business.whatsapp} para consultas o citas.` : '';
const cleanUrl = (url, allowedExternal = false) => {
  if (typeof url !== 'string') return '';
  if (url.startsWith('/') && !url.startsWith('//')) return publicBase + url;
  if (allowedExternal && /^https:\/\/wa\.me\/[0-9]+(?:\?|$)/.test(url)) return url;
  return '';
};
if (status === 403) reply = 'Este chat solo está habilitado desde la página de BALTO.';
else if (status === 400) reply = 'Escribe un mensaje de entre 1 y 500 caracteres.';
else if (!business || !response.data) {
  status = 503;
  reply = 'No puedo consultar los datos de BALTO en este momento. Inténtalo de nuevo en unos minutos.';
}
else if (context.intent === 'greeting') reply = `¡Hola! Soy el asistente de ${business.name}. Puedo consultar productos, precios, disponibilidad, servicios, horarios y direcciones. ¿Qué necesitas?`;
else if (context.intent === 'schedule') {
  reply = `Hospitalización: ${business.hospitalization}.\nAtención en tienda/consultorio: ${business.consultation_schedule}${business.schedule_is_example && !/ejemplo/i.test(business.consultation_schedule) ? ' (horario de ejemplo, pendiente de confirmar)' : ''}\nSedes:\n${(business.addresses || []).map(a => '• ' + a).join('\n')}\n${contact}`;
}
else if (context.intent === 'services') reply = `Nuestros servicios:\n${response.data.map(s => '• ' + s.title).join('\n')}\n${contact}`;
else if (context.intent === 'categories') reply = `Categorías de la tienda:\n${response.data.map(c => '• ' + c.name).join('\n')}\nDime el nombre de una categoría o de un producto para consultar precios y existencias.`;
else if (context.intent === 'product' || context.intent === 'category') {
  const products = response.data;
  if (!products.length) reply = `No encontré productos que coincidan. Puedes consultar por nombre o SKU, o preguntar por las categorías. ${contact}`;
  else {
    reply = products.slice(0, 5).map(p => p.available ? `• ${p.name} cuesta ${p.price} y hay ${p.stock} ${p.stock === 1 ? 'unidad disponible' : 'unidades disponibles'}.` : `• ${p.name}: ese producto está agotado por ahora. Su precio de referencia es ${p.price}.`).join('\n');
    if (products.length > 5 || (response.meta?.total || 0) > 5) reply += '\nTe muestro hasta cinco opciones. Escribe un nombre más específico para afinar la búsqueda.';
    if (response.meta?.catalog_is_example) reply += '\nProductos, precios y existencias de ejemplo; pendientes del inventario real de BALTO.';
    links = products.slice(0, 5).map(p => ({ label: 'Ver ' + p.name, url: cleanUrl(p.url) })).filter(l => l.url);
  }
}
else reply = `No entendí tu consulta. Prueba con «precio de Dog Chow», «juguetes», «servicios» u «horarios». ${contact}`;
if (business && status === 200) {
  const url = cleanUrl(business.appointment_url || business.whatsapp_url, true);
  if (url) links.push({ label: 'Consultar por WhatsApp', url });
}
return [{ json: { status, body: { reply, intent: context.intent, engine: context.engine, links } } }];
