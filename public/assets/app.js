document.addEventListener('submit',event=>{const form=event.target;if(form.dataset.confirm&&!confirm(form.dataset.confirm)){event.preventDefault();return;}const button=form.querySelector('button[type="submit"]');if(button){button.disabled=true;setTimeout(()=>button.disabled=false,5000);}});

const labelFor=field=>{
    const container=field.closest('.field')||field.parentElement;
    const nearby=container?.querySelector('label');
    if(nearby)return nearby.textContent.replace(/\s+/g,' ').trim().replace(/\s*\(optional\)/i,'');
    if(field.id){
        const associated=[...document.querySelectorAll('label[for]')].find(label=>label.htmlFor===field.id);
        if(associated)return associated.textContent.replace(/\s+/g,' ').trim().replace(/\s*\(optional\)/i,'');
    }
    return field.name?field.name.replace(/[_-]+/g,' '):'this field';
};

document.querySelectorAll('input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]):not([type="submit"]):not([type="button"]),textarea').forEach(field=>{
    if(field.hasAttribute('placeholder')||field.type==='file'){
        if(field.type==='file'&&!field.hasAttribute('title'))field.title=`Choose ${labelFor(field).toLowerCase()}`;
        return;
    }
    const label=labelFor(field).toLowerCase();
    if(field instanceof HTMLTextAreaElement){field.placeholder=`Write ${label}`;return;}
    if(field.type==='date'||field.type==='datetime-local'||field.type==='time'){
        field.title=`Choose ${label}`;
        return;
    }
    field.placeholder=field.type==='search'?`Search ${label.replace(/^search\s+/,'')}`:`Enter ${label}`;
});

document.querySelectorAll('select').forEach(select=>{
    if(!select.hasAttribute('title'))select.title=`Choose ${labelFor(select).toLowerCase()}`;
    if(select.name==='period'||select.options[0]?.value==='')return;
    const prompt=document.createElement('option');
    prompt.value='';
    prompt.disabled=true;
    prompt.hidden=true;
    prompt.textContent=`Choose ${labelFor(select).toLowerCase()}`;
    select.insertBefore(prompt,select.firstChild);
});

document.querySelectorAll('input[type="password"]').forEach(input=>{
    const field=input.parentElement;
    if(!field||input.closest('.password-input-wrap'))return;
    const wrapper=document.createElement('span');
    wrapper.className='password-input-wrap';
    input.before(wrapper);
    wrapper.append(input);
    const toggle=document.createElement('button');
    toggle.type='button';
    toggle.className='password-eye-toggle';
    toggle.setAttribute('aria-controls',input.id||'');
    const eye='<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>';
    const eyeOff='<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8"/><path d="M9.9 5.2A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a15.7 15.7 0 0 1-3 3.8M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7c1.3 0 2.5-.3 3.5-.7"/></svg>';
    const updateToggle=()=>{
        const visible=input.type==='text';
        const label=input.dataset.secretLabel||'password';
        toggle.setAttribute('aria-label',`${visible?'Hide':'Show'} ${label}`);
        toggle.setAttribute('aria-pressed',String(visible));
        toggle.title=`${visible?'Hide':'Show'} ${label}`;
        toggle.innerHTML=visible?eyeOff:eye;
    };
    toggle.addEventListener('click',()=>{
        input.type=input.type==='password'?'text':'password';
        updateToggle();
        input.focus();
    });
    updateToggle();
    input.insertAdjacentElement('afterend',toggle);
});

