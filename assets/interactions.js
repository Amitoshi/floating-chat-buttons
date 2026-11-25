/* Public interaction bridge. No cookies, visitor identifiers, tracking scripts or startup requests. */
(()=>{'use strict';
 const config=window.FCBPublic;
 if(!config||window.FCBEvents)return;
 const channels=new Set(['whatsapp','call','sms','telegram','messenger','email','custom','callback','enquiry']);
 const sources=new Set(['widget','sitewide','form','recovery']);
 const seen=new Set();
 let consentOverride=null;
 const root=()=>{const element=document.getElementById('fcb-live');return element?.dataset.preview==='1'?null:element;};
 const debug=status=>{if(config.ga4_debug&&window.console?.debug)console.debug('[FCB]',status);};
 const consent=()=>{
  if(consentOverride!==null)return consentOverride;
  try{return typeof window.wp_has_consent==='function'&&window.wp_has_consent('statistics')===true;}catch(error){return false;}
 };
 const setAnalytics=value=>{consentOverride=value===true;debug(consentOverride?'Analytics consent granted':'Analytics consent revoked');};
 window.FCBConsent={setAnalytics,grant:()=>setAnalytics(true),revoke:()=>setAnalytics(false)};
 const consentChange=event=>{
  const value=event.detail?.statistics;
  if(value==='allow'||value===true)setAnalytics(true);
  else if(value==='deny'||value===false)setAnalytics(false);
 };
 // Consent API implementations dispatch on either document or window.
 document.addEventListener('wp_listen_for_consent_change',consentChange);
 window.addEventListener('wp_listen_for_consent_change',consentChange);
 const uuid=()=>{
  if(window.crypto?.randomUUID)return window.crypto.randomUUID();
  const bytes=new Uint8Array(16);
  if(window.crypto?.getRandomValues)window.crypto.getRandomValues(bytes);
  else for(let i=0;i<bytes.length;i++)bytes[i]=Math.floor(Math.random()*256);
  bytes[6]=(bytes[6]&15)|64;bytes[8]=(bytes[8]&63)|128;
  const hex=Array.from(bytes,value=>value.toString(16).padStart(2,'0')).join('');
  return hex.slice(0,8)+'-'+hex.slice(8,12)+'-'+hex.slice(12,16)+'-'+hex.slice(16,20)+'-'+hex.slice(20);
 };
 const interactionId=value=>typeof value==='string'&&/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(value)?value:uuid();
 const location=()=>{
  const element=root();
  if(element?.classList.contains('fcb-left'))return 'left';
  if(element?.classList.contains('fcb-right'))return 'right';
  return 'right';
 };
 const localAllowed=()=>Boolean(config.analytics)&&(config.analytics_consent==='not_required'||consent());
 const post=fields=>window.fetch(config.url,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:new URLSearchParams(fields),keepalive:true});
 const local=async(event,channel,source,id)=>{
  if(!localAllowed()||!config.url||typeof window.fetch!=='function')return;
  try{
   // Fetch a current nonce only for a permitted interaction: cached HTML can outlive WP nonces.
   const response=await post({action:'fcb_nonce'});
   if(!response.ok)return debug('Event token unavailable');
   const result=await response.json();
   if(!result.success||typeof result.data?.event_nonce!=='string'||!result.data.event_nonce)return debug('Event token unavailable');
   if(!localAllowed())return debug('Event canceled after consent change');
   const accepted=await post({action:'fcb_event',nonce:result.data.event_nonce,event,channel,source,event_id:id,consent:consent()?'1':'0'});
   debug(accepted.ok?'Local event sent':'Local event unavailable');
  }catch(error){debug('Local event unavailable');}
 };
 const ga=(event,channel,source)=>{
  if(!config.ga4||!consent()||!event)return;
  const params={channel_type:channel,widget_location:location(),source_category:source};
  if(config.ga4_debug)params.debug_mode=true;
  try{
   if(config.ga4_mode==='gtm'){
    if(Array.isArray(window.dataLayer)){window.dataLayer.push({event,...params});debug('GTM event sent');}
    else debug('Existing GTM dataLayer unavailable');
   }else if(typeof window.gtag==='function'){window.gtag('event',event,params);debug('GA4 event sent');}
   else debug('Existing Google tag unavailable');
  }catch(error){debug('Google event unavailable');}
 };
 const emit=(type,channel,source,id)=>{
  if(!channels.has(channel))return null;
  source=sources.has(source)?source:'widget';id=interactionId(id);
  const key=type+':'+id;
  if(seen.has(key))return id;
  seen.add(key);
  if(seen.size>1024)seen.delete(seen.values().next().value);
  // Denied interactions are discarded here and never replayed after a grant.
  const localEvent={contact:source==='sitewide'&&channel==='call'?'phone_click':'contact_click',open:'callback_open',failure:'form_failure'}[type];
  if(localEvent)local(localEvent,channel,source,id);
  const googleEvent={contact:channel==='call'?'fcb_phone_click':channel==='whatsapp'?'fcb_whatsapp_click':'fcb_contact_click',open:'fcb_callback_open',lead:'generate_lead'}[type];
  ga(googleEvent,channel,source);
  return id;
 };
 window.FCBEvents={uuid,consent,
  contact:(channel,source='widget',id)=>emit('contact',channel,source,id),
  open:(source='widget',channel='callback',id)=>emit('open',channel,source,id),
  failure:(source='widget',id)=>emit('failure','callback',source,id),
  // The accepted form endpoint owns local submission totals; this is GA4 only.
  lead:(kind='callback',source='widget',id)=>{
   const accepted=emit('lead',kind,source,id);
   // Functional recovery state is independent of optional analytics consent.
   if(accepted&&['callback','enquiry'].includes(kind))document.dispatchEvent(new CustomEvent('fcb:lead-accepted'));
   return accepted;
  }
 };

 const recovery=config.recovery||{};
 if(!recovery.enabled||config.recoveryAllowed===false)return;
 const readSession=key=>{try{return sessionStorage.getItem(key)==='1';}catch(error){return false;}};
 const writeSession=key=>{try{sessionStorage.setItem(key,'1');}catch(error){/* Private browsing may deny storage. */}};
 const phoneNumber=value=>{
  let number=String(value||'').trim().replace(/^tel:/i,'');
  try{number=decodeURIComponent(number);}catch(error){/* Retain valid digits in malformed URLs. */}
  return number.split(/[;?,#]/,1)[0].replace(/\s*(?:ext\.?|x)\s*\d+\s*$/i,'').replace(/\D/g,'');
 };
 const numbers=new Set((Array.isArray(recovery.numbers)?recovery.numbers:[]).map(phoneNumber).filter(Boolean));
 const allows=number=>number&&(recovery.number_mode==='include'?numbers.has(number):recovery.number_mode==='exclude'?!numbers.has(number):true);
 let pending=false,away=false,due=false,timer=null,shown=readSession('fcb-recovery-shown');
 const cancel=()=>{pending=false;away=false;due=false;if(timer!==null)window.clearTimeout(timer);timer=null;};
 const prompt=()=>{
  if(!pending||shown||readSession('fcb-dismissed')||readSession('fcb-recovery-shown'))return cancel();
  if(document.hidden){due=true;return;}
  const element=root();
  if(!element?.fcb?.open)return cancel();
  shown=true;writeSession('fcb-recovery-shown');cancel();
  element.dataset.source='recovery';
  // Widget integration may apply these same overrides; textContent never interprets configured HTML.
  const title=element.querySelector('.fcb-card h2'),copy=element.querySelector('.fcb-card-copy');
  if(title)title.textContent=typeof recovery.title==='string'&&recovery.title?recovery.title:'Need another way to reach us?';
  if(copy)copy.textContent=typeof recovery.text==='string'&&recovery.text?recovery.text:"Leave your phone number or email and we'll get back to you.";
  element.fcb.open('callback',true);
 };
 const returned=()=>{
  if(pending&&(due||(away&&['return','both'].includes(recovery.timing))))prompt();
 };
 document.addEventListener('visibilitychange',()=>{if(pending&&document.hidden)away=true;else if(!document.hidden)returned();});
 window.addEventListener('blur',()=>{if(pending)away=true;});
 window.addEventListener('focus',returned);
 window.addEventListener('pageshow',returned);
 document.addEventListener('click',event=>{
  const target=event.target instanceof Element?event.target:event.target?.parentElement;
  const link=target?.closest('a[href]');
  if(!link||link.closest('.fcb-widget')||!/^tel:/i.test(link.getAttribute('href')||'')||event.button>0)return;
  if(!allows(phoneNumber(link.getAttribute('href'))))return;
  // Observational only: never cancel, stop or await the native telephone action.
  window.FCBEvents.contact('call','sitewide');
  if(recovery.followup?.enabled)return;
  const element=root();
  element?.fcb?.recover?.();
  if(!recovery.prompt||recovery.timing==='manual'||shown||readSession('fcb-dismissed')||readSession('fcb-recovery-shown'))return;
  cancel();pending=true;
  if(['delay','both'].includes(recovery.timing)){
   const delay=Number(recovery.delay);
   timer=window.setTimeout(prompt,Math.max(1,Math.min(3600,Number.isFinite(delay)?delay:8))*1000);
  }
 },true);
})();
