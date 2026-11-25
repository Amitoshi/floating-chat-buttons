(()=>{'use strict';
document.addEventListener('submit',async e=>{
 const form=e.target.closest('.fcb-form');if(!form)return;e.preventDefault();
 if(form.dataset.sending==='1')return;
 const root=form.closest('.fcb-widget'),c=JSON.parse(root.dataset.config||'{}'),feedback=form.querySelector('.fcb-feedback'),button=form.querySelector('[type=submit]'),input=form.querySelector('[name=contact]');
 const tell=(text,error=false,field=false)=>{feedback.textContent=text;feedback.classList.toggle('is-error',error);if(error){if(field)input.setAttribute('aria-invalid','true');else input.removeAttribute('aria-invalid');feedback.focus()}else input.removeAttribute('aria-invalid')};
 const preview=root.dataset.preview==='1',origin=root.dataset.source==='recovery'?'recovery':'widget',eventId=window.FCBEvents?.uuid?.()||crypto.randomUUID();
 const value=input.value.trim(),digits=value.replace(/[^0-9]/g,'');
 const email=/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
 const phone=/^\+?[0-9() .-]{7,30}$/.test(value)&&digits.length>=7&&digits.length<=15;
 if(!email&&!phone){if(!preview)window.FCBEvents?.failure(origin,eventId);tell(FCBForm.invalid,true,true);return}
 if(c.consent&&!form.querySelector('[name=consent]').checked){if(!preview)window.FCBEvents?.failure(origin,eventId);tell(FCBForm.consent,true);return}
 if(root.dataset.preview==='1'){tell(FCBForm.preview);return}
 tell(FCBForm.sending);form.dataset.sending='1';button.disabled=true;form.setAttribute('aria-busy','true');const label=button.textContent;button.textContent=FCBForm.sending;
 try{
  const nonceBody=new URLSearchParams({action:'fcb_nonce'});
  const nonceResponse=await fetch(FCBForm.url,{method:'POST',body:nonceBody,credentials:'same-origin',cache:'no-store'});
  const nonceResult=await nonceResponse.json();if(!nonceResult.success)throw new Error(nonceResult.data?.message||FCBForm.network);
  const body=new FormData(form);body.set('action','fcb_submit');body.set('nonce',nonceResult.data.nonce);body.set('source',location.href);body.set('kind',root.dataset.kind||c.form_kind||'callback');body.set('origin',origin);body.set('contact_source',root.dataset.contactSource||'');body.set('event_id',eventId);body.set('analytics_consent',window.FCBEvents?.consent?.()?'1':'0');
  if(!c.name_field)body.delete('name');if(!c.message_field)body.delete('message');
  const response=await fetch(FCBForm.url,{method:'POST',body,credentials:'same-origin',cache:'no-store'});
  const result=await response.json();tell(result.data?.message||FCBForm.network,!result.success,result.data?.field==='contact');
  if(result.success){window.FCBEvents?.lead(root.dataset.kind||c.form_kind||'callback',origin,eventId);form.reset();}
 }catch(err){tell(err.name==='Error'?err.message:FCBForm.network,true)}
 finally{form.dataset.sending='0';button.disabled=false;button.textContent=label;form.removeAttribute('aria-busy');root.fcb?.sync(c)}
});
})();