const aiSettings=document.querySelector('[data-ai-settings]');
const certificateEditor=document.querySelector('[data-certificate-editor]');
if(certificateEditor){
    const canvas=certificateEditor.querySelector('[data-certificate-canvas]');
    const sync=()=>{
        certificateEditor.querySelectorAll('[data-placement-controls]').forEach(row=>{
            const key=row.dataset.placementControls;
            const overlay=canvas?.querySelector(`[data-placement="${key}"]`);if(!overlay)return;
            const value=property=>row.querySelector(`[name="placements[${key}][${property}]"]`).value;
            overlay.hidden=!row.querySelector('[type=checkbox]').checked;
            overlay.style.left=value('x')+'%';overlay.style.top=value('y')+'%';overlay.style.width=value('width')+'%';overlay.style.textAlign={L:'left',C:'center',R:'right'}[value('align')];overlay.style.fontSize=(Number(value('font_size'))*25.4/72*canvas.clientWidth/Number(canvas.dataset.widthMm))+'px';
        });
    };
    certificateEditor.addEventListener('input',sync);sync();
    if(canvas&&window.ResizeObserver)new ResizeObserver(sync).observe(canvas);
    canvas?.querySelectorAll('[data-placement]').forEach(overlay=>overlay.addEventListener('pointerdown',event=>{
        event.preventDefault();overlay.setPointerCapture(event.pointerId);
        const rect=canvas.getBoundingClientRect(),key=overlay.dataset.placement;
        const row=certificateEditor.querySelector(`[data-placement-controls="${key}"]`);
        const x=row.querySelector(`[name="placements[${key}][x]"]`),y=row.querySelector(`[name="placements[${key}][y]"]`),width=row.querySelector(`[name="placements[${key}][width]"]`);
        const initialX=Number(x.value),initialY=Number(y.value),startX=event.clientX,startY=event.clientY;
        const move=e=>{x.value=Math.max(0,Math.min(100-Number(width.value),initialX+(e.clientX-startX)*100/rect.width)).toFixed(1);y.value=Math.max(0,Math.min(95,initialY+(e.clientY-startY)*100/rect.height)).toFixed(1);sync();};
        const end=()=>{overlay.removeEventListener('pointermove',move);overlay.removeEventListener('pointerup',end);overlay.removeEventListener('pointercancel',end);};
        overlay.addEventListener('pointermove',move);overlay.addEventListener('pointerup',end);overlay.addEventListener('pointercancel',end);
    }));
    if(canvas?.dataset.format==='pdf'){
        const status=certificateEditor.querySelector('[data-certificate-preview-status]');
        (async()=>{try{const pdfjs=await import('https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/pdf.min.mjs');pdfjs.GlobalWorkerOptions.workerSrc='https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/pdf.worker.min.mjs';const pdf=await pdfjs.getDocument(canvas.dataset.background).promise;const page=await pdf.getPage(1);const viewport=page.getViewport({scale:1.5});const target=canvas.querySelector('canvas');target.width=viewport.width;target.height=viewport.height;await page.render({canvasContext:target.getContext('2d'),viewport}).promise;}catch{status.textContent='PDF visual preview could not load. Use the coordinate fields and generated sample PDF to check placement.';}})();
    }
}
const gatewayForm=document.querySelector('[data-payment-gateway]');
document.querySelector('[data-select-enrollment]')?.addEventListener('click',()=>{
    const checks=[...document.querySelectorAll('input[form="bulk-enrollment"]:not(:disabled)')];
    const select=checks.some(check=>!check.checked);checks.forEach(check=>check.checked=select);
});
if(gatewayForm){
    const types=JSON.parse(document.getElementById('payment-type-fields').textContent);
    const type=gatewayForm.querySelector('[name="provider_type"]');
    const api=gatewayForm.querySelector('[name="configure_api"]');
    const updateFields=()=>{
        const fields=types[type.value]||{};
        gatewayForm.querySelectorAll('[data-gateway-field]').forEach(container=>{
            const field=fields[container.dataset.gatewayField];
            const input=container.querySelector('input');
            const visible=Boolean(field)&&(!field.private||api.checked);
            container.hidden=!visible;input.disabled=!visible;input.required=visible&&Boolean(field.required||(api.checked&&field.api_required));
            if(field){container.querySelector('label').textContent=field.label+(input.required?' *':' (optional)');input.placeholder=`Enter ${field.label.toLowerCase()}`;}
        });
        const environment=gatewayForm.querySelector('[data-gateway-api-environment]');environment.hidden=!api.checked;environment.querySelector('select').disabled=!api.checked;
        gatewayForm.querySelector('[name="instructions"]').required=type.value==='Other';
    };
    type.addEventListener('change',updateFields);api.addEventListener('change',updateFields);updateFields();
}
if(aiSettings){
    const presets=JSON.parse(document.getElementById('ai-provider-presets').textContent);
    const provider=aiSettings.querySelector('#ai-provider');
    const choice=aiSettings.querySelector('#ai-model-choice');
    const model=aiSettings.querySelector('#ai-model');
    const baseUrl=aiSettings.querySelector('#ai-base-url');
    const testButton=aiSettings.querySelector('[data-ai-test]');
    const result=aiSettings.querySelector('[data-ai-test-result]');
    const syncModel=()=>{
        const custom=choice.value==='__custom';
        model.readOnly=!custom;
        if(!custom)model.value=choice.value;
        model.hidden=!custom;
        aiSettings.querySelector('.custom-model-label').hidden=!custom;
    };
    syncModel();
    choice.addEventListener('change',()=>{if(choice.value==='__custom')model.value='';syncModel();if(choice.value==='__custom')model.focus();});
    provider.addEventListener('change',()=>{
        const preset=presets[provider.value];
        choice.replaceChildren(...preset.models.map(value=>new Option(value,value)),new Option('Custom model ID','__custom'));
        choice.value=preset.models[0];baseUrl.value=preset.url;syncModel();
        result.textContent='Provider changed. Enter its API key and test the connection.';
        delete result.dataset.state;
    });
    let revision=0;
    aiSettings.addEventListener('input',()=>{revision++;result.textContent='';delete result.dataset.state;});
    testButton.addEventListener('click',async()=>{
        if(!aiSettings.reportValidity())return;
        if(aiSettings.querySelector('[name="clear_api_key"]')?.checked){result.textContent='Uncheck Remove saved API key before testing.';result.dataset.state='error';return;}
        const currentRevision=revision;
        testButton.disabled=true;result.textContent='Testing connection…';delete result.dataset.state;
        const data=Object.fromEntries(new FormData(aiSettings));delete data._method;
        try{
            const response=await fetch('/admin/site-settings/ai/test',{method:'POST',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify(data)});
            const payload=await response.json();
            if(currentRevision!==revision)return;
            result.textContent=payload.message||'Connection test failed. Try again shortly.';
            if(payload.errors)result.textContent=Object.values(payload.errors).flat().join(' ');
            result.dataset.state=response.ok&&payload.ok?'success':'error';
        }catch{if(currentRevision===revision){result.textContent='Could not complete the connection test. Check connectivity and try again.';result.dataset.state='error';}}
        finally{testButton.disabled=false;}
    });
}

