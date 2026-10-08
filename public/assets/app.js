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
    const field=input.closest('.field')||input.parentElement;
    if(!field||field.querySelector('.password-eye-toggle'))return;
    field.classList.add('password-field');
    const toggle=document.createElement('button');
    toggle.type='button';
    toggle.className='password-eye-toggle';
    toggle.setAttribute('aria-controls',input.id||'');
    const eye='<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>';
    const eyeOff='<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8"/><path d="M9.9 5.2A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a15.7 15.7 0 0 1-3 3.8M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7c1.3 0 2.5-.3 3.5-.7"/></svg>';
    const updateToggle=()=>{
        const visible=input.type==='text';
        toggle.setAttribute('aria-label',visible?'Hide password':'Show password');
        toggle.title=visible?'Hide password':'Show password';
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

document.querySelectorAll('[data-tabs]').forEach(tabs=>{
    const tablist=tabs.querySelector('[role="tablist"]');
    const tabButtons=[...tabs.querySelectorAll('[role="tab"]')];
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
    activate(tabButtons.find(tab=>tab.getAttribute('aria-selected')==='true')||tabButtons[0]);
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
