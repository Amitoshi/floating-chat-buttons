(()=>{'use strict';
if(!window.FCBHelp)return;
const root=document.querySelector('.fcb-admin');if(!root)return;
let serial=0,active=null,closeTimer=0,suppressFocus=false,scheduled=false;
const create=(tag,text,attrs={})=>{const node=document.createElement(tag);if(text)node.textContent=text;for(const [key,value]of Object.entries(attrs))node.setAttribute(key,value);return node};
function position(){
 if(!active)return;
 const {button,panel}=active,rect=button.getBoundingClientRect(),viewport=window.visualViewport;
 const width=viewport?viewport.width:window.innerWidth,height=viewport?viewport.height:window.innerHeight,leftEdge=viewport?viewport.offsetLeft:0,topEdge=viewport?viewport.offsetTop:0,pad=12;
 panel.style.maxWidth=Math.max(120,Math.min(380,width-pad*2))+'px';panel.style.maxHeight=Math.max(80,height-pad*2)+'px';
 let box=panel.getBoundingClientRect(),left=rect.right+8,top=rect.top;
 if(left+box.width>leftEdge+width-pad){
  left=rect.left-box.width-8;
  if(left<leftEdge+pad){
   const above=rect.top-topEdge-pad-8,below=topEdge+height-pad-rect.bottom-8;
   panel.style.maxHeight=Math.max(80,Math.max(above,below))+'px';box=panel.getBoundingClientRect();
   left=rect.right-box.width;top=below>=above?rect.bottom+8:rect.top-box.height-8;
  }
 }
 left=Math.max(leftEdge+pad,Math.min(left,leftEdge+width-box.width-pad));
 if(top+box.height>topEdge+height-pad)top=topEdge+height-box.height-pad;
 panel.style.left=left+'px';panel.style.top=Math.max(topEdge+pad,top)+'px';
}
function close(restore=false){
 clearTimeout(closeTimer);if(!active)return;
 const item=active;active=null;item.pinned=false;item.panel.hidden=true;item.button.setAttribute('aria-expanded','false');
 if(restore){suppressFocus=true;item.button.focus();suppressFocus=false;}
}
function open(item){
 clearTimeout(closeTimer);if(active!==item)close();active=item;item.panel.hidden=false;item.button.setAttribute('aria-expanded','true');position();
}
function delayClose(item){
 clearTimeout(closeTimer);closeTimer=setTimeout(()=>{if(active===item&&!item.pinned&&!item.panel.matches(':hover')&&!item.button.matches(':hover')&&!item.panel.contains(document.activeElement)&&document.activeElement!==item.button)close();},180);
}
function attach(target,entry){
 const host=target.closest('.fcb-field,.fcb-switch-row,.fcb-m2-picker')||target;
 if(host.dataset.fcbHelpAttached)return;host.dataset.fcbHelpAttached='1';
 const id='fcb-context-help-'+(++serial),button=create('button','i',{type:'button',class:'fcb-help-trigger','aria-label':FCBHelp.help+entry.title,'aria-expanded':'false','aria-controls':id,'aria-haspopup':'dialog','data-help-topic':entry.topic});
 const wrap=create('div',null,{class:'fcb-help-setting'});host.before(wrap);wrap.append(host,button);
 const panel=create('section',null,{class:'fcb-help-popover',id,role:'dialog','aria-modal':'false','aria-labelledby':id+'-title'});panel.hidden=true;
 const heading=create('h4',entry.title,{id:id+'-title'}),dismiss=create('button','×',{type:'button',class:'fcb-help-close','aria-label':FCBHelp.close});panel.append(heading,dismiss);
 panel.append(create('p',entry.explanation));
 for(const [label,key]of [['Effect','effect'],['Example','example'],['Default','default']]){const p=create('p');p.append(create('strong',label+': '),document.createTextNode(entry[key]));panel.append(p);}
 const link=create('a',FCBHelp.read,{href:entry.url});panel.append(link);document.body.append(panel);
 const item={button,panel,pinned:false};
 button.addEventListener('mouseenter',()=>open(item));button.addEventListener('mouseleave',()=>delayClose(item));
 button.addEventListener('focus',()=>{if(!suppressFocus)open(item)});
 button.addEventListener('click',event=>{event.preventDefault();if(active===item&&item.pinned)close();else{open(item);item.pinned=true;}});
 panel.addEventListener('mouseenter',()=>clearTimeout(closeTimer));panel.addEventListener('mouseleave',()=>delayClose(item));
 for(const node of [button,panel])node.addEventListener('focusout',event=>{if(event.relatedTarget!==button&&!panel.contains(event.relatedTarget)){item.pinned=false;delayClose(item);}});
 dismiss.addEventListener('click',()=>close(true));
 button.addEventListener('keydown',event=>{if(event.key==='ArrowDown'||event.key==='Tab'&&!event.shiftKey&&active===item){event.preventDefault();open(item);dismiss.focus();}});
 panel.addEventListener('keydown',event=>{
  if(event.key!=='Tab')return;
  if(event.shiftKey&&document.activeElement===dismiss){event.preventDefault();close(true);}
  else if(!event.shiftKey&&document.activeElement===link){
   const candidates=[...root.querySelectorAll('button,a[href],input,select,textarea,[tabindex="0"]')].filter(node=>!node.disabled&&node.getClientRects().length&&!node.closest('[hidden], [inert]'));
   const next=candidates[candidates.indexOf(button)+1];if(next){event.preventDefault();close();next.focus();}else close();
  }
 });
}
function hydrate(){
 scheduled=false;
 for(const entry of FCBHelp.entries)for(const target of root.querySelectorAll(entry.selector))attach(target,entry);
 for(const option of root.querySelectorAll('[data-path$=".schedule.timezone"] option[value="site"], [data-path="callback_schedule.timezone"] option[value="site"]'))if(option.textContent!==FCBHelp.timezoneLabel)option.textContent=FCBHelp.timezoneLabel;
}
hydrate();
document.addEventListener('fcb:hydrate',()=>{if(active)close();hydrate();});
new MutationObserver(()=>{if(!scheduled){scheduled=true;requestAnimationFrame(hydrate);}}).observe(root,{childList:true,subtree:true});
document.addEventListener('keydown',event=>{if(event.key==='Escape'&&active){event.preventDefault();event.stopPropagation();close(true);}},true);
document.addEventListener('pointerdown',event=>{if(active&&!active.panel.contains(event.target)&&event.target!==active.button)close();});
window.addEventListener('resize',position);window.addEventListener('scroll',position,true);
if(window.visualViewport){window.visualViewport.addEventListener('resize',position);window.visualViewport.addEventListener('scroll',position);}
})();
