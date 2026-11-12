/** Checkout real de Commerce: coordinación, pago pendiente y stock; sin transferir dinero. */
import puppeteer from 'npm:puppeteer-core@25.10.0';
const root=new URL('../',import.meta.url).pathname;
const base=Deno.env.get('BALTO_TEST_URL')||'https://balto-veterinaria.3-211-103-160.sslip.io';
const host=Deno.env.get('BALTO_SSH_HOST')||'ubuntu@3.211.103.160';
const key=Deno.env.get('BALTO_SSH_KEY')||'/home/sae/Downloads/balto-demo.pem';
const args=['-i',key,'-o','BatchMode=yes','-o','ConnectTimeout=10',host];
const quote=s=>"'"+s.replace(/'/g,"'\\''")+"'";
async function remote(command){const r=await new Deno.Command('ssh',{args:[...args,command],stdout:'piped',stderr:'piped'}).output();if(!r.success)throw Error('Falló una operación de verificación en Drupal.');return new TextDecoder().decode(r.stdout);}
const php=async code=>JSON.parse(await remote('sudo docker compose --project-directory /opt/balto-veterinaria exec -T drupal drush php:eval '+quote(code)));
const env=Object.fromEntries((await remote('cat /opt/balto-veterinaria/.env')).split('\n').filter(l=>l.includes('=')&&!l.startsWith('#')).map(l=>[l.slice(0,l.indexOf('=')),l.slice(l.indexOf('=')+1)]));
const lookup='$v=\\Drupal::entityTypeManager()->getStorage("commerce_product_variation")->loadByProperties(["sku"=>"BAL-JUG-001"]);$v=reset($v);';
const stock=()=>php(lookup+'echo json_encode(["stock"=>\\Drupal::service("vet_api.catalog")->stock($v),"product"=>$v->getProductId(),"price"=>$v->getPrice()->getNumber()]);');
const snapshot=id=>php('$o=\\Drupal::entityTypeManager()->getStorage("commerce_order")->load('+id+');$ps=\\Drupal::entityTypeManager()->getStorage("commerce_payment")->loadMultipleByOrder($o);echo json_encode(["number"=>$o->getOrderNumber(),"state"=>$o->getState()->getId(),"paid"=>$o->isPaid(),"simulated"=>(bool)$o->getData("vet_simulated"),"payments"=>array_values(array_map(fn($p)=>["id"=>$p->id(),"state"=>$p->getState()->getId(),"gateway"=>$p->getPaymentGatewayId()],$ps))]);');
const results=[],orders=[],errors=[];
const check=(ok,message)=>{if(!ok)throw Error(message);results.push(message);console.log('OK:',message);};
const browser=await puppeteer.launch({executablePath:'/opt/brave-bin/brave',headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
const start=await stock();
const submit=async(p,s)=>{try{return await Promise.all([p.waitForNavigation({waitUntil:'networkidle0',timeout:45000}),p.click(s)]);}catch(e){console.log('La prueba no pudo avanzar al siguiente paso del formulario.');throw e;}};
let failure;
try{
 const manager=await browser.newPage();await manager.goto(base+'/user/login',{waitUntil:'networkidle0'});await manager.type('#edit-name',env.DRUPAL_STORE_MANAGER_USER);await manager.type('#edit-pass',env.DRUPAL_STORE_MANAGER_PASSWORD);await submit(manager,'#edit-submit');
 for(const gateway of ['yape_coordinado','pagar_al_recoger','pago_simulado']){
  const ctx=await browser.createBrowserContext(),p=await ctx.newPage();p.on('pageerror',e=>errors.push(e.message));
  await p.goto(base+'/product/'+start.product,{waitUntil:'networkidle0'});await submit(p,'.product-cart-form input[type=submit]');await p.goto(base+'/cart',{waitUntil:'networkidle0'});await submit(p,'#edit-checkout');
  const options=await p.$$eval('input[type=radio][name="payment_information[payment_method]"]',es=>es.map(e=>e.value));
  check(options.some(v=>v.includes(gateway)),gateway+': opción disponible');
  await p.$eval('input[name="payment_information[payment_method]"][value*="'+gateway+'"]',e=>e.click());await p.waitForNetworkIdle({idleTime:800});
  await p.$eval('[name="vet_customer[name]"]',(e,value)=>{e.value=value;},'Prueba técnica BALTO '+gateway);await p.$eval('[name="vet_customer[phone]"]',e=>{e.value='900000003';});await p.$eval('[name="contact_information[email]"]',e=>{e.value='verificacion-retiro@example.invalid';});await submit(p,'#edit-actions-next');
  check(new URL(p.url()).pathname.endsWith('/review'),gateway+': revisión accesible');await submit(p,'#edit-actions-next');
  check(new URL(p.url()).pathname.endsWith('/complete'),gateway+': pedido registrado');
  const id=Number(new URL(p.url()).pathname.split('/')[2]);orders.push({id,gateway});
  const data=await snapshot(id);check(data.payments.length===1&&data.payments[0].gateway===gateway,gateway+': un solo pago del medio elegido');
  check(data.state==='validation',gateway+': pedido pendiente de confirmación del negocio');
  check(data.simulated===(gateway==='pago_simulado'),gateway+': indicador de demostración correcto');
  check(data.payments[0].state===(gateway==='pago_simulado'?'completed':'pending'),gateway+': estado de pago correcto');
  check((await stock()).stock===start.stock-orders.length,gateway+': reserva exactamente una unidad');
  await p.reload({waitUntil:'networkidle0'});check((await snapshot(id)).payments.length===1&&(await stock()).stock===start.stock-orders.length,gateway+': recarga no duplica pago ni reserva');
  if(gateway!=='pago_simulado'){
   const href=await p.$eval('.order-whatsapp',e=>e.href);const message=new URL(href).searchParams.get('text');
   check(new URL(href).pathname==='/51986866036'&&message.includes(String(data.number))&&message.includes('Pelota')&&(message.includes(Number(start.price).toFixed(2))||message.includes(Number(start.price).toFixed(2).replace('.',','))),gateway+': WhatsApp con pedido, productos y total; no enviado');
   check(!message.includes('900000003')&&!message.includes('example.invalid'),gateway+': mensaje sin correo ni teléfono del cliente');
   const other=await browser.createBrowserContext();const outsider=await other.newPage();const forbidden=await outsider.goto(p.url(),{waitUntil:'networkidle0'});check(forbidden.status()===403||new URL(outsider.url()).pathname==='/user/login',gateway+': otra sesión no accede al pedido');await other.close();
  }
  if(gateway==='yape_coordinado'){
   await manager.goto(base+'/admin/commerce/orders/'+id+'/payments',{waitUntil:'networkidle0'});
   const receive=await manager.$$eval('a',es=>es.map(e=>e.href).find(h=>/\/payments\/\d+\/operation\/receive/.test(h)));
   if(!receive){console.log('Enlaces de operaciones:',await manager.$$eval('a',es=>es.filter(e=>/recib|receive/i.test(e.textContent)).map(e=>({text:e.textContent,href:e.href}))));throw Error('No aparece la operación administrativa de recibir pago.');}
   await manager.goto(receive,{waitUntil:'networkidle0'});await submit(manager,'form input[type=submit],form button[type=submit]');
   check((await snapshot(id)).paid,'Administrador de tienda registra recepción del pago de prueba');
   check((await stock()).stock===start.stock-orders.length,'Recibir pago no descuenta stock por segunda vez');
  }
  await ctx.close();
 }
 // Cancelar el pedido aún no pagado y probar bloqueo de recepción posterior.
 const unpaid=orders.find(o=>o.gateway==='pagar_al_recoger');
 const canceled=await php('$o=\\Drupal::entityTypeManager()->getStorage("commerce_order")->load('+unpaid.id+');$o->getState()->applyTransitionById("cancel");$o->save();$ps=\\Drupal::entityTypeManager()->getStorage("commerce_payment")->loadMultipleByOrder($o);$p=reset($ps);$blocked=FALSE;try{$p->getPaymentGateway()->getPlugin()->receivePayment($p);}catch(\\Drupal\\commerce_payment\\Exception\\PaymentGatewayException $e){$blocked=TRUE;}echo json_encode(["blocked"=>$blocked,"receive_hidden"=>!$p->getPaymentGateway()->getPlugin()->buildPaymentOperations($p)["receive"]["access"]]);');
 check(canceled.blocked,'No se puede recibir pago de un pedido cancelado');
 check(canceled.receive_hidden,'El panel oculta la operación de recibir en pedidos cancelados');
 check((await stock()).stock===start.stock-orders.length+1,'Cancelar pedido pendiente devuelve su unidad al stock');
 check(errors.length===0,'Sin errores de JavaScript en checkout');
}catch(e){failure=e;console.error(e.message);}
finally{
 // Pedidos técnicos identificados: devolver las unidades reservadas sin tocar ventas ajenas.
 for(const {id} of orders){
  await php('$o=\\Drupal::entityTypeManager()->getStorage("commerce_order")->load('+id+');$ps=\\Drupal::entityTypeManager()->getStorage("commerce_payment")->loadMultipleByOrder($o);foreach($ps as $p){$g=$p->getPaymentGateway()->getPlugin();if($p->getState()->getId()==="completed"){$g->refundPayment($p);}elseif($p->getState()->getId()==="pending"){$g->voidPayment($p);}}if($o->getState()->getId()!=="canceled"){$o->getState()->applyTransitionById("cancel");$o->save();}echo json_encode(TRUE);');
 }
 const end=await stock();check(end.stock===start.stock,'Stock de los pedidos técnicos restaurado al cancelar las pruebas');
 await browser.close();
 await Deno.writeTextFile(root+'docs/verificacion-pagos-manuales.json',JSON.stringify({date:new Date().toISOString(),url:base,results,orders,stock_before:start.stock,stock_after:end.stock,error:failure?.message||null,note:'No se enviaron mensajes ni dinero. Los pagos recibidos de prueba fueron revertidos contablemente y los pedidos técnicos cancelados.'},null,2)+'\n');
}
if(failure)throw failure;
