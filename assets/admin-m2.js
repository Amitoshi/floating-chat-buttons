(()=>{'use strict';
const editor=window.FCBAdminEditor;if(!window.FCBAdmin||!editor)return;
const all=s=>[...document.querySelectorAll(s)],$=s=>document.querySelector(s),labels=new Map(),searches=new WeakMap();let serial=0;
const el=(tag,text,attrs={})=>{const n=document.createElement(tag);if(text!==null)n.textContent=text;for(const[k,v]of Object.entries(attrs))n.setAttribute(k,String(v));return n};
const values=path=>{const v=editor.get(path);return Array.isArray(v)?v:typeof v==='string'?v.split(/[\r\n]+/).map(s=>s.trim()).filter(Boolean):[]};
const schedulePath=key=>key==='callback'?'callback_schedule':`channels.${key}.schedule`;
async function request(action,data={}){
 const response=await fetch(FCBAdmin.url,{method:'POST',credentials:'same-origin',body:new URLSearchParams({action,nonce:FCBAdmin.nonce,...data})});
 let result;try{result=await response.json()}catch(e){throw Error('Unable to read the server response. Please retry.')}
 if(!result.success)throw Error(result.data?.message||'This action could not be completed.');return result.data;
}
const status=(text,error=false)=>editor.status(text,error,!error);
function renderIntervals(){
 all('[data-intervals]').forEach(holder=>{
  const key=holder.dataset.intervals,path=schedulePath(key)+'.intervals',intervals=editor.get(path)||[];holder.replaceChildren();
  intervals.forEach((interval,index)=>{
   const group=el('fieldset',null,{'class':'fcb-m2-interval','data-interval':index});group.append(el('legend',`Interval ${index+1}`));
   const days=el('div',null,{'class':'fcb-m2-days','aria-label':'Days this interval starts'});
   ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'].forEach((day,n)=>{const label=el('label',null),input=el('input',null,{type:'checkbox','data-interval-day':n+1,'aria-label':day});input.checked=(interval.days||[]).includes(n+1);label.append(input,document.createTextNode(day.slice(0,3)));days.append(label)});
   group.append(days);const times=el('div',null,{'class':'fcb-m2-grid'});
   for(const[field,label]of [['open','Opens'],['close','Closes']]){const wrap=el('label',null,{'class':'fcb-field'}),input=el('input',null,{type:'time',[`data-interval-${field}`]:'','aria-label':`${key} interval ${index+1} ${label.toLowerCase()}`});input.value=interval[field];wrap.append(el('span',label),input);times.append(wrap)}
   group.append(times,el('button','Remove interval',{type:'button','class':'fcb-text-button','data-remove-interval':key}));holder.append(group);
  });
 });
}
function labelCache(picker){const path=picker.dataset.selectorPath;if(!labels.has(path)){try{labels.set(path,JSON.parse(picker.dataset.labels||'{}'))}catch(e){labels.set(path,{})}}return labels.get(path)}
function renderPickers(){
 all('[data-selector-path]').forEach(picker=>{
  const path=picker.dataset.selectorPath,cache=labelCache(picker),chips=picker.querySelector('.fcb-m2-chips');chips.replaceChildren();
  values(path).forEach(value=>{const label=cache[value]||'Selected item (unavailable)',chip=el('span',null,{'class':'fcb-m2-chip'}),remove=el('button','×',{type:'button','data-remove-selection':String(value),'aria-label':`Remove ${label}`});chip.append(document.createTextNode(label+' '),remove);chips.append(chip)});
  const input=picker.querySelector('input[role=combobox]'),results=picker.querySelector('[role=listbox]');if(!results.id)results.id='fcb-m2-results-'+(++serial);input.setAttribute('aria-controls',results.id);
 });
}
function hydrate(){renderIntervals();renderPickers()}
document.addEventListener('fcb:hydrate',hydrate);hydrate();
async function search(picker){
 const input=picker.querySelector('input[role=combobox]'),results=picker.querySelector('[role=listbox]'),note=picker.querySelector('.fcb-m2-search-status'),query=input.value.trim(),token=Symbol();searches.set(picker,token);
 if(!query){results.hidden=true;input.setAttribute('aria-expanded','false');note.textContent='Type a name to find content.';return}
 note.textContent='Searching…';
 try{const data=await request('fcb_m2_lookup',{kind:picker.dataset.kind,query});if(searches.get(picker)!==token)return;results.replaceChildren();const selected=values(picker.dataset.selectorPath).map(String);let count=0;
  for(const item of data.items){labelCache(picker)[String(item.id)]=item.label;if(selected.includes(String(item.id)))continue;const li=el('li',null),button=el('button',item.label,{type:'button','data-select-item':String(item.id),role:'option'});li.append(button);results.append(li);count++}
  results.hidden=!count;input.setAttribute('aria-expanded',String(!!count));note.textContent=count?`${count} results. Use the arrow keys to choose.`:'No matching unselected items.';
 }catch(error){note.textContent=error.message;results.hidden=true;input.setAttribute('aria-expanded','false')}
}
document.addEventListener('input',event=>{
 const input=event.target,picker=input.closest('[data-selector-path]');
 if(picker&&input.matches('input[role=combobox]')){clearTimeout(input.fcbSearchTimer);input.fcbSearchTimer=setTimeout(()=>search(picker),220);return}
 if(input.matches('[data-m2-list]')){editor.set(input.dataset.path,input.value.split(/[\r\n]+/).map(s=>s.trim()).filter(Boolean),false);return}
 if(input.matches('[data-interval-day],[data-interval-open],[data-interval-close]')){
  const holder=input.closest('[data-intervals]'),group=input.closest('[data-interval]'),path=schedulePath(holder.dataset.intervals)+'.intervals',intervals=structuredClone(editor.get(path)||[]),index=Number(group.dataset.interval);
  if(input.hasAttribute('data-interval-day')){intervals[index].days=[...group.querySelectorAll('[data-interval-day]:checked')].map(n=>Number(n.dataset.intervalDay))}
  else intervals[index][input.hasAttribute('data-interval-open')?'open':'close']=input.value;
  editor.set(path,intervals,false);editor.status('Unsaved draft changes. Publish to update your site.');
 }
});
document.addEventListener('keydown',event=>{
 const picker=event.target.closest('[data-selector-path]');if(!picker)return;const input=picker.querySelector('input[role=combobox]'),results=picker.querySelector('[role=listbox]'),buttons=[...results.querySelectorAll('button')];
 if(event.key==='Escape'){results.hidden=true;input.setAttribute('aria-expanded','false');input.focus();event.preventDefault()}
 if(['ArrowDown','ArrowUp'].includes(event.key)&&buttons.length&&!results.hidden){let index=buttons.indexOf(document.activeElement);index=event.key==='ArrowDown'?(index+1)%buttons.length:(index-1+buttons.length)%buttons.length;buttons[index].focus();event.preventDefault()}
});
document.addEventListener('click',async event=>{
 const target=event.target,picker=target.closest('[data-selector-path]'),selection=target.closest('[data-select-item]'),remove=target.closest('[data-remove-selection]');
 if(picker&&(selection||remove)){
  const path=picker.dataset.selectorPath,current=values(path);if(selection){const id=picker.dataset.kind==='content'?Number(selection.dataset.selectItem):selection.dataset.selectItem;editor.set(path,[...current,id]);picker.querySelector('input[role=combobox]').value=''}
  else editor.set(path,current.filter(v=>String(v)!==remove.dataset.removeSelection));
  picker.querySelector('[role=listbox]').hidden=true;picker.querySelector('input[role=combobox]').setAttribute('aria-expanded','false');picker.querySelector('input[role=combobox]').focus();editor.status('Unsaved draft changes. Publish to update your site.');return;
 }
 const add=target.closest('[data-add-interval]'),del=target.closest('[data-remove-interval]');if(add||del){const key=(add||del).dataset[add?'addInterval':'removeInterval'],path=schedulePath(key)+'.intervals',intervals=structuredClone(editor.get(path)||[]);if(add){if(intervals.length>=100){status('Use up to 100 intervals per channel.',true);return}intervals.push({days:[1,2,3,4,5],open:'09:00',close:'18:00'})}else intervals.splice(Number(del.closest('[data-interval]').dataset.interval),1);editor.set(path,intervals);editor.status('Unsaved draft changes. Publish to update your site.');return}
 const button=target.closest('[data-m2-action]');if(!button)return;event.preventDefault();const action=button.dataset.m2Action;button.disabled=true;
 try{
  if(action==='explain'){const data=await request('fcb_m2_explain',{config:JSON.stringify(editor.getState()),url:$('#fcb-m2-explain-url').value});$('#fcb-m2-explanation').textContent=data.reason;status('Draft visibility explained.')}
  if(action==='export'){const data=await request('fcb_m2_config',{mode:'export',config:JSON.stringify(editor.getState()),destinations:$('#fcb-m2-export-destinations').checked?'1':'0'}),blob=new Blob([JSON.stringify(data.config,null,2)],{type:'application/json'}),url=URL.createObjectURL(blob),link=el('a',null,{href:url,download:'contact-buttons-settings.json'});document.body.append(link);link.click();link.remove();setTimeout(()=>URL.revokeObjectURL(url),1000);$('#fcb-m2-transfer-status').textContent='Settings exported without enquiries, analytics history or recipient addresses.';status('Settings exported safely.')}
  if(action==='import'){const file=$('#fcb-m2-import-file').files[0];if(!file)throw Error('Choose a settings JSON file first.');if(file.size>150000)throw Error('Choose a file smaller than 150 KB.');const data=await request('fcb_m2_config',{mode:'import',json:await file.text()});editor.replace(data.config);$('#fcb-m2-transfer-status').textContent='Settings loaded into your current unpublished draft. Review before saving or publishing.'}
  if(action==='filter'){const data=await request('fcb_m2_insights',{start:$('#fcb-m2-date-start').value,end:$('#fcb-m2-date-end').value});$('#fcb-m2-metrics').innerHTML=data.html;$('#fcb-m2-range-status').textContent=data.message;status('Date filter applied.')}
  if(action==='report-generate'||action==='report-test'){
   status('Using published report settings…');const data=await request('fcb_report',{mode:action==='report-generate'?'generate':'test'});
   if(data.html){$('#fcb-report-preview').innerHTML=data.html;$('#fcb-report-preview').scrollIntoView({block:'nearest',behavior:'smooth'})}status(data.message||'Report action completed.');
  }
 }catch(error){status(error.message,true);const notice=action==='explain'?$('#fcb-m2-explanation'):action==='filter'?$('#fcb-m2-range-status'):['export','import'].includes(action)?$('#fcb-m2-transfer-status'):null;if(notice)notice.textContent=error.message}
 finally{button.disabled=false}
});
})();
