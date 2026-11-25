/* Finite, optional per-button attention. No identifiers, network work or repeating timers. */
(()=>{'use strict';
const effects=['ripple','pulse'],state=new WeakMap(),roots=new Map(),active=new Set(),reduced=matchMedia('(prefers-reduced-motion:reduce)');
const names=['fcb-attention-ripple','fcb-attention-pulse'],waiting=new Map();
const observer=typeof IntersectionObserver==='function'?new IntersectionObserver(entries=>{for(const entry of entries){const root=waiting.get(entry.target),config=roots.get(root);if(entry.isIntersecting&&root?.isConnected&&config)sync(root,config)}},{threshold:.05}):null;
function unwatch(button){waiting.delete(button);observer?.unobserve(button)}
function stop(button){const meta=state.get(button);button.classList.remove(...names);active.delete(button);if(meta?.end){button.removeEventListener('animationend',meta.end);button.removeEventListener('animationcancel',meta.end);meta.end=null}}
function available(button){if(!button.isConnected||button.closest('[hidden],[inert]')||!button.getClientRects().length||getComputedStyle(button).visibility==='hidden')return false;const box=button.getBoundingClientRect();let left=Math.max(0,box.left),right=Math.min(innerWidth,box.right),top=Math.max(0,box.top),bottom=Math.min(innerHeight,box.bottom);for(let parent=button.parentElement;parent&&parent!==document.documentElement;parent=parent.parentElement){const css=getComputedStyle(parent);if(/hidden|clip|auto|scroll/.test(css.overflowX+' '+css.overflowY)){const rect=parent.getBoundingClientRect();left=Math.max(left,rect.left);right=Math.min(right,rect.right);top=Math.max(top,rect.top);bottom=Math.min(bottom,rect.bottom)}}return right>left&&bottom>top}
function play(button,effect,color,enabled=true){
 if(!effects.includes(effect)||!enabled||document.hidden||reduced.matches||!available(button)){stop(button);return false}
 stop(button);button.classList.add('fcb-effect-host');button.style.setProperty('--fcb-effect-color',/^#[0-9a-f]{3,8}$/i.test(color||'')?color:'#2563eb');
 // Reflow only on an explicit new effect/preview, never on ordinary page requests.
 void button.offsetWidth;button.classList.add('fcb-attention-'+effect);active.add(button);
 const meta=state.get(button)||{};meta.end=event=>{if(event.target===button&&(event.pseudoElement==='::after'||event.type==='animationcancel'))stop(button)};state.set(button,meta);button.addEventListener('animationend',meta.end);button.addEventListener('animationcancel',meta.end);
 return true;
}
function sync(root,config){
 if(config.animations?.enabled===false)cancelAll();
 for(const entry of roots.keys())if(!entry.isConnected)roots.delete(entry);roots.set(root,config);
 const launcher=root.querySelector('.fcb-launcher[data-fcb=toggle]'),hasLauncher=!!launcher&&!launcher.hidden&&root.querySelector('.fcb-choices')?.dataset.hasOptions!=='0';
 for(const button of [...root.querySelectorAll('.fcb-channel[data-channel]'),...(launcher?[launcher]:[])]){
  const isLauncher=button===launcher,channel=config.channels?.[button.dataset.channel],chosen=isLauncher?'inherit':channel?.appearance?.animation||'inherit',effect=chosen==='inherit'?(config.quick_animation&&(isLauncher?hasLauncher:!hasLauncher)?'ripple':'none'):chosen,meta=state.get(button)||{};
  if(meta.setting!==effect){stop(button);meta.setting=effect;meta.played=false;state.set(button,meta)}
  if(effect==='none'||config.animations?.enabled===false||reduced.matches||document.hidden){stop(button);unwatch(button);continue}
  if(!available(button)&&active.has(button))stop(button);
  if(!meta.played&&available(button)&&play(button,effect,(isLauncher?config.launcher_style?.background:channel?.color),true)){meta.played=true;state.set(button,meta);unwatch(button)}else if(!meta.played&&observer){waiting.set(button,root);observer.observe(button)}
 }
}
function cancelAll(){for(const button of [...active])stop(button)}
document.addEventListener('visibilitychange',()=>{if(document.hidden)cancelAll();else for(const[root,config]of roots)if(root.isConnected)sync(root,config)});
reduced.addEventListener('change',()=>{if(reduced.matches)cancelAll();else for(const[root,config]of roots)if(root.isConnected)sync(root,config)});
window.FCBButtonEffects={sync,preview:(button,effect,color,enabled=true)=>play(button,effect,color,enabled),stop};
})();