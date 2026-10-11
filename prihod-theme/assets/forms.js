document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.prihod-article .klp-form,.prihod-article .gkod-form').forEach(function (root) {
        var prefix = root.classList.contains('gkod-form') ? 'gkod' : 'klp';
        function selector(value) { return value.replace(/\.klp-/g, '.' + prefix + '-'); }
        var form = root.querySelector('form'), submit = root.querySelector(selector('.klp-submit'));
        if (!form || !submit || !form.elements.donor_email) return;
        var donor = root.dataset.requestType === 'donation', current = 0;
        if ((donor && !form.elements.donation_amount) || (!donor && (!form.elements.names || !root.querySelector(selector('.klp-services'))))) return;
        var titles = donor ? [prihodFormUI.amount, prihodFormUI.contacts, prihodFormUI.review] : [prihodFormUI.type, prihodFormUI.names, prihodFormUI.total, prihodFormUI.contacts, prihodFormUI.review];
        var panels = titles.map(function (title) { var panel = document.createElement('section'); panel.className = 'prihod-form-step'; panel.setAttribute('aria-label', title); form.insertBefore(panel, submit); return panel; });
        function move(node, index) { if (node) panels[index].appendChild(node); }
        if (donor) {
            move(form.elements.donation_amount.closest(selector('.klp-field')), 0);
            move(form.elements.note && form.elements.note.closest(selector('.klp-field')), 0);
            move(root.querySelector(selector('.klp-frequency')), 0);
            move(root.querySelector(selector('.klp-summary')), 0);
        } else {
            move(root.querySelector(selector('.klp-intention')), 0); move(root.querySelector(selector('.klp-services')), 0);
            move(form.elements.names && form.elements.names.closest(selector('.klp-field')), 1);
            move(form.elements.note && form.elements.note.closest(selector('.klp-field')), 1);
            move(root.querySelector(selector('.klp-summary')), 2);
        }
        var contactIndex = titles.length - 2;
        move(root.querySelector(selector('.klp-grid')), contactIndex); move(root.querySelector(selector('.klp-payment')), contactIndex); move(root.querySelector(selector('.klp-consent')), contactIndex);
        var progress = document.createElement('ol'); progress.className = 'prihod-form-progress'; progress.setAttribute('aria-label', prihodFormUI.steps);
        titles.forEach(function (title, index) { var item = document.createElement('li'); item.textContent = (index + 1) + '. ' + title; progress.appendChild(item); }); form.insertBefore(progress, form.firstChild);
        var controls = document.createElement('div'); controls.className = 'prihod-form-actions';
        var back = document.createElement('button'), next = document.createElement('button'); back.type = next.type = 'button'; back.textContent = prihodFormUI.back; next.textContent = prihodFormUI.next; controls.append(back, next); form.insertBefore(controls, submit);
        var alert = document.createElement('p'); alert.className = 'prihod-form-alert'; alert.setAttribute('role','alert'); alert.hidden = true; controls.before(alert);
        function validPanel() {
            var invalid = [...panels[current].querySelectorAll('input,textarea,select')].find(function (field) { return !field.checkValidity(); });
            if (invalid) { invalid.reportValidity(); return false; }
            if (!donor && current === 0 && !form.querySelector(selector('.klp-service input:checked'))) { alert.textContent = prihodFormUI.service; alert.hidden = false; return false; }
            alert.hidden = true; return true;
        }
        function review() {
            var panel = panels[panels.length - 1]; panel.replaceChildren();
            var heading = document.createElement('h3'); heading.textContent = prihodFormUI.check; panel.appendChild(heading);
            var list = document.createElement('dl');
            ['names','note','donor_name','donor_email','donation_amount'].forEach(function (name) {
                var input = form.elements[name]; if (!input || !input.value.trim()) return;
                var label = input.closest(selector('.klp-field'))?.querySelector('label')?.textContent || name;
                var term = document.createElement('dt'), detail = document.createElement('dd'); term.textContent = label.replace(/\*/g,'').trim(); detail.textContent = input.value; list.append(term,detail);
            });
            var selected = [...form.querySelectorAll(selector('.klp-service input:checked'))].map(function (input) { return input.closest(selector('.klp-service')).querySelector(selector('.klp-service__name')).textContent.trim(); });
            if (selected.length) { var term = document.createElement('dt'), detail = document.createElement('dd'); term.textContent = prihodFormUI.type; detail.textContent = selected.join('; '); list.append(term,detail); }
            var total = root.querySelector('output'); if (total) { var term = document.createElement('dt'), detail = document.createElement('dd'); term.textContent = prihodFormUI.total; detail.textContent = total.textContent; list.append(term,detail); }
            panel.appendChild(list);
        }
        function show(focus) {
            panels.forEach(function (panel,index) { panel.hidden = index !== current; });
            [...progress.children].forEach(function (item,index) { if (index === current) item.setAttribute('aria-current','step'); else item.removeAttribute('aria-current'); });
            back.hidden = current === 0; next.hidden = current === panels.length - 1; submit.hidden = current !== panels.length - 1;
            var secure = root.querySelector(selector('.klp-secure')); if (secure) secure.hidden = current !== panels.length - 1;
            if (current === panels.length - 1) review();
            if (focus) { panels[current].tabIndex = -1; panels[current].focus(); }
        }
        next.addEventListener('click',function () { if (validPanel()) { current++; show(true); } });
        back.addEventListener('click',function () { current = Math.max(0,current-1); alert.hidden = true; show(true); });
        form.addEventListener('submit',function (event) {
            if (current !== panels.length - 1) { event.preventDefault(); event.stopImmediatePropagation(); if (validPanel()) { current++; show(true); } return; }
            var invalid = [...form.elements].find(function (field) { return field.checkValidity && !field.checkValidity(); });
            if (invalid) { event.preventDefault(); event.stopImmediatePropagation(); var index = panels.findIndex(function (panel) { return panel.contains(invalid); }); if (index >= 0) current = index; show(true); invalid.reportValidity(); }
        },true);
        root.classList.add('prihod-stepped-form'); show(false);
    });
});
