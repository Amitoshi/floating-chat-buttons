/* Optional functional call follow-up. No visitor IDs, contact storage or startup requests. */
(()=>{'use strict';
 const settings=window.FCBFollowup,c=settings?.config,followup=c?.recovery?.followup;
 if(!followup?.enabled||settings.pageAllowed===false)return;
 const root=()=>document.getElementById('fcb-live');
 if(root()?.dataset.preview==='1')return;
 const keys={used:'fcb_followup_used',accepted:'fcb_followup_accepted',pending:'fcb-followup-pending'};
 const memory={used:false,accepted:false};let pending=null,timer=null,panel=null,lastFocus=null;
 const storage={get:key=>{try{return sessionStorage.getItem(key)}catch(error){return null}},set:(key,value)=>{try{sessionStorage.setItem(key,value)}catch(error){}},remove:key=>{try{sessionStorage.removeItem(key)}catch(error){}}};
 const flagged=kind=>{
  if(memory[kind]||storage.get(keys[kind])==='1')return true;
  try{return document.cookie.split(';').some(value=>value.trim()===keys[kind]+'=1')}catch(error){return false}
 };
 const flag=kind=>{
  memory[kind]=true;storage.set(keys[kind],'1');
  // Session cookies share only a one-bit functional limit across same-site tabs.
  try{document.cookie=keys[kind]+'=1; Path=/; SameSite=Lax'+(location.protocol==='https:'?'; Secure':'')}catch(error){}
 };
 const stop=()=>{if(timer!==null)clearTimeout(timer);timer=null};
 const clearPending=()=>{stop();pending=null;storage.remove(keys.pending)};
 const suppress=()=>flagged('used')||flagged('accepted');
 const current=()=>{try{return JSON.parse(root()?.dataset.config||'null')||c}catch(error){return c}};
 const mobile=config=>matchMedia('(max-width:'+(Number(config.mobile_bar?.breakpoint)||767)+'px)').matches;
 const allowedDevice=config=>mobile(config)?config.mobile!==false:config.desktop!==false;
 const actions=()=>{
  const config=current(),isMobile=mobile(config),states=window.FCBWidget?.availability?.(config,new Date(),isMobile);
  if(!allowedDevice(config))return {callback:false,whatsapp:null};
  const whatsapp=config.channels?.whatsapp;
  return {
   callback:!!config.callback&&!!root()?.fcb?.open&&states?.callback?.open===true,
   whatsapp:whatsapp?.enabled&&whatsapp.value&&states?.whatsapp?.open===true&&whatsapp.appearance?.[isMobile?'mobile':'desktop']!==false?window.FCBWidget?.destination?.('whatsapp',whatsapp.value):null,
  };
 };
 const position=()=>{
  if(!panel||panel.hidden)return;
  const element=root(),box=element&&!element.hidden?element.getBoundingClientRect():null,pad=12;
  panel.style.maxHeight=Math.max(80,innerHeight-pad*2)+'px';
  panel.style.width=Math.min(340,innerWidth-pad*2)+'px';
  const width=panel.getBoundingClientRect().width;
  const left=box?(element.classList.contains('fcb-left')?box.left:box.right-width):innerWidth-width-pad;
  panel.style.left=Math.max(pad,Math.min(innerWidth-width-pad,left))+'px';
  const height=panel.getBoundingClientRect().height;
  if(box&&element.classList.contains('fcb-layout-bar')&&element.dataset.barPosition==='top'){
   panel.style.bottom='auto';panel.style.top=Math.max(pad,Math.min(innerHeight-height-pad,box.bottom+pad))+'px';
  }else{
   panel.style.top='auto';panel.style.bottom='max('+Math.max(pad,Math.min(innerHeight-height-pad,box?innerHeight-box.top+pad:24))+'px, env(safe-area-inset-bottom, 0px))';
  }
 };
 const hide=()=>{
  if(!panel)return;
  const focused=panel.contains(document.activeElement);panel.hidden=true;
  if(focused){const target=lastFocus?.isConnected&&lastFocus.getClientRects().length?lastFocus:root()?.querySelector('[data-fcb="toggle"],a[href^="tel:"]');if(target&&!target.closest('[inert]'))target.focus({preventScroll:true});}
 };
 const create=(tag,text,attributes={})=>{const node=document.createElement(tag);if(text)node.textContent=text;for(const[key,value]of Object.entries(attributes))node.setAttribute(key,value);return node};
 const mount=available=>{
  panel=create('section',null,{class:'fcb-followup',role:'dialog','aria-modal':'false','aria-labelledby':'fcb-followup-title','aria-live':'polite'});
  const close=create('button','×',{type:'button',class:'fcb-followup-close','aria-label':'Close follow-up'});
  const title=create('h2',typeof followup.title==='string'&&followup.title?followup.title:'Were you able to reach us?',{id:'fcb-followup-title'});
  const text=create('p',typeof followup.text==='string'&&followup.text?followup.text:"If not, leave your contact details and we'll get back to you as soon as possible.");
  const group=create('div',null,{class:'fcb-followup-actions'});panel.append(close,title,text,group);
  close.addEventListener('click',hide);panel.addEventListener('keydown',event=>{if(event.key==='Escape'){event.preventDefault();event.stopPropagation();hide()}});
  if(available.callback){
   const callback=create('button','Request a Callback',{type:'button',class:'fcb-followup-callback'});group.append(callback);
   callback.addEventListener('click',()=>{if(flagged('accepted')){hide();return}if(!actions().callback){callback.disabled=true;return}hide();root()?.fcb?.open('callback',false,{source:'recovery',channel:'call'})});
  }
  if(available.whatsapp){
   const whatsapp=create('a','Message on WhatsApp',{href:available.whatsapp,target:'_blank',rel:'noopener noreferrer'});group.append(whatsapp);
   whatsapp.addEventListener('click',event=>{if(flagged('accepted')||!actions().whatsapp){event.preventDefault();hide()}});
  }
  lastFocus=document.activeElement;document.body.append(panel);position();
 };
 const tryShow=()=>{
  stop();if(!pending)return;
  if(suppress()){clearPending();hide();return}
  if(document.hidden||!document.hasFocus())return;
  if(!allowedDevice(current()))return;
  const form=root()?.querySelector('.fcb-card'),editing=document.activeElement?.matches('input,textarea,select,[contenteditable="true"]');
  if((form&&!form.hidden)||editing){timer=setTimeout(tryShow,1000);return}
  const available=actions();if(!available.callback&&!available.whatsapp){timer=setTimeout(tryShow,30000);return}
  flag('used');clearPending();mount(available);
 };
 const arm=()=>{stop();if(suppress()){clearPending();hide();return}if(pending)timer=setTimeout(tryShow,Math.max(0,pending.at-Date.now()))};
 const phoneNumber=value=>{let number=String(value||'').replace(/^tel:/i,'');try{number=decodeURIComponent(number)}catch(error){}return number.split(/[;?,#]/,1)[0].replace(/\s*(?:ext\.?|x)\s*\d+\s*$/i,'').replace(/\D/g,'')};
 const numbers=new Set((Array.isArray(c.recovery.numbers)?c.recovery.numbers:[]).map(phoneNumber).filter(Boolean));
 const allows=number=>number&&(c.recovery.number_mode==='include'?numbers.has(number):c.recovery.number_mode==='exclude'?!numbers.has(number):true);
 document.addEventListener('click',event=>{
  const target=event.target instanceof Element?event.target:event.target?.parentElement,link=target?.closest('a[href]');
  if(!link||event.button>0||link.closest('[data-preview="1"]')||!/^tel:/i.test(link.getAttribute('href')||''))return;
  if(!allows(phoneNumber(link.getAttribute('href')))||!allowedDevice(current())||suppress()||pending)return;
  const delay=Number(followup.delay);pending={at:Date.now()+Math.max(2,Math.min(120,Number.isFinite(delay)?delay:10))*1000,source:link.closest('.fcb-widget')?'widget':'sitewide'};
  storage.set(keys.pending,JSON.stringify(pending));arm();
  // Observe only: the original telephone action is never prevented or delayed.
 },true);
 document.addEventListener('fcb:lead-accepted',()=>{flag('accepted');clearPending();hide()});
 document.addEventListener('visibilitychange',()=>{if(document.hidden){stop();hide()}else arm()});
 window.addEventListener('focus',arm);window.addEventListener('pageshow',arm);window.addEventListener('pagehide',()=>{stop();hide()});
 window.addEventListener('resize',()=>{if(!allowedDevice(current()))hide();else position()});window.addEventListener('scroll',position,{passive:true});
 try{const saved=JSON.parse(storage.get(keys.pending)||'null');if(saved&&Number.isFinite(saved.at)&&['widget','sitewide'].includes(saved.source))pending={at:saved.at,source:saved.source}}catch(error){}
 arm();
})();