document.querySelectorAll('[data-tabs]').forEach(tabs=>{
    const tablist=tabs.querySelector('[role="tablist"]');
    const tabButtons=[...(tablist?.querySelectorAll('[role="tab"]')||[])];
    const panels=tabButtons.map(tab=>document.getElementById(tab.getAttribute('aria-controls'))).filter(Boolean);
    const activate=(tab,focus=false)=>{
        tabButtons.forEach(button=>{
            const selected=button===tab;
            button.setAttribute('aria-selected',String(selected));
            button.tabIndex=selected?0:-1;
            const panel=document.getElementById(button.getAttribute('aria-controls'));
            if(panel)panel.hidden=!selected;
        });
        if(focus)tab.focus();
    };
    tabs.classList.add('tabs-enhanced');
    const hashTarget=document.getElementById(decodeURIComponent(location.hash.slice(1)));
    const hashTab=hashTarget&&tabButtons.find(tab=>document.getElementById(tab.getAttribute('aria-controls'))?.contains(hashTarget));
    activate(hashTab||tabButtons.find(tab=>tab.getAttribute('aria-selected')==='true')||tabButtons[0]);
    tablist?.addEventListener('click',event=>{
        const tab=event.target.closest('[role="tab"]');
        if(tab&&tabButtons.includes(tab))activate(tab);
    });
    tablist?.addEventListener('keydown',event=>{
        const index=tabButtons.indexOf(document.activeElement);
        if(index<0)return;
        let next=index;
        if(event.key==='ArrowRight')next=(index+1)%tabButtons.length;
        else if(event.key==='ArrowLeft')next=(index-1+tabButtons.length)%tabButtons.length;
        else if(event.key==='Home')next=0;
        else if(event.key==='End')next=tabButtons.length-1;
        else return;
        event.preventDefault();activate(tabButtons[next],true);
    });
});

