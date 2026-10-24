(function () {
    'use strict';

    var API_SESSION = 'api/session.php';
    var API_CHAT = 'api/chat.php';

    var messagesEl = document.getElementById('messages');
    var inputEl = document.getElementById('input');
    var sendBtn = document.getElementById('send');
    var newBtn = document.getElementById('new-chat');
    var statusEl = document.getElementById('status');

    var csrf = '';
    var busy = false;

    function addMessage(text, kind) {
        var div = document.createElement('div');
        div.className = 'msg msg--' + kind;
        div.textContent = text;            // فقط textContent؛ هرگز innerHTML (جلوگیری از XSS)
        messagesEl.appendChild(div);
        scrollDown();
        return div;
    }

    function addCards(products) {
        if (!products || !products.length) { return; }
        var wrap = document.createElement('div');
        wrap.className = 'cards';
        products.forEach(function (p) {
            var card = document.createElement('div');
            card.className = 'card';

            if (p.image_url) {
                var img = document.createElement('img');
                img.src = p.image_url;
                img.alt = p.name || '';
                img.loading = 'lazy';
                img.referrerPolicy = 'no-referrer';
                card.appendChild(img);
            }

            var body = document.createElement('div');
            body.className = 'card__body';

            var name = document.createElement('div');
            name.className = 'card__name';
            name.textContent = p.name || '';
            body.appendChild(name);

            var price = document.createElement('div');
            price.className = 'card__price';
            price.textContent = p.price_text || '';
            body.appendChild(price);

            if (p.id) {
                var a = document.createElement('a');
                a.href = 'product.php?id=' + encodeURIComponent(String(p.id || ''));
                a.textContent = 'مشاهده محصول';
                body.appendChild(a);
            }

            var add = document.createElement('button');
            add.type = 'button';
            add.className = 'card__add';
            add.textContent = 'افزودن به سبد خرید';
            add.addEventListener('click', function () { addToCart(p.id, add); });
            body.appendChild(add);
            card.appendChild(body);
            wrap.appendChild(card);
        });
        messagesEl.appendChild(wrap);
        scrollDown();
    }

    function addToCart(id, button) {
        if (!id || !csrf || button.disabled) { return; }
        button.disabled = true;
        var previous = button.textContent;
        button.textContent = 'در حال افزودن…';
        postJson('api/cart.php', { product_id: Number(id), quantity: 1 }, { 'X-CSRF-Token': csrf })
            .then(function (res) {
                if (res.body && res.body.ok) {
                    button.textContent = 'افزوده شد ✓';
                } else {
                    button.textContent = (res.body && res.body.message) || 'افزودن ناموفق';
                }
            }).catch(function () {
                button.textContent = 'خطای اتصال';
            }).then(function () {
                window.setTimeout(function () { button.textContent = previous; button.disabled = false; }, 1800);
            });
    }

    function scrollDown() { messagesEl.scrollTop = messagesEl.scrollHeight; }
    function setStatus(t) { statusEl.textContent = t || ''; }
    function setBusy(b) { busy = b; sendBtn.disabled = b; inputEl.disabled = b; if (!b) { inputEl.focus(); } }

    function postJson(url, data, headers) {
        var h = { 'Content-Type': 'application/json' };
        if (headers) { Object.keys(headers).forEach(function (k) { h[k] = headers[k]; }); }
        return fetch(url, { method: 'POST', headers: h, body: JSON.stringify(data || {}), credentials: 'same-origin' })
            .then(function (r) {
                return r.json().catch(function () { return { ok: false, message: 'پاسخ نامعتبر از سرور.' }; })
                    .then(function (j) { return { status: r.status, body: j }; });
            });
    }

    function startConversation() {
        messagesEl.textContent = '';
        setBusy(true);
        setStatus('در حال شروع گفتگو…');
        return postJson(API_SESSION, {}).then(function (res) {
            setStatus('');
            if (res.body && res.body.ok) {
                csrf = res.body.csrf;
                addMessage(res.body.greeting, 'bot');
            } else {
                addMessage((res.body && res.body.message) || 'شروع گفتگو ممکن نشد.', 'error');
            }
        }).catch(function () {
            setStatus('');
            addMessage('اتصال به سرور برقرار نشد.', 'error');
        }).then(function () { setBusy(false); });
    }

    function send() {
        if (busy) { return; }
        var text = inputEl.value.trim();
        if (!text) { return; }
        inputEl.value = '';
        addMessage(text, 'user');
        setBusy(true);
        setStatus('دستیار در حال نوشتن…');

        postJson(API_CHAT, { message: text }, { 'X-CSRF-Token': csrf }).then(function (res) {
            setStatus('');
            if (res.body && res.body.ok) {
                addMessage(res.body.reply, 'bot');
                addCards(res.body.products);
                return;
            }
            if (res.status === 409) {                // گفتگو منقضی شده؛ گفتگوی تازه بساز
                addMessage('گفتگو منقضی شد؛ گفتگوی جدید شروع شد. پیام را دوباره بفرستید.', 'error');
                return startConversation();
            }
            addMessage((res.body && res.body.message) || 'خطایی رخ داد.', 'error');
        }).catch(function () {
            setStatus('');
            addMessage('اتصال به سرور برقرار نشد.', 'error');
        }).then(function () { setBusy(false); });
    }

    sendBtn.addEventListener('click', send);
    newBtn.addEventListener('click', startConversation);
    inputEl.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); }
    });

    startConversation();
})();
