(()=>{'use strict';
if(!window.FCBAdmin)return;
let state=structuredClone(FCBAdmin.draft),step=0,saving=false,revision=0;
const $=s=>document.querySelector(s),all=s=>[...document.querySelectorAll(s)],layout=$('.fcb-editor-layout'),wizard=layout?.dataset.wizard==='1',preview=$('#fcb-preview');
let interfaceMode=FCBAdmin.interfaceMode==='advanced'?'advanced':'simple',modeSaving=false,simpleFloatingLayout=state.mobile_view?.layout==='bar'?'expandable':state.mobile_view?.layout;
if(wizard){try{step=Math.max(0,Math.min(3,Number(localStorage.getItem('fcb-setup-step:'+location.host)||0)))}catch(e){}}
const read=(path)=>path.split('.').reduce((v,k)=>v?.[k],state);
const write=(path,value)=>{const keys=path.split('.');let obj=state;keys.slice(0,-1).forEach(k=>{if(!obj[k]||typeof obj[k]!=='object')obj[k]={};obj=obj[k]});obj[keys.at(-1)]=value};
const currentView=()=>$('.fcb-preview-stage')?.classList.contains('is-mobile')?'mobile_view':'desktop_view';
function buttonPlan(viewName){
 const view=state[viewName]||{},device=viewName==='mobile_view'?'mobile':'desktop',bar=device==='mobile'&&view.layout==='bar',visible=state[device]!==false;
 const channels=Object.keys(state.channels).filter(key=>state.channels[key].enabled&&state.channels[key].appearance?.[device]!==false),callback=!!state.callback;
 const separate=view.layout==='separate'&&!view.grouped;let direct=[];
 if(separate)direct=[...channels,...(callback?['callback']:[])];
 else if(view.grouped)direct=(view.always||[]).filter(key=>key==='callback'?callback:channels.includes(key));
 else if(bar){direct=['call','whatsapp'].filter(key=>channels.includes(key));if(!direct.length)direct=channels.slice(0,2)}
 let extra=[...channels.filter(key=>!direct.includes(key)),...(callback&&!direct.includes('callback')?['callback']:[])];
 if(bar&&state.mobile_bar?.more_action==='callback'&&extra.length===1&&extra[0]==='callback'){direct.push('callback');extra=[]}
 return {view,device,bar,visible,channels,direct,extra,hasMenu:visible&&!separate&&extra.length>0};
}
function refreshButtonUI(){
 const selected=Object.keys(state.channels).filter(key=>state.channels[key].enabled),plans={desktop_view:buttonPlan('desktop_view'),mobile_view:buttonPlan('mobile_view')};
 all('[data-order-number]').forEach(node=>{const index=selected.indexOf(node.dataset.orderNumber);node.textContent=index<0?'Not enabled':'Button '+(index+1)+' of '+selected.length});
 all('[data-button-location-details]').forEach(node=>node.hidden=selected.length+(state.callback?1:0)<=2);
 all('[data-channel-location]').forEach(node=>{const key=node.dataset.channelLocation,p=plans[node.dataset.view],eligible=key==='callback'?state.callback:p.channels.includes(key);node.textContent=!p.visible?'Hidden on this device':!eligible?'Hidden by button visibility':p.direct.includes(key)?p.bar?'Mobile bar · direct action':p.view.grouped?'Always visible':key==='callback'?'Callback button · '+(state.callback_style?.placement==='above'?'above contacts':'below contacts'):'Individual button':p.bar?'More menu':'Inside contact options'});
 all('[data-callback-location-row]').forEach(node=>node.hidden=!state.callback);
 all('[data-simple-callback-fields]').forEach(node=>node.hidden=!state.callback);
 all('[data-launcher-placement]').forEach(node=>{const p=plans[node.dataset.launcherPlacement];node.hidden=!p.hasMenu||p.bar});
 all('[data-launcher-support]').forEach(node=>{const p=plans[node.dataset.launcherSupport];node.textContent=p.bar?'The mobile bar keeps More in the bar; floating Close placement does not apply.':!p.hasMenu?'No launcher is needed when all available actions are directly visible.':'The launcher only opens additional options. Direct Call buttons dial immediately.'});
 const active=currentView(),plan=plans[active];all('[data-simple-launcher]').forEach(node=>node.hidden=!plan.hasMenu||plan.bar);
 all('[data-simple-launcher-position]').forEach(node=>{const index=Number(plan.view.launcher_index??-1);node.value=index>0?'custom':String(index)});
 all('[data-move-launcher]').forEach(button=>{const name=button.dataset.view||active,p=plans[name],count=p.extra.length,index=Number(p.view.launcher_index??-1),at=index<0?count:Math.min(index,count);button.disabled=!p.hasMenu||p.bar||(Number(button.dataset.moveLauncher)<0?at===0:at===count)});
 for(const[name,p]of Object.entries(plans)){const index=Number(p.view.launcher_index??-1),at=index<0?p.extra.length:Math.min(index,p.extra.length),text='Close control '+(at+1)+' of '+(p.extra.length+1);all('[data-launcher-order]').filter(node=>node.dataset.launcherOrder===name).forEach(node=>node.textContent=text);if(name===active)all('[data-simple-launcher-order]').forEach(node=>node.textContent=text)}
 all('[data-preview-animation]').forEach(button=>{const channel=state.channels[button.dataset.previewAnimation];button.style.backgroundColor=channel.color;button.style.color=channel.icon_color;const icon=channel.appearance?.icon;const markup=window.FCBAssets?.icons?.[icon&&icon!=='default'?icon:button.dataset.previewAnimation],slot=button.querySelector('[data-animation-preview-icon]');if(slot&&markup)slot.innerHTML=markup});
}
const sorted=v=>Array.isArray(v)?v.map(sorted):(v&&typeof v==='object'?Object.fromEntries(Object.keys(v).sort().map(k=>[k,sorted(v[k])])):v);
const fingerprint=c=>JSON.stringify([sorted(c),Object.keys(c.channels||{})]);
let published=fingerprint(FCBAdmin.live),savedDraft=fingerprint(state),messageTimer;
const status=(text,error=false,success=false)=>{
 for(const el of [$('#fcb-save-state'),...all('.fcb-action-feedback')].filter(Boolean)){el.textContent=(success?'✓ ':'')+text;el.classList.toggle('is-error',error);el.classList.toggle('is-success',success);el.classList.add('has-message');el.setAttribute('role',error?'alert':'status')}
};
const updateButtons=()=>{
 const dirty=fingerprint(state)!==published;
 all('[data-admin-action=publish]').forEach(el=>{el.disabled=saving||!dirty;el.classList.toggle('is-dirty',dirty);el.setAttribute('aria-disabled',String(el.disabled))});
 const badge=$('.fcb-draft-badge');if(badge)badge.textContent=dirty?'Unpublished changes':'All changes published';
};
const clearErrors=()=>{all('.fcb-field-error').forEach(el=>el.remove());all('[aria-invalid=true]').forEach(el=>el.removeAttribute('aria-invalid'))};
function applyInterface(mode){
 interfaceMode=mode;const admin=$('.fcb-admin');if(admin)admin.dataset.interfaceMode=mode;
 document.body.classList.toggle('fcb-interface-simple',mode==='simple');document.body.classList.toggle('fcb-interface-advanced',mode==='advanced');
 all('#toplevel_page_fcb .wp-submenu a').forEach(link=>{const slug=new URL(link.href,location.href).searchParams.get('page');if(slug?.startsWith('fcb'))link.closest('li').hidden=mode==='simple'&&!['fcb','fcb-setup','fcb-inbox','fcb-help'].includes(slug)});
 all('[data-interface-toggle]').forEach(button=>{button.dataset.interfaceMode=mode==='simple'?'advanced':'simple';button.textContent=mode==='simple'?'Show Advanced Settings':'Use Simple Settings';button.setAttribute('aria-pressed',String(mode==='advanced'))});
 all('[data-simple-bar-colors]').forEach(el=>el.hidden=state.mobile_view?.layout!=='bar');
 showStep();
 refreshButtonUI();
 if(preview?.fcb)preview.fcb.sync(state);document.dispatchEvent(new Event('fcb:interface'));
}
async function changeInterface(mode){
 if(modeSaving)return;modeSaving=true;all('button[data-interface-mode]').forEach(button=>button.disabled=true);
 try{
  const response=await fetch(FCBAdmin.url,{method:'POST',credentials:'same-origin',body:new URLSearchParams({action:'fcb_interface',nonce:FCBAdmin.nonce,mode})}),result=await response.json();
  if(!result.success)throw Error(result.data?.message||'Unable to change the settings view.');
  const movedFromSimple=document.activeElement?.closest('.fcb-simple-controls');applyInterface(result.data.mode);if(movedFromSimple&&mode==='advanced')$('[data-interface-toggle]')?.focus();status(result.data.message);if(!layout&&FCBAdmin.page==='fcb'&&mode==='simple')location.href=FCBAdmin.setupUrl;
 }catch(error){status(error.message,true)}finally{modeSaving=false;all('button[data-interface-mode]').forEach(button=>button.disabled=false)}
}
const fieldError=(path,message)=>{
 window.FCBFocusedEditor?.openForValidation(path);
 const preferred=nodes=>nodes.find(node=>node.getClientRects().length)||nodes.find(node=>interfaceMode==='simple'?node.closest('.fcb-simple-controls'):!node.closest('.fcb-simple-controls'))||nodes[0];
 let el=preferred(all('[data-path]').filter(e=>e.dataset.path===path))||preferred(all('[data-field]').filter(e=>e.dataset.field===path));
 if(!el&&path?.startsWith('channels.')){const p=path.split('.');el=preferred(all('input[data-channel]').filter(e=>e.dataset.channel===p[1]&&e.dataset.property===p[2]))}
 if(!el&&path?.startsWith('channels.')){const key=path.split('.')[1];el=document.querySelector('[data-schedule="'+key+'"] input[data-path]')||all('[data-path]').find(e=>e.dataset.path.startsWith(path+'.'))}
 if(!el){el=all('[data-path]').find(e=>e.dataset.path.startsWith(path+'.'))}
 if(!el)return;
 if(el.closest('.fcb-advanced-settings')&&interfaceMode==='simple')applyInterface('advanced');
 let detail=el.closest('details');while(detail){detail.open=true;detail=detail.parentElement?.closest('details')}
 if(wizard){const section=el.closest('[data-step]');if(section){step=Number(section.dataset.step);showStep()}}
 el.setAttribute('aria-invalid','true');const note=document.createElement('span');note.className='fcb-field-error';note.textContent=message;note.id='fcb-error-'+path.replace(/[^a-z0-9]/gi,'-');el.setAttribute('aria-describedby',note.id);el.insertAdjacentElement('afterend',note);el.scrollIntoView({block:'center',behavior:'smooth'});el.focus({preventScroll:true});
};
function hydrate(){
 all('[data-field]').forEach(el=>{if(el.type==='checkbox')el.checked=!!state[el.dataset.field];else el.value=state[el.dataset.field]??''});
 all('[data-path]').forEach(el=>{const v=read(el.dataset.path);if(el.type==='checkbox')el.checked=!!v;else if(el.tagName!=='DIV')el.value=Array.isArray(v)?v.join('\n'):(v??'')});
 all('[data-always-channel]').forEach(el=>{el.checked=(read(el.dataset.alwaysView+'.always')||[]).includes(el.dataset.alwaysChannel);el.disabled=!read(el.dataset.alwaysView+'.grouped')});
 all('input[data-channel]').forEach(el=>{const ch=state.channels[el.dataset.channel];if(!ch)return;if(el.type==='checkbox')el.checked=!!ch[el.dataset.property];else el.value=ch[el.dataset.property]});
 all('[data-channel-fields]').forEach(el=>el.hidden=!state.channels[el.dataset.channelFields]?.enabled);
 const holder=$('.fcb-channel-fields');if(holder)Object.keys(state.channels).forEach(k=>{const card=holder.querySelector('[data-channel-fields="'+k+'"]');if(card)holder.append(card)});
 all('[data-order-holder]').forEach(holder=>Object.keys(state.channels).forEach(key=>{const row=holder.querySelector('[data-order-card="'+key+'"]');if(row)holder.append(row)}));
 const styles=$('.fcb-advanced-channel-list');if(styles)Object.keys(state.channels).forEach(key=>{const card=styles.querySelector('[data-style-channel="'+key+'"]')?.closest('details');if(card)styles.append(card)});
 const selected=Object.keys(state.channels).filter(k=>state.channels[k].enabled);
 all('[data-move]').forEach(el=>{const index=selected.indexOf(el.dataset.key);el.disabled=index<0||(Number(el.dataset.move)<0?index===0:index===selected.length-1)});
 all('[data-channel-summary]').forEach(el=>{const ch=state.channels[el.dataset.channelSummary];el.textContent=ch?.value||'Add your contact details'});
 all('.fcb-preset[data-preset]').forEach(el=>el.setAttribute('aria-pressed',String(el.dataset.preset===state.preset)));
 const none=$('.fcb-none-selected');if(none)none.hidden=Object.values(state.channels).some(ch=>ch.enabled);
 const names={email:'Email',sms:'SMS',telegram:'Telegram',messenger:'Messenger',custom:'Custom URL'},extra=Object.keys(state.channels).filter(k=>state.channels[k].enabled&&!['call','whatsapp'].includes(k));
 all('[data-simple-extra-summary]').forEach(el=>{el.hidden=!extra.length;el.querySelector('p').textContent='Also enabled: '+extra.map(k=>names[k]||k).join(', ')+'. These contact methods and your advanced settings remain active.'});
 all('[data-simple-position]').forEach(el=>el.value=state.mobile_view?.layout==='bar'?'bar':state.desktop_view?.position===state.mobile_view?.position&&['left','right'].includes(state.desktop_view.position)?state.desktop_view.position:'custom');
 all('[data-simple-bar-colors]').forEach(el=>el.hidden=state.mobile_view?.layout!=='bar');
 refreshButtonUI();
 if(preview){window.FCBWidget.init(preview);preview.fcb.sync(state)}
 showStep();updateButtons();document.dispatchEvent(new CustomEvent('fcb:hydrate'));
}
function showStep(){
 if(!wizard)return;
 all('[data-step]').forEach(el=>el.hidden=Number(el.dataset.step)!==step);
 all('[data-step-indicator]').forEach(el=>{const n=Number(el.dataset.stepIndicator);el.classList.toggle('is-current',n===step);el.classList.toggle('is-done',n<step);if(n===step)el.setAttribute('aria-current','step');else el.removeAttribute('aria-current')});
 $('#fcb-back').disabled=step===0;$('#fcb-back').hidden=interfaceMode==='simple';$('#fcb-next').hidden=interfaceMode==='simple'||step===3;$('[data-admin-action=publish]').hidden=interfaceMode!=='simple'&&step!==3;
 try{localStorage.setItem('fcb-setup-step:'+location.host,String(step))}catch(e){}
}
function checkDestinations(){
 for(const [key,ch] of Object.entries(state.channels)){
  if(!ch.enabled)continue;const v=ch.value.trim(),digits=v.replace(/[^0-9]/g,'');
  let ok=!!v;
  if(['call','sms','whatsapp'].includes(key))ok=/^\+?[0-9() .-]{7,30}$/.test(v)&&digits.length>=7&&digits.length<=15;
  if(key==='email')ok=/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
  if(key==='telegram')ok=/^@?[a-zA-Z][a-zA-Z0-9_]{4,31}$/.test(v);
  if(key==='messenger')ok=/^[a-zA-Z0-9.]{3,100}$/.test(v);
  if(key==='custom'){try{const u=new URL(v);ok=u.protocol==='https:'&&!u.username&&!u.password}catch(e){ok=false}}
  if(!ok){status(FCBAdmin.strings.destinations+' ('+key+')',true);fieldError('channels.'+key+'.value',FCBAdmin.strings.destinations);return false}
 }return true;
}
function preset(key,preserveMobileBar=false){
 const keepBar=preserveMobileBar&&state.mobile_view?.layout==='bar';const patch=FCBAdmin.presets?.[key];if(!patch)return;revision++;
 for(const field of ['preset','layout','style','size','gap'])if(patch[field]!==undefined)state[field]=structuredClone(patch[field]);
 for(const view of ['desktop_view','mobile_view'])for(const field of ['layout','direction','expanded','size','gap'])if(patch[view]?.[field]!==undefined)state[view][field]=structuredClone(patch[view][field]);
 if(keepBar){simpleFloatingLayout=patch.mobile_view?.layout||simpleFloatingLayout;state.mobile_view.layout='bar'}
 if(state.panel_style&&patch.panel_style?.enabled!==undefined)state.panel_style.enabled=patch.panel_style.enabled;
 hydrate();status('Design preset applied to your selected buttons. Contact details are unchanged. Publish to update your site.');
}
// Resets are local appearance edits. Channel selection, details, order, schedules,
// device visibility and callback/data settings are deliberately outside this list.
function resetSection(section,key){
 const defaults=FCBAdmin.defaults,copy=(target,fields)=>{for(const field of fields)if(defaults[target]?.[field]!==undefined)state[target][field]=structuredClone(defaults[target][field])};
 const contact=k=>{const ch=state.channels[k],d=defaults.channels[k];if(!ch||!d)return;for(const field of ['color','icon_color'])ch[field]=d[field];const visibility={desktop:ch.appearance.desktop,mobile:ch.appearance.mobile};ch.appearance={...structuredClone(d.appearance),...visibility}};
 if(section==='contact'){if(key)contact(key);else Object.keys(state.channels).forEach(contact)}
 if(['launcher_style','callback_style','form_style','panel_style','animations'].includes(section)&&defaults[section])state[section]=structuredClone(defaults[section]);
 if(section==='mobile_bar')copy('mobile_bar',['background','button_background','icon_color','text','border_color','border_width','padding','height']);
 if(section==='bar_palette'){const palette=FCBAdmin.presets?.[state.preset]?.mobile_bar||{};for(const field of ['background','button_background','icon_color','text'])state.mobile_bar[field]=palette[field]??defaults.mobile_bar[field]}
 if(section==='launcher_style'){state.launcher=defaults.launcher;state.intro=defaults.intro;state.intro_enabled=defaults.intro_enabled;state.intro_behavior=defaults.intro_behavior}
 if(section==='positioning'){for(const field of ['position','offset'])state[field]=defaults[field];for(const view of ['desktop_view','mobile_view'])copy(view,['x','y','position'])}
 if(section==='design'){
  for(const field of ['preset','layout','style','size','gap','launcher','intro','intro_enabled','intro_behavior','tooltips'])state[field]=structuredClone(defaults[field]);
  for(const group of ['launcher_style','callback_style','form_style','panel_style','animations'])if(defaults[group])state[group]=structuredClone(defaults[group]);
  if(defaults.mobile_bar)copy('mobile_bar',['background','button_background','icon_color','text','border_color','border_width','padding','height']);
  for(const view of ['desktop_view','mobile_view'])copy(view,['layout','direction','expanded','size','icon_size','gap']);
  Object.keys(state.channels).forEach(contact);
 }
 revision++;hydrate();clearErrors();status((section==='design'?'Design defaults restored.':section==='bar_palette'?'Preset colors restored.':'Section defaults restored.')+' Unpublished draft changes; contact details and enquiries are preserved.');
}
document.addEventListener('input',e=>{
 const special=e.target;
 if(special.matches('[data-simple-launcher-position]')){write(currentView()+'.launcher_index',Number(special.value));revision++;hydrate();status('Launcher / Close position updated in your draft. Publish to update your site.');return}
 if(special.matches('[data-simple-position]')){
  if(special.value==='bar'){if(state.mobile_view.layout!=='bar')simpleFloatingLayout=state.mobile_view.layout;state.mobile_view.layout='bar';revision++;hydrate();status('Mobile bar selected in your draft. Your design and desktop layout are unchanged. Publish to update your site.')}
  else if(['left','right'].includes(special.value)){state.position=special.value;state.desktop_view.position=special.value;state.mobile_view.position=special.value;if(state.mobile_view.layout==='bar')state.mobile_view.layout=simpleFloatingLayout||'expandable';revision++;hydrate();status('Position updated in your draft. Publish to update your site.')}
  return;
 }
 if(special.matches('[data-always-channel]')){const path=special.dataset.alwaysView+'.always',selected=all('[data-always-channel]').filter(input=>input.dataset.alwaysView===special.dataset.alwaysView&&input.checked).map(input=>input.dataset.alwaysChannel);write(path,selected);revision++;hydrate();status('Contact grouping updated in your draft. Contact destinations and order are unchanged.');return}
 const el=e.target;if(el.matches('[data-path]')){write(el.dataset.path,el.type==='checkbox'?el.checked:el.type==='number'?Number(el.value):el.value)}
 else if(el.matches('[data-field]'))state[el.dataset.field]=el.type==='checkbox'?el.checked:(el.type==='number'?Number(el.value):el.value);
 else if(el.matches('input[data-channel]'))state.channels[el.dataset.channel][el.dataset.property]=el.type==='checkbox'?el.checked:el.value;
 else return;
 if(el.dataset.field==='intro_behavior')state.intro_enabled=state.intro_behavior!=='never';
 if(el.dataset.field==='intro_enabled'&&state.intro_enabled&&state.intro_behavior==='never')state.intro_behavior='hide_on_open';
 if(['intro_behavior','intro_enabled'].includes(el.dataset.field))all('[data-field]').filter(input=>['intro_behavior','intro_enabled'].includes(input.dataset.field)).forEach(input=>{if(input.type==='checkbox')input.checked=!!state[input.dataset.field];else input.value=state[input.dataset.field]});
 revision++;
 if(el.dataset.field){for(const view of ['desktop_view','mobile_view']){if(!state[view])continue;const k=el.dataset.field;if(['layout','position','size','gap'].includes(k))state[view][k]=state[k];if(k==='offset'){state[view].x=state.offset;state[view].y=state.offset}}}
 if(el.dataset.field)all('[data-field]').filter(input=>input!==el&&input.dataset.field===el.dataset.field).forEach(input=>{if(input.type==='checkbox')input.checked=!!state[el.dataset.field];else input.value=state[el.dataset.field]??''});
 if(el.dataset.path)all('[data-path]').filter(input=>input!==el&&input.dataset.path===el.dataset.path&&input.tagName!=='DIV').forEach(input=>{if(input.type==='checkbox')input.checked=!!read(el.dataset.path);else input.value=read(el.dataset.path)??''});
 if(/^channels\.[a-z]+\.appearance\.animation$/.test(el.dataset.path||'')){const key=el.dataset.path.split('.')[1],button=$('[data-preview-animation="'+key+'"]');if(button)window.FCBButtonEffects?.stop(button)}
 el.removeAttribute('aria-invalid');el.parentElement?.querySelector('.fcb-field-error')?.remove();
 updateButtons();
 all('[data-channel-fields]').forEach(el=>el.hidden=!state.channels[el.dataset.channelFields]?.enabled);
 if(el.matches('input[data-channel][data-property=value]'))all('[data-channel-summary]').filter(n=>n.dataset.channelSummary===el.dataset.channel).forEach(n=>n.textContent=el.value||'Add your contact details');
 if(el.matches('input[data-channel][data-property=enabled]'))hydrate();
 if(el.matches('[data-path$=".grouped"]'))all('[data-always-channel]').forEach(input=>input.disabled=!read(input.dataset.alwaysView+'.grouped'));
 if(el.dataset.path==='mobile_view.layout')all('[data-simple-bar-colors]').forEach(node=>node.hidden=state.mobile_view.layout!=='bar');
 if(el.matches('input[data-channel][data-property=value]'))all('input[data-channel]').filter(input=>input!==el&&input.dataset.channel===el.dataset.channel&&input.dataset.property==='value').forEach(input=>input.value=el.value);
 if(el.matches('input[data-channel][data-property=label],input[data-channel][data-property=color],input[data-channel][data-property=icon_color]'))all('input[data-channel]').filter(input=>input!==el&&input.dataset.channel===el.dataset.channel&&input.dataset.property===el.dataset.property).forEach(input=>input.value=el.value);
 refreshButtonUI();
 if(preview)preview.fcb.sync(state);
 status('Unsaved draft changes. Publish to update your site.');
});
async function save(mode){
 if(saving)return false;
 if(mode==='reset'&&!window.confirm(FCBAdmin.strings.reset))return false;
 clearTimeout(messageTimer);clearErrors();
 if(mode==='publish'){state.enabled=true;if(!checkDestinations()){updateButtons();return false}}
 const savedRevision=revision;saving=true;status(mode==='publish'?'Publishing…':FCBAdmin.strings.saving);all('[data-admin-action]').forEach(b=>{b.disabled=true;b.dataset.normalLabel=b.textContent;if(b.dataset.adminAction===mode){b.replaceChildren();const spin=document.createElement('span');spin.className='fcb-spinner';spin.setAttribute('aria-hidden','true');b.append(spin,document.createTextNode(mode==='publish'?'Publishing…':'Saving…'))}});
 try{
  const body=new URLSearchParams({action:'fcb_save',nonce:FCBAdmin.nonce,mode,config:JSON.stringify(state)});
  const response=await fetch(FCBAdmin.url,{method:'POST',body,credentials:'same-origin'});
  const result=await response.json();
  if(!result.success){if(result.data?.field)fieldError(result.data.field,result.data.message);throw new Error(result.data?.message||FCBAdmin.strings.error)}
  status(result.data.message,false,true);
  if(mode==='publish'&&result.data.config)published=fingerprint(result.data.config);
  if(['draft','publish','reset'].includes(mode)&&result.data.config)savedDraft=fingerprint(result.data.config);
  if(result.data.config&&mode!=='toggle'){if(revision===savedRevision){state=result.data.config;hydrate()}else{status(FCBAdmin.strings.newer)}}
  if(mode==='reset'&&revision===savedRevision){step=0;hydrate()}
  if(mode==='publish')messageTimer=setTimeout(()=>{if(fingerprint(state)===published)status('All changes published.',false,true)},6500);
  if(mode==='toggle'){
   const on=result.data.config.enabled;all('[data-admin-action=toggle]').forEach(b=>b.textContent=on?'Disable widget':'Enable widget');
   const badge=$('.fcb-page-heading .fcb-status');if(badge){badge.textContent=on?'Widget is live':'Widget is off';badge.classList.toggle('is-live',on)}
  }return true;
 }catch(err){status(err.message||FCBAdmin.strings.error,true);return false}
 finally{saving=false;all('[data-admin-action]').forEach(b=>{if(b.dataset.normalLabel)b.textContent=b.dataset.normalLabel;b.disabled=false});updateButtons();document.dispatchEvent(new Event('fcb:save-finished'))}
}
async function saveLatestDraft(){
 while(saving)await new Promise(resolve=>document.addEventListener('fcb:save-finished',resolve,{once:true}));
 for(;;){const intended=revision;if(!await save('draft'))return false;if(revision===intended)return true;}
}
document.addEventListener('click',async e=>{
 const animation=e.target.closest('[data-preview-animation]');if(animation){e.preventDefault();const channel=state.channels[animation.dataset.previewAnimation],chosen=channel.appearance?.animation||'inherit',effect=chosen==='inherit'?(state.quick_animation?'ripple':'none'):chosen,started=window.FCBButtonEffects?.preview(animation,effect,channel.color,state.animations?.enabled!==false);status(started?'Animation preview: two gentle cycles. Your settings are unchanged.':effect==='none'?'No attention animation is selected for this button.':'Motion preview stays off when animations are disabled, reduced motion is requested, or the page is hidden.');return}
 const launcherMove=e.target.closest('[data-move-launcher]');if(launcherMove){e.preventDefault();const viewName=launcherMove.dataset.view||currentView(),plan=buttonPlan(viewName),count=plan.extra.length,index=Number(plan.view.launcher_index??-1),from=index<0?count:Math.min(index,count),to=from+Number(launcherMove.dataset.moveLauncher);if(plan.hasMenu&&!plan.bar&&to>=0&&to<=count){write(viewName+'.launcher_index',to===count?-1:to);revision++;hydrate();status('Close control order updated in your draft. Publish to update your site.');launcherMove.focus()}return}
 const mode=e.target.closest('button[data-interface-mode]');if(mode){e.preventDefault();await changeInterface(mode.dataset.interfaceMode);return}
 const reset=e.target.closest('[data-reset-section]');if(reset){e.preventDefault();resetSection(reset.dataset.resetSection,reset.dataset.key);return}
 const action=e.target.closest('[data-admin-action]');if(action){e.preventDefault();await save(action.dataset.adminAction);return}
 const p=e.target.closest('.fcb-preset[data-preset]');if(p){preset(p.dataset.preset,p.classList.contains('fcb-simple-preset'));return}
 const viewport=e.target.closest('[data-viewport]');if(viewport){$('.fcb-preview-stage').classList.toggle('is-mobile',viewport.dataset.viewport==='mobile');all('[data-viewport]').forEach(el=>el.setAttribute('aria-pressed',String(el===viewport)));refreshButtonUI();if(preview)preview.fcb.sync(state);return}
 if(e.target.closest('#fcb-restore-preset')){preset(state.preset);return}
 if(e.target.closest('#fcb-next')){
  if(step===0&&!Object.values(state.channels).some(ch=>ch.enabled)){status(FCBAdmin.strings.methods,true);return}
  if(step===1&&!checkDestinations())return;
  step=Math.min(3,step+1);showStep();layout?.scrollIntoView({behavior:'instant',block:'start'});return;
 }
 if(e.target.closest('#fcb-back')){step=Math.max(0,step-1);showStep();return}
 const move=e.target.closest('[data-move]');
 if(move){e.preventDefault();const entries=Object.entries(state.channels),active=entries.filter(([,ch])=>ch.enabled).map(([k])=>k),index=active.indexOf(move.dataset.key),next=active[index+Number(move.dataset.move)],from=entries.findIndex(([k])=>k===move.dataset.key),to=entries.findIndex(([k])=>k===next);if(index>=0&&to>=0){revision++;[entries[from],entries[to]]=[entries[to],entries[from]];state.channels=Object.fromEntries(entries);hydrate();status('Button order updated in your draft. Publish to update your site.');move.focus()}return}
 const exit=e.target.closest('[data-exit]');if(exit){e.preventDefault();if(await saveLatestDraft())location.href=exit.href;return}
 const confirm=e.target.closest('[data-confirm]');if(confirm&&!window.confirm(confirm.dataset.confirm))e.preventDefault();
});
window.FCBAdminEditor={saveLatestDraft,getRevision:()=>revision,getState:()=>structuredClone(state),get:path=>read(path),set:(path,value,render=true)=>{write(path,value);revision++;if(render)hydrate();else{if(preview)preview.fcb.sync(state);updateButtons()}},replace:config=>{state=structuredClone(config);revision++;hydrate();status('Imported settings are an unpublished draft. Review and publish.')},isDirty:()=>fingerprint(state)!==published,save,status,hydrate};
hydrate();
applyInterface(interfaceMode);
if(layout)layout.inert=false;
})();