document.querySelectorAll('form[data-learner-profile]').forEach(form=>form.addEventListener('submit',event=>{
    const invalid=[...form.elements].find(field=>field.required&&!field.disabled&&!field.checkValidity());
    if(!invalid)return;
    event.preventDefault();
    const panel=invalid.closest('[role="tabpanel"]');
    if(panel?.hidden){
        const tab=form.querySelector(`[aria-controls="${CSS.escape(panel.id)}"]`);
        tab?.click();
    }
    requestAnimationFrame(()=>{invalid.focus();invalid.reportValidity();});
}));

document.querySelectorAll('[data-dialog-open]').forEach(button=>button.addEventListener('click',()=>{
    document.getElementById(button.dataset.dialogOpen)?.showModal();
}));
document.querySelectorAll('[data-dialog-close]').forEach(button=>button.addEventListener('click',()=>button.closest('dialog')?.close()));
document.querySelectorAll('dialog').forEach(dialog=>dialog.addEventListener('click',event=>{
    if(event.target===dialog)dialog.close();
}));

document.querySelectorAll('form').forEach(form=>{
    const period=form.querySelector('select[name="period"]');
    const start=form.querySelector('input[name="start_date"]');
    const end=form.querySelector('input[name="end_date"]');
    if(!period||!start||!end)return;
    const updateDateRange=()=>{
        const custom=period.value==='custom';
        [start,end].forEach(input=>{
            input.closest('.field')?.toggleAttribute('hidden',!custom);
            input.disabled=!custom;
        });
    };
    period.addEventListener('change',updateDateRange);
    updateDateRange();
});

document.querySelectorAll('[data-dependent] input,[data-dependent] select,[data-dependent] textarea').forEach(input=>{
    input.dataset.requiredWhenActive='true';
    input.required=true;
});
document.querySelectorAll('select[data-toggle]').forEach(control=>{
    const updateDependent=()=>document.querySelectorAll(`[data-dependent="${CSS.escape(control.dataset.toggle)}"]`).forEach(field=>{
        const active=control.value==='1';
        field.hidden=!active;
        field.querySelectorAll('input,select,textarea').forEach(input=>{
            input.disabled=!active;
            if(input.dataset.requiredWhenActive==='true')input.required=active;
        });
    });
    control.addEventListener('change',updateDependent);
    updateDependent();
});

const reopen=document.querySelector('[data-reopen-dialog]');
if(reopen){document.getElementById(reopen.dataset.reopenDialog)?.showModal();}

