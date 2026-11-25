(()=>{'use strict';
const icons=window.FCBAssets?.icons||{},siteZone=window.FCBAssets?.timezone||'UTC';
const destination=(key,value)=>{const v=String(value||'').trim(),phone=v.replace(/[^0-9+]/g,'');if(key==='call'||key==='sms')return(key==='call'?'tel:':'sms:')+phone;if(key==='whatsapp')return'https://wa.me/'+phone.replace(/^\+/,'');if(key==='email')return'mailto:'+v;if(key==='telegram')return'https://t.me/'+v.replace(/^@/,'');if(key==='messenger')return'https://m.me/'+v;return /^https:\/\//i.test(v)?v:'#'};
const wall=(date,zone)=>{
 zone=zone==='site'?siteZone:zone;const offset=/^([+-])(\d{2}):(\d{2})$/.exec(zone);
 if(offset){const d=new Date(date.getTime()+(offset[1]==='-'?-1:1)*(+offset[2]*60+ +offset[3])*60000);return{day:d.getUTCDay()||7,minute:d.getUTCHours()*60+d.getUTCMinutes()}}
 try{const p=Object.fromEntries(new Intl.DateTimeFormat('en-GB',{timeZone:zone,weekday:'short',hour:'2-digit',minute:'2-digit',hourCycle:'h23'}).formatToParts(date).map(x=>[x.type,x.value]));return{day:{Mon:1,Tue:2,Wed:3,Thu:4,Fri:5,Sat:6,Sun:7}[p.weekday],minute:(+p.hour%24)*60+ +p.minute}}catch(e){return null}
};
const minutes=v=>/^\d\d:\d\d$/.test(String(v))?+v.slice(0,2)*60+ +v.slice(3):NaN;
function scheduleState(ch,date=new Date()){
 if(!ch?.enabled)return{open:false,action:'hide',message:'',alternative:''};
 const s=ch.schedule||{};if(!s.enabled)return{open:true,action:'direct',message:'',alternative:''};
 const t=wall(date,s.timezone||'site');let open=false;
 if(t)for(const range of s.intervals||[]){
  const a=minutes(range.open),b=minutes(range.close),days=(range.days||[]).map(Number),prev=(t.day+5)%7+1;
  if(a<b&&days.includes(t.day)&&t.minute>=a&&t.minute<b)open=true;
  if(a>b&&((days.includes(t.day)&&t.minute>=a)||(days.includes(prev)&&t.minute<b)))open=true;
 }
 return{open,action:open?'direct':s.closed||'hide',message:s.message||'This contact method is currently unavailable.',alternative:s.alternative||''};
}
function availability(c,date=new Date(),mobile=null){
 const states=Object.fromEntries(Object.entries(c.channels||{}).map(([key,ch])=>[key,scheduleState(ch,date)]));
 states.callback=scheduleState({enabled:c.callback,schedule:c.callback_schedule},date);
 for(const [key,state]of Object.entries(states)){
  if(state.action==='alternative'&&state.alternative==='callback')state.action='callback';
  if(state.action==='callback'&&(key==='callback'||!states.callback.open))state.action='unavailable';
  if(state.action==='alternative'&&(state.alternative===key||!states[state.alternative]?.open||(mobile!==null&&c.channels?.[state.alternative]?.appearance?.[mobile?'mobile':'desktop']===false)))state.action='unavailable';
 }return states;
}
const shadow=v=>v==='none'?'none':v==='strong'?'0 8px 28px #142e3440':'0 5px 20px #142e3426';
const init=root=>{
 if(root.fcb)return;
 let c=JSON.parse(root.dataset.config||'{}'),lastFocus=null,pending=false,away=false,dismissed=false,presentationKey=null,menuAnimation=null,formAnimation=null,bubbleAnimation=null,transitionVersion=0,transitioning=false,desiredOpen=false;
 const preview=root.dataset.preview==='1',recoveryOnly=root.dataset.recoveryOnly==='1',card=root.querySelector('.fcb-card'),toggle=root.querySelector('[data-fcb=toggle]'),choices=root.querySelector('.fcb-choices');
 try{dismissed=sessionStorage.getItem('fcb-dismissed')==='1'}catch(e){}
 const activeView=()=>{const mobile=preview?!!root.closest('.fcb-preview-stage')?.classList.contains('is-mobile'):matchMedia('(max-width:'+Math.max(320,Math.min(1200,c.mobile_bar?.breakpoint||767))+'px)').matches;const fallback={x:c.offset??24,y:c.offset??24,position:c.position||'right',size:c.size||56,icon_size:24,gap:c.gap||12,layout:c.layout||'expandable',direction:'vertical',expanded:false};const result={mobile,...fallback,...(mobile?c.mobile_view:c.desktop_view)};if(recoveryOnly)result.layout='separate';else if(result.grouped&&result.layout==='separate')result.layout='expandable';return result};
 const duration=()=>c.animations?.enabled===false||matchMedia('(prefers-reduced-motion:reduce)').matches?0:Math.max(180,Math.min(240,Number(c.animations?.duration)||200));
 const motion=(element,enter,delay=0)=>{
  const ms=duration();if(!element||!ms||!element.animate)return null;
  const base=getComputedStyle(element).transform,rest=base==='none'?'':base,moved=(rest+' translateY(8px)').trim();
  return element.animate(enter?[{opacity:0,transform:moved},{opacity:1,transform:rest||'none'}]:[{opacity:1,transform:rest||'none'},{opacity:0,transform:moved}],{duration:ms,delay,easing:'cubic-bezier(.2,.7,.2,1)',fill:delay?'backwards':'none'});
 };
 const menuAccess=()=>{
  const visible=choices.dataset.hasOptions!=='0'&&(activeView().layout==='separate'||root.classList.contains('is-open')||root.classList.contains('fcb-recovery-ready'));
  const opened=desiredOpen,bar=activeView().layout==='bar',stateLabel=opened?'Close contact options':'Open contact options';
  const custom=c.launcher_style?.aria_label,description=custom&&!['Contact options','Open contact options','Close contact options'].includes(custom)?': '+custom:'';
  choices.inert=!visible||!desiredOpen&&activeView().layout!=='separate';choices.setAttribute('aria-hidden',String(choices.inert));toggle.setAttribute('aria-expanded',String(opened));toggle.setAttribute('aria-label',stateLabel+description);toggle.title=c.tooltips===false?'':stateLabel+description;
  toggle.querySelector(':scope > span:last-child').textContent=opened?'Close':bar?'More':c.launcher;
  const icon=toggle.querySelector('.fcb-launcher-icon'),closed=c.launcher_style?.icon||'call';root.dataset.launcherIcon=closed;root.style.setProperty('--fcb-ui-duration',duration()+'ms');
  if(icon){
   if(!icon.querySelector('.fcb-launcher-closed')){icon.innerHTML='<span class="fcb-launcher-closed"></span><span class="fcb-launcher-open"></span>';icon.setAttribute('aria-hidden','true');}
   const saved=icon.querySelector('.fcb-launcher-closed'),closeIcon=icon.querySelector('.fcb-launcher-open');
   if(saved.dataset.icon!==closed){saved.innerHTML=closed==='none'?'':icons[closed]||icons.call||'';saved.dataset.icon=closed;}
   if(!closeIcon.innerHTML)closeIcon.innerHTML=icons.close||'';
  }
  const intro=root.querySelector('.fcb-intro'),behavior=c.intro_behavior||'hide_on_open',hideBubble=behavior==='hide_on_open'&&opened;
  intro.hidden=!c.intro||c.intro_enabled===false||behavior==='never'||recoveryOnly||bar;if(!transitioning)intro.classList.toggle('fcb-intro-hidden',hideBubble);intro.setAttribute('aria-hidden',String(intro.hidden||hideBubble));
  const group=choices.querySelector('.fcb-choice-links');root.classList.toggle('fcb-links-scroll',group.scrollHeight>group.clientHeight+1);
 };
 const finished=animation=>animation?animation.finished.then(()=>true,()=>false):Promise.resolve(true);
 const expanded=async(value,animate=true)=>{
  const version=++transitionVersion,intro=root.querySelector('.fcb-intro'),hasMenu=root.classList.contains('is-open')||root.classList.contains('is-menu-closing');
  desiredOpen=value;transitioning=true;menuAnimation?.cancel();bubbleAnimation?.cancel();
  if(!value&&choices.contains(document.activeElement))toggle.focus({preventScroll:true});menuAccess();
  const animated=animate&&activeView().layout!=='separate'&&duration()>0,hideFirst=!intro.hidden&&(c.intro_behavior||'hide_on_open')==='hide_on_open';
  if(value){
   if(hideFirst&&!intro.classList.contains('fcb-intro-hidden')&&!hasMenu){
    bubbleAnimation=animated?motion(intro,false):null;await finished(bubbleAnimation);if(version!==transitionVersion)return;
   }
   if(hideFirst)intro.classList.add('fcb-intro-hidden');
   root.classList.remove('is-menu-closing');root.classList.add('is-open');menuAccess();
   menuAnimation=animated?motion(choices,true):null;await finished(menuAnimation);if(version!==transitionVersion)return;
   window.FCBButtonEffects?.sync(root,c);
  }else{
   root.classList.remove('is-open');if(hasMenu){root.classList.add('is-menu-closing');menuAccess();menuAnimation=animated?motion(choices,false):null;await finished(menuAnimation);if(version!==transitionVersion)return;}
   root.classList.remove('is-menu-closing');window.FCBButtonEffects?.sync(root,c);
   if(hideFirst){intro.classList.remove('fcb-intro-hidden');bubbleAnimation=animated?motion(intro,true):null;await finished(bubbleAnimation);if(version!==transitionVersion)return;}
  }
  transitioning=false;menuAccess();
 };
 const close=()=>{
  if(card&&!card.hidden){formAnimation?.cancel();card.inert=true;card.setAttribute('aria-hidden','true');formAnimation=motion(card,false);if(formAnimation)formAnimation.onfinish=()=>{card.hidden=true};else card.hidden=true;}
  dismissed=true;pending=false;root.classList.remove('is-recovery-active');try{if(!preview)sessionStorage.setItem('fcb-dismissed','1')}catch(e){}
  const target=lastFocus?.isConnected&&lastFocus.getClientRects().length&&!lastFocus.closest('[inert]')?lastFocus:toggle;if(!target.hidden)target.focus({preventScroll:true});
 };
 const recover=()=>{if(!c.callback||availability(c).callback.action==='hide')return;root.classList.add('fcb-recovery-ready');root.dataset.source='recovery';root.dataset.contactSource='call';sync(c)};
 const open=(kind=c.form_kind||'callback',automatic=false,context={})=>{
  if(!card||!c.callback||(automatic&&dismissed))return;
  const callback=availability(c,new Date(),activeView().mobile).callback;if(!callback.open){if(!automatic){const note=root.querySelector('.fcb-channel-notice');note.textContent=callback.message;note.hidden=false;}return;}
  const wasHidden=card.hidden||card.inert;formAnimation?.cancel();
  if(context.source)root.dataset.source=context.source;
  const source=root.dataset.source==='recovery'?'recovery':'widget';
  if(recoveryOnly)root.classList.add('fcb-recovery-ready');
  const view=activeView();if((view.mobile&&!c.mobile)||(!view.mobile&&!c.desktop))return;
  root.hidden=false;if(wasHidden)lastFocus=document.activeElement;card.hidden=false;card.inert=false;card.setAttribute('aria-hidden','false');root.dataset.kind=kind;root.dataset.contactSource=context.channel||root.dataset.contactSource||'callback';
  const recovery=source==='recovery'&&c.recovery;
  card.querySelector('h2').textContent=context.title||(recovery?c.recovery.title:c.fallback_title);
  card.querySelector('.fcb-card-copy').textContent=context.text||(recovery?c.recovery.text:c.fallback_text);
  if(wasHidden)formAnimation=motion(card,true);if(!automatic)card.querySelector('[name=contact]').focus();
  if(!preview&&wasHidden)window.FCBEvents?.open(source,kind);
  if(source==='recovery')root.classList.add('is-recovery-active');
  pending=false;
 };
 const styleObject=(prefix,object={})=>{
  const lengths=['radius','border_width','padding_x','padding_y','padding','gap','margin','width','height','font_size'];
  for(const [key,value]of Object.entries(object)){if(lengths.includes(key))root.style.setProperty('--fcb-'+prefix+'-'+key.replaceAll('_','-'),Number(value)+'px');else if(['background','text','icon_color','border_color','hover_color','focus_color','button_background','button_text'].includes(key))root.style.setProperty('--fcb-'+prefix+'-'+key.replaceAll('_','-'),value)}
 };
 function sync(next){
  const focused=root.contains(document.activeElement)?document.activeElement:null;c=next;root.dataset.config=JSON.stringify(c);root.dataset.preset=c.preset||'';
  const view=activeView(),states=availability(c,new Date(),view.mobile),runtime=['is-open','is-menu-closing','fcb-recovery-ready','is-recovery-active'].filter(x=>root.classList.contains(x));
  root.className=['fcb-widget','fcb-'+view.position,'fcb-style-'+c.style,'fcb-layout-'+view.layout,view.mobile?'fcb-view-mobile':'fcb-view-desktop',...runtime].join(' ');
  const viewport=preview?root.closest('.fcb-preview-stage').clientWidth:innerWidth;const height=preview?root.closest('.fcb-preview-stage').clientHeight:innerHeight;view.y=Math.min(view.y,Math.max(0,height-200));view.x=Math.min(view.x,Math.max(0,viewport-(c.callback?220:Math.max(84,view.size))-12));const cardWidth=Math.min(350,viewport-24);const cardX=Math.min(view.x,Math.max(12,viewport-cardWidth-12));root.style.setProperty('--fcb-card-shift',Math.max(0,view.x-cardX)+'px');
  root.dataset.position=view.position;root.dataset.direction=view.direction;root.dataset.desktopPosition=c.desktop_view?.position||c.position;root.dataset.mobilePosition=c.mobile_view?.position||c.position;
  const nextPresentation=[view.mobile,view.layout,view.expanded,c.preset].join(':');if(nextPresentation!==presentationKey){++transitionVersion;menuAnimation?.cancel();bubbleAnimation?.cancel();transitioning=false;desiredOpen=!!view.expanded;root.classList.toggle('is-open',desiredOpen);root.classList.remove('is-menu-closing');presentationKey=nextPresentation}
  for(const [key,value]of Object.entries({size:view.size,gap:view.gap,x:view.x,y:view.y,'icon-size':view.icon_size}))root.style.setProperty('--fcb-'+key,Number(value)+'px');
  styleObject('intro',c.intro_style);styleObject('cb',c.callback_style);styleObject('launcher',c.launcher_style);styleObject('form',c.form_style);styleObject('panel',c.panel_style);root.dataset.panel=c.panel_style?.enabled?'1':'0';root.style.setProperty('--fcb-panel-shadow',shadow(c.panel_style?.shadow));
  const cb=c.callback_style||{},launcher=c.launcher_style||{};
  root.dataset.callbackPlacement=cb.placement||'below';root.classList.toggle('fcb-callback-edge',!!cb.edge);
  const intro=root.querySelector('.fcb-intro');intro.textContent=c.intro||'';intro.hidden=!c.intro||c.intro_enabled===false||recoveryOnly;
  root.style.setProperty('--fcb-launcher-shadow',shadow(launcher.shadow));root.style.setProperty('--fcb-launcher-effective-width',launcher.width?launcher.width+'px':(launcher.icon==='none'||!['circle','square'].includes(c.style)?'auto':view.size+'px'));root.style.setProperty('--fcb-launcher-effective-height',(launcher.height||view.size)+'px');root.dataset.launcherText=launcher.icon==='none'?'show':'auto';
  const callback=root.querySelector('.fcb-callback-link');const cbState=states.callback;const oldCallbackAction=callback.dataset.action;callback.hidden=!c.callback||cbState.action==='hide';callback.dataset.action=cbState.action;callback.dataset.actualChannel=cbState.alternative||'';callback.dataset.message=cbState.message||'';callback.setAttribute('aria-disabled',String(cbState.action==='unavailable'));callback.classList.toggle('fcb-unavailable',cbState.action==='unavailable');callback.querySelector('.fcb-callback-label').textContent=cbState.action==='alternative'?c.channels[cbState.alternative].label:c.submit_label;
  if(root.dataset.ready==='1'&&oldCallbackAction!==cbState.action&&!callback.hidden)motion(callback,true);callback.querySelector('.fcb-callback-icon').innerHTML=icons[cbState.action==='alternative'?cbState.alternative:cb.icon||'call']||'';
  let visible=0;
  const holder=root.querySelector('.fcb-choice-links');
  for(const [key,ch]of Object.entries(c.channels||{})){
   const link=root.querySelector('.fcb-channel[data-channel="'+key+'"]');if(!link)continue;
   const state=states[key],appearance=ch.appearance||{},target=state.action==='alternative'?state.alternative:key,targetCh=c.channels[target]||ch;
   const device=(view.mobile?appearance.mobile:appearance.desktop)!==false;
   const oldAction=link.dataset.action;link.hidden=!ch.enabled||state.action==='hide'||!device;link.dataset.action=state.action;if(root.dataset.ready==='1'&&oldAction!==state.action&&!link.hidden)motion(link,true);link.dataset.actualChannel=target;link.dataset.message=state.message||'';
   if(!link.hidden)visible++;
   link.href=['callback','unavailable'].includes(state.action)?'#':destination(target,targetCh.value);
   const label=state.action==='callback'?c.submit_label:(state.action==='alternative'?targetCh.label:ch.label);
   link.querySelector('span').textContent=label;
   const icon=appearance.icon&&appearance.icon!=='default'?appearance.icon:target;
   const svg=link.querySelector('svg');if(svg&&icons[icon])svg.outerHTML=icons[icon];
   link.style.setProperty('--fcb-color',targetCh.color);link.style.setProperty('--fcb-icon',targetCh.icon_color);
   link.classList.toggle('fcb-unavailable',state.action==='unavailable');link.setAttribute('aria-disabled',String(state.action==='unavailable'));
   const title=[label,state.open?'':state.message].filter(Boolean).join(' — ');link.title=c.tooltips===false?'':title;link.setAttribute('aria-label',title);
   const content=appearance.content_mode||'inherit',legacyText=!Object.hasOwn(appearance,'content_mode')&&appearance.custom&&appearance.text_mode&&appearance.text_mode!=='auto'?appearance.text_mode:null,text=content==='text'||content==='icon_text'?'show':content==='icon'?'hide':legacyText||(['circle','square'].includes(c.style)?'hide':'show');link.dataset.contentMode=content;link.dataset.textMode=text;link.dataset.custom=appearance.custom?'1':'0';
   if(appearance.custom){
    const width=view.mobile?appearance.mobile_width||appearance.width:appearance.width,height=view.mobile?appearance.mobile_height||appearance.height:appearance.height;
    const values={width:width?width+'px':text==='show'?'auto':view.size+'px',height:(height||view.size)+'px','icon-size':(view.mobile?appearance.mobile_icon_size:appearance.icon_size)+'px','padding-x':appearance.padding_x+'px','padding-y':appearance.padding_y+'px',radius:appearance.radius+'px','border-width':appearance.border_width+'px','border-color':appearance.border_color,shadow:shadow(appearance.shadow),'hover-color':appearance.hover_color||targetCh.color,'focus-color':appearance.focus_color};
    for(const [k,v]of Object.entries(values))link.style.setProperty('--fcb-ch-'+k,v);
   }
   holder.append(link);
  }
  if(card){
   if(card.hidden){card.querySelector('h2').textContent=c.fallback_title;card.querySelector('.fcb-card-copy').textContent=c.fallback_text}
   card.querySelector('.fcb-submit').textContent=c.submit_label;card.querySelector('.fcb-optional-name').hidden=!c.name_field;card.querySelector('.fcb-optional-message').hidden=!c.message_field;card.querySelector('.fcb-consent').hidden=!c.consent;
   const privacy=card.querySelector('.fcb-privacy'),a=privacy.querySelector('a');privacy.firstChild.textContent=c.privacy_text+' ';a.href=c.privacy_url||'#';a.hidden=!c.privacy_url;
   const alt=card.querySelector('.fcb-alternatives');alt.replaceChildren();holder.querySelectorAll('.fcb-channel:not([hidden])').forEach(link=>{if(link.dataset.action!=='direct')return;const copy=link.cloneNode(true);copy.className='fcb-alternative';copy.removeAttribute('style');copy.querySelector('svg')?.remove();alt.append(copy)});
   const submit=card.querySelector('[type=submit]');submit.disabled=!cbState.open||card.querySelector('form').dataset.sending==='1';if(!cbState.open&&!card.hidden&&!card.querySelector('.fcb-feedback').textContent)card.querySelector('.fcb-feedback').textContent=cbState.message;
   if(!c.callback){formAnimation?.cancel();card.hidden=true;}if(card.hidden){card.inert=true;card.setAttribute('aria-hidden','true');}
  }
  const deviceAllowed=view.mobile?c.mobile:c.desktop;
  root.hidden=!deviceAllowed||(!visible&&callback.hidden)||(recoveryOnly&&!root.classList.contains('fcb-recovery-ready'));
  window.FCBLayout?.sync(root,c,view);
  if(choices.dataset.hasOptions==='0'){++transitionVersion;menuAnimation?.cancel();bubbleAnimation?.cancel();transitioning=false;desiredOpen=false;root.classList.remove('is-open','is-menu-closing');}
  menuAccess();const introRoom=intro.hidden||intro.classList.contains('fcb-intro-hidden')?0:intro.offsetHeight+view.gap;root.style.setProperty('--fcb-menu-room',Math.max(64,height-view.y-introRoom-(view.layout==='expandable'?(launcher.height||view.size)+view.gap:0)-24)+'px');if(focused?.isConnected&&document.activeElement!==focused){const next=focused.getClientRects().length&&!focused.closest('[inert]')?focused:toggle;if(!next.hidden)next.focus({preventScroll:true})}root.dataset.ready='1';window.FCBButtonEffects?.sync(root,c);
 }
 root.addEventListener('click',event=>{
  const control=event.target.closest('[data-fcb]'),link=event.target.closest('a[data-channel]');
  if(control){
   if(control.dataset.fcb==='toggle')expanded(!desiredOpen);
   if(control.dataset.fcb==='callback'){const action=control.dataset.action;if(action==='alternative'){const key=control.dataset.actualChannel,url=destination(key,c.channels[key].value);if(!preview){window.FCBEvents?.contact(key,'widget');if(['call','sms','email'].includes(key))window.location.href=url;else window.open(url,'_blank','noopener,noreferrer')}return;}if(recoveryOnly)root.dataset.source='recovery';else if(root.dataset.source!=='recovery')root.dataset.source='widget';open()}
   if(control.dataset.fcb==='close')close();
  }
  if(link){
   const action=link.dataset.action||'direct',channel=link.dataset.actualChannel||link.dataset.channel;
   if(preview){event.preventDefault();if(c.callback)open();return}
   window.FCBEvents?.contact(channel,'widget');
   if(action==='unavailable'){event.preventDefault();const note=root.querySelector('.fcb-channel-notice');note.textContent=link.dataset.message;note.hidden=false;return}
   if(action==='callback'){event.preventDefault();root.dataset.source='widget';open('callback',false,{channel:link.dataset.channel,text:link.dataset.message});return}
   root.dataset.contactSource=channel;
   if(c.fallback&&c.callback&&!dismissed&&!c.recovery?.followup?.enabled){pending=true;away=false}
  }
 });
 root.addEventListener('keydown',e=>{if(e.key==='Escape'){if(card&&!card.hidden)close();else{expanded(false);toggle.focus()}}});
 if(!preview){
  document.addEventListener('visibilitychange',()=>{if(pending&&document.hidden)away=true;if(pending&&away&&!document.hidden)open(c.form_kind||'callback',true)});
  window.addEventListener('pageshow',()=>{if(pending&&away)open(c.form_kind||'callback',true)});
  let resizeFrame=0;window.addEventListener('resize',()=>{cancelAnimationFrame(resizeFrame);resizeFrame=requestAnimationFrame(()=>sync(c))},{passive:true});
  setInterval(()=>sync(c),30000);
 }
 root.fcb={sync,open,close,recover};sync(c);
};
window.FCBWidget={init,destination,scheduleState,availability,wall};
document.querySelectorAll('.fcb-widget').forEach(init);
})();
