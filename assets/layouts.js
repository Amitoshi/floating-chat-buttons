/* Shared DOM partitioning: move existing actions, never clone or rewrite destinations. */
(()=>{'use strict';
const state=new WeakMap();let spacing=null;
const restoreSpace=()=>{if(!spacing)return;for(const key of ['padding-top','padding-bottom']){const old=spacing[key];if(old.value)document.body.style.setProperty(key,old.value,old.priority);else document.body.style.removeProperty(key)}};
function space(root,enabled,edge,height){
 if(root.dataset.preview==='1')return;
 if(!enabled){restoreSpace();spacing=null;return}
 if(!spacing){spacing={};const css=getComputedStyle(document.body);for(const key of ['padding-top','padding-bottom'])spacing[key]={value:document.body.style.getPropertyValue(key),priority:document.body.style.getPropertyPriority(key),base:parseFloat(css.getPropertyValue(key))||0}}
 restoreSpace();const key='padding-'+edge;document.body.style.setProperty(key,'calc('+spacing[key].base+'px + '+height+'px)');
}
function sync(root,c,view){
 const choices=root.querySelector('.fcb-choices'),holder=root.querySelector('.fcb-choice-links'),toggle=root.querySelector('[data-fcb=toggle]'),callback=root.querySelector('.fcb-callback-link');
 let local=state.get(root);if(!local){const visible=document.createElement('div');visible.className='fcb-visible-links';visible.hidden=true;choices.before(visible);const anchor=document.createComment('contact-launcher');toggle.before(anchor);const after=document.createElement('div');after.className='fcb-choice-links fcb-stack-after';after.hidden=true;choices.append(after);local={visible,anchor,after};state.set(root,local)}
 const {visible,anchor,after}=local;const links=[...root.querySelectorAll('.fcb-channel')];for(const link of links)holder.append(link);anchor.after(toggle);choices.append(callback);callback.style.marginTop='';callback.style.marginBottom='';visible.replaceChildren();after.replaceChildren();after.hidden=true;holder.hidden=false;
 for(const node of [root,choices,visible,holder,after])for(const key of ['padding-bottom','min-width','top','max-height','margin-top','margin-bottom','max-width'])node.style.removeProperty(key);
 const enabled=links.filter(el=>!el.hidden),bar=view.mobile&&view.layout==='bar',separate=view.layout==='separate';
 const introElement=root.querySelector('.fcb-intro'),boundary=root.dataset.preview==='1'?root.closest('.fcb-preview-stage').clientWidth:innerWidth;introElement.style.maxWidth=Math.max(60,Math.min(320,boundary-(Number(view.x)||0)-12))+'px';
 const cb=!callback.hidden;let direct=[];
 if(!separate){
  if(view.grouped)direct=(view.always||[]).filter(key=>key==='callback'?cb:enabled.some(a=>a.dataset.channel===key));
  else if(bar){direct=['call','whatsapp'].filter(key=>enabled.some(a=>a.dataset.channel===key));if(!direct.length)direct=enabled.slice(0,2).map(a=>a.dataset.channel);}
 }
 let extra=enabled.filter(a=>!direct.includes(a.dataset.channel));let callbackExtra=cb&&!direct.includes('callback');
 if(bar&&c.mobile_bar?.more_action==='callback'&&callbackExtra&&!extra.length){direct.push('callback');callbackExtra=false;}
 // The channel order remains authoritative even when group controls were selected in a different order.
 for(const link of enabled)if(direct.includes(link.dataset.channel))visible.append(link);
 if(direct.includes('callback'))visible.append(callback);
 const hasMenu=extra.length>0||callbackExtra;choices.dataset.hasOptions=hasMenu?'1':'0';choices.hidden=!hasMenu;toggle.hidden=separate||!hasMenu;
 if(!hasMenu)root.classList.remove('is-open','is-menu-closing');
 // Widget.menuAccess finalizes the stateful label, icon and accessible name
 // after this function has placed the launcher in its active layout.
 if(bar&&!toggle.hidden)visible.append(toggle);
 visible.hidden=!visible.children.length;
 const floating=!bar&&!separate&&hasMenu;root.dataset.floatingStack=floating?'1':'0';
 if(floating){
  const ordered=extra.slice();if(callbackExtra)c.callback_style?.placement==='above'?ordered.unshift(callback):ordered.push(callback);
  if(callbackExtra)callback.style[c.callback_style?.placement==='above'?'marginBottom':'marginTop']=(Number(c.callback_style?.margin)||0)+'px';
  const raw=Number(view.launcher_index),index=!Number.isFinite(raw)||raw<0?ordered.length:Math.min(ordered.length,Math.max(0,Math.trunc(raw)));
  const first=ordered.slice(0,index),last=ordered.slice(index);for(const el of first)holder.append(el);for(const el of last)after.append(el);holder.hidden=!first.length;after.hidden=!last.length;
  root.classList.add('fcb-measuring');
  const stage=root.dataset.preview==='1'?root.closest('.fcb-preview-stage'):null,height=stage?stage.clientHeight:innerHeight,width=stage?stage.clientWidth:innerWidth,gap=Number(view.gap)||12;
  const maxWidth=Math.max(60,width-(Number(view.x)||0)-12);choices.style.maxWidth=maxWidth+'px';
  const css=getComputedStyle(choices),panel=(parseFloat(css.paddingTop)||0)+(parseFloat(css.borderTopWidth)||0),buttonHeight=toggle.getBoundingClientRect().height;
  const intro=root.querySelector('.fcb-intro'),bubble=c.intro_enabled!==false&&c.intro&&c.intro_behavior!=='never'?intro.getBoundingClientRect().height+gap:0;
  const dock=parseFloat(getComputedStyle(root).bottom)||Number(view.y)||0,room=Math.max(44,height-dock-bubble-buttonHeight-24-panel*2);
  if(!visible.hidden)visible.style.maxHeight=Math.max(44,room-(ordered.length?Math.min(100,room/2):0))+'px';
  const directHeight=visible.hidden?0:visible.getBoundingClientRect().height+gap,listRoom=Math.max(24,room-directHeight-(first.length?gap:0)-(last.length?gap:0));
  const firstHeight=holder.hidden?0:holder.getBoundingClientRect().height,lastHeight=after.hidden?0:after.getBoundingClientRect().height,total=firstHeight+lastHeight;
  const firstRoom=total>listRoom?listRoom*firstHeight/total:firstHeight,lastRoom=total>listRoom?listRoom*lastHeight/total:lastHeight;
  if(first.length)holder.style.maxHeight=firstRoom+'px';if(last.length)after.style.maxHeight=lastRoom+'px';
  holder.dataset.stackScroll=firstHeight>firstRoom+1?'1':'0';after.dataset.stackScroll=lastHeight>lastRoom+1?'1':'0';
  const beforeSpace=first.length?holder.getBoundingClientRect().height+gap+panel:0,afterSpace=last.length?after.getBoundingClientRect().height+gap+panel:0;
  root.style.paddingBottom=afterSpace+'px';if(!visible.hidden){visible.style.marginBottom=beforeSpace+'px';visible.style.overflowY=visible.scrollHeight>visible.clientHeight+1?'auto':'visible';}
  root.style.minWidth=Math.min(maxWidth,Math.max(toggle.getBoundingClientRect().width,choices.getBoundingClientRect().width,visible.hidden?0:visible.getBoundingClientRect().width))+'px';
  choices.style.top=(toggle.offsetTop-beforeSpace-(first.length?0:panel))+'px';after.style.marginTop=(buttonHeight+gap+(first.length?gap:0))+'px';
  root.dataset.launcherIndex=String(index);root.classList.remove('fcb-measuring');
 }
 // The bubble is absolutely anchored independently of menu visibility. Always
 // uses the full reserved stack; other modes sit above the stable direct group.
 const intro=root.querySelector('.fcb-intro');
 if(!bar){const anchorTop=!toggle.hidden?toggle.offsetTop:visible.hidden?choices.offsetTop:visible.offsetTop;let top=anchorTop;
  if(!visible.hidden)top=Math.min(top,visible.offsetTop);
  if(floating&&c.intro_behavior==='always')top=Math.min(top,parseFloat(choices.style.top)||0);
  intro.style.top=(top-intro.getBoundingClientRect().height-(Number(view.gap)||12))+'px';
 }
 root.dataset.barPosition=c.mobile_bar?.position==='top'?'top':'bottom';
 for(const [key,value]of Object.entries(c.mobile_bar||{})){if(['background','button_background','icon_color','text','border_color'].includes(key))root.style.setProperty('--fcb-bar-'+key.replaceAll('_','-'),value);if(['padding','height','border_width'].includes(key))root.style.setProperty('--fcb-bar-'+key.replaceAll('_','-'),Number(value)+'px')}
 const admin=root.dataset.preview==='1'?0:(document.getElementById('wpadminbar')?.getBoundingClientRect().height||0);root.style.setProperty('--fcb-admin-offset',admin+'px');
 const barHeight=bar?visible.getBoundingClientRect().height:0;root.style.setProperty('--fcb-bar-extent',barHeight+'px');
 space(root,bar&&c.mobile&&!!c.mobile_bar?.content_spacing,root.dataset.barPosition,barHeight);
 return {bar,hasMenu,directCount:visible.children.length};
}
window.FCBLayout={sync};
})();