const preferenceKey='lwotowone-accessibility';
let preferences={scale:1,font:'site',spacing:'normal',colors:'normal',underline:false,motion:false};
try{preferences={...preferences,...JSON.parse(localStorage.getItem(preferenceKey)||'{}')};}catch{}
const applyPreferences=()=>{
    document.documentElement.style.zoom=String(preferences.scale);
    document.documentElement.classList.toggle('a11y-readable-font',preferences.font==='readable');
    document.documentElement.classList.toggle('a11y-serif-font',preferences.font==='serif');
    document.documentElement.classList.toggle('a11y-relaxed-spacing',preferences.spacing==='relaxed');
    document.documentElement.classList.toggle('a11y-wide-spacing',preferences.spacing==='wide');
    document.documentElement.classList.toggle('theme-high-contrast',preferences.colors==='contrast');
    document.documentElement.classList.toggle('theme-dark',preferences.colors==='dark');
    document.documentElement.classList.toggle('theme-grayscale',preferences.colors==='grayscale');
    document.documentElement.classList.toggle('underline-links',Boolean(preferences.underline));
    document.documentElement.classList.toggle('reduce-motion',Boolean(preferences.motion));
    document.querySelector('#a11y-text-size')?.setAttribute('value',String(Math.round(preferences.scale*100)));
    const output=document.querySelector('[data-a11y-output="text-size"]');if(output)output.value=`${Math.round(preferences.scale*100)}%`;
    ['font','spacing','colors'].forEach(key=>{const control=document.querySelector(`[data-accessibility="${key}"]`);if(control)control.value=preferences[key];});
    document.querySelector('[data-accessibility="underline"]')?.setAttribute('aria-pressed',String(Boolean(preferences.underline)));
    document.querySelector('[data-accessibility="motion"]')?.setAttribute('aria-pressed',String(Boolean(preferences.motion)));
};
const savePreferences=()=>{try{localStorage.setItem(preferenceKey,JSON.stringify(preferences));}catch{}applyPreferences();};
applyPreferences();
document.querySelectorAll('[data-accessibility]').forEach(button=>{
    if(button.matches('input[type="range"],select')){
        button.addEventListener(button.matches('input')?'input':'change',()=>{
            if(button.dataset.accessibility==='text-size')preferences.scale=Number(button.value)/100;
            else preferences[button.dataset.accessibility]=button.value;
            savePreferences();
        });
        return;
    }
    button.addEventListener('click',()=>{
    switch(button.dataset.accessibility){
        case'text-size':preferences.scale=Number(button.value)/100;break;
        case'font':preferences.font=button.value;break;
        case'spacing':preferences.spacing=button.value;break;
        case'colors':preferences.colors=button.value;break;
        case'underline':preferences.underline=!preferences.underline;break;
        case'motion':preferences.motion=!preferences.motion;break;
        case'read-aloud':{
            const readButton=button;
            if(window.speechSynthesis?.speaking){window.speechSynthesis.cancel();readButton.setAttribute('aria-pressed','false');readButton.innerHTML='<i class="fas fa-volume-up" aria-hidden="true"></i> Read page aloud';}
            else if(window.speechSynthesis&&window.SpeechSynthesisUtterance){const content=document.querySelector('#main,#auth-main')?.innerText?.trim();if(content){const speech=new SpeechSynthesisUtterance(content);speech.onend=()=>{readButton.setAttribute('aria-pressed','false');readButton.innerHTML='<i class="fas fa-volume-up" aria-hidden="true"></i> Read page aloud';};window.speechSynthesis.speak(speech);readButton.setAttribute('aria-pressed','true');readButton.innerHTML='<i class="fas fa-stop" aria-hidden="true"></i> Stop reading';}}
            break;
        }
        case'reset':preferences={scale:1,font:'site',spacing:'normal',colors:'normal',underline:false,motion:false};break;
    }
    savePreferences();
    });
});
document.addEventListener('keydown',event=>{if(event.altKey&&event.key==='0'){event.preventDefault();preferences={scale:1,font:'site',spacing:'normal',colors:'normal',underline:false,motion:false};savePreferences();}});

const helpChat=document.querySelector('[data-help-chat]');
if(helpChat){
    const toggle=helpChat.querySelector('.help-chat-toggle');
    const panel=helpChat.querySelector('.help-chat-panel');
    const close=helpChat.querySelector('[data-chat-close]');
    const input=helpChat.querySelector('#help-chat-input');
    const messages=helpChat.querySelector('[data-chat-messages]');
    const openChat=()=>{panel.hidden=false;toggle.setAttribute('aria-expanded','true');input.focus();};
    const closeChat=()=>{panel.hidden=true;toggle.setAttribute('aria-expanded','false');toggle.focus();};
    toggle.addEventListener('click',()=>panel.hidden?openChat():closeChat());
    close.addEventListener('click',closeChat);
    document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!panel.hidden)closeChat();});
    helpChat.querySelector('[data-chat-form]').addEventListener('submit',async event=>{
        event.preventDefault();const question=input.value.trim();if(!question)return;
        const userBubble=document.createElement('p');userBubble.className='chat-question';userBubble.textContent=question;messages.append(userBubble);
        input.value='';input.disabled=true;messages.scrollTop=messages.scrollHeight;
        const reply=document.createElement('p');reply.className='chat-reply';reply.textContent='One moment while I look into that…';messages.append(reply);
        try{
            const response=await fetch('/help/chat',{method:'POST',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content||''},body:JSON.stringify({message:question})});
            const result=await response.json();reply.textContent=response.ok&&result.reply?result.reply:'I could not reach the help service just now. Please visit /faq or contact our team at /contact.';
        }catch{reply.textContent='I could not reach the help service just now. Please visit /faq or contact our team at /contact.';}
        finally{input.disabled=false;input.focus();messages.scrollTop=messages.scrollHeight;}
    });
}
