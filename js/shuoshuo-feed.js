/* One active comment panel keeps WordPress/plugin form IDs unique in the feed. */
(() => {
    'use strict';
    if (window.iroShuoshuoFeedInstalled) return;
    window.iroShuoshuoFeedInstalled = true;

    let current;
    let turnstileReady;

    function loadTurnstile() {
        if (window.turnstile) return Promise.resolve(window.turnstile);
        if (turnstileReady) return turnstileReady;
        turnstileReady = new Promise((resolve, reject) => {
            const existing = document.querySelector('script[src*="challenges.cloudflare.com/turnstile/"]');
            const script = existing || document.createElement('script');
            const timer = setTimeout(() => reject(new Error('captcha')), 20000);
            const loaded = () => {
                clearTimeout(timer);
                if (window.turnstile) resolve(window.turnstile);
                else reject(new Error('captcha'));
            };
            script.addEventListener('load', loaded, {once: true});
            script.addEventListener('error', () => {
                clearTimeout(timer);
                reject(new Error('captcha'));
            }, {once: true});
            if (!existing) {
                script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
                script.async = true;
                document.head.appendChild(script);
            }
        }).catch(error => {
            turnstileReady = null;
            throw error;
        });
        return turnstileReady;
    }

    function plainText(value) {
        const template = document.createElement('template');
        template.innerHTML = String(value || '');
        return template.content.textContent.trim().slice(0, 400);
    }

    class Feed {
        constructor(root) {
            this.root = root;
            this.labels = JSON.parse(root.querySelector('.iro-shuoshuo-config').textContent);
            this.drafts = new Map();
            this.requests = new Set();
            this.active = null;
            this.click = this.click.bind(this);
            this.submit = this.submit.bind(this);
            root.addEventListener('click', this.click);
            root.addEventListener('submit', this.submit);
        }

        status(panel, message, error = false) {
            let status = panel.querySelector('[data-iro-form-status]');
            if (!status) {
                status = document.createElement('p');
                status.className = 'iro-shuoshuo-status';
                status.dataset.iroFormStatus = '';
                status.setAttribute('role', 'status');
                status.setAttribute('aria-live', 'polite');
                panel.appendChild(status);
            }
            status.textContent = message;
            status.hidden = !message;
            status.classList.toggle('is-error', error);
        }

        async request(fields, writing = false) {
            const controller = new AbortController();
            this.requests.add(controller);
            const timer = setTimeout(() => controller.abort(), 30000);
            try {
                const response = await fetch(this.root.dataset.ajaxUrl, {
                    method: 'POST', credentials: 'same-origin', body: fields,
                    signal: controller.signal, cache: 'no-store'
                });
                const text = await response.text();
                let result;
                try { result = JSON.parse(text); } catch (_) {
                    const message = response.headers.get('content-type')?.includes('text/plain') ? plainText(text) : '';
                    throw new Error(response.ok && writing ? this.labels.uncertain : (message || this.labels.failed));
                }
                if (!response.ok || !result.success) {
                    throw new Error(plainText(result.data?.message || result.data) || this.labels.submitFailed);
                }
                return result.data;
            } catch (error) {
                if (error.name === 'AbortError' || error instanceof TypeError) {
                    throw new Error(writing ? this.labels.uncertain : this.labels.failed);
                }
                throw error;
            } finally {
                clearTimeout(timer);
                this.requests.delete(controller);
            }
        }

        remember(entry) {
            const form = entry.panel.querySelector('.iro-shuoshuo-form');
            if (!form) return;
            const values = [];
            // Never reuse security tokens or captcha answers across panel reloads.
            const excluded = /nonce|captcha|timestamp|^id$|turnstile|^action$|^submit$|^comment_post_ID$|^_wp_http_referer$/i;
            for (const field of form.elements) {
                if (!field.name || excluded.test(field.name) || ['file', 'submit', 'button', 'password'].includes(field.type)) continue;
                values.push({name: field.name, value: field.value, checked: field.checked});
            }
            this.drafts.set(entry.id, {values, reply: entry.panel.querySelector('[data-iro-reply-name]')?.textContent || ''});
        }

        restore(entry) {
            const draft = this.drafts.get(entry.id);
            const form = entry.panel.querySelector('.iro-shuoshuo-form');
            if (!form || !draft) return;
            for (const value of draft.values) {
                for (const field of form.elements) {
                    if (field.name !== value.name) continue;
                    if (['checkbox', 'radio'].includes(field.type)) {
                        if (field.value === value.value) field.checked = value.checked;
                    } else field.value = value.value;
                }
            }
            const reply = entry.panel.querySelector('[data-iro-reply-status]');
            if (reply) {
                reply.hidden = !Number(form.elements.namedItem('comment_parent')?.value);
                reply.querySelector('[data-iro-reply-name]').textContent = draft.reply;
            }
        }

        removeCaptcha(entry) {
            if (entry.widget !== undefined && window.turnstile) {
                window.turnstile.remove(entry.widget);
                entry.widget = undefined;
            }
        }

        close() {
            if (!this.active) return;
            const entry = this.active;
            for (const request of this.requests) request.abort();
            this.remember(entry);
            this.removeCaptcha(entry);
            entry.button.setAttribute('aria-expanded', 'false');
            entry.panel.hidden = true;
            entry.panel.replaceChildren();
            this.active = null;
        }

        async load(entry, page = 1, listOnly = false) {
            if (entry.loading) return;
            entry.loading = true;
            entry.panel.setAttribute('aria-busy', 'true');
            this.status(entry.panel, this.labels.loading);
            try {
                const data = await this.request(new URLSearchParams({
                    action: 'iro_shuoshuo_comments', post_id: entry.id, comment_page: page
                }));
                if (this.active !== entry || !this.root.isConnected) return;
                const template = document.createElement('template');
                template.innerHTML = data.html;
                const oldThread = entry.panel.querySelector('.iro-shuoshuo-comment-thread');
                const newThread = template.content.querySelector('.iro-shuoshuo-comment-thread');
                const oldForm = entry.panel.querySelector('.iro-shuoshuo-form');
                const newForm = template.content.querySelector('.iro-shuoshuo-form');
                if (listOnly && oldThread && newThread && Boolean(oldForm) === Boolean(newForm)) {
                    oldThread.replaceWith(newThread);
                    // Refresh the nonce without disturbing the current form or captcha.
                    if (oldForm && newForm) oldForm.elements.namedItem('sakurairo_comment_nonce').value = newForm.elements.namedItem('sakurairo_comment_nonce').value;
                } else {
                    this.remember(entry);
                    this.removeCaptcha(entry);
                    entry.panel.replaceChildren(template.content);
                    this.restore(entry);
                    this.initCaptcha(entry);
                }
                entry.page = data.page;
                entry.button.querySelector('[data-iro-count]').textContent = data.count;
                this.status(entry.panel, '');
                // Comment images are inserted after the theme's initial lazyload pass.
                for (const img of entry.panel.querySelectorAll('img[data-src]')) {
                    img.loading = 'lazy';
                    img.src = img.dataset.src;
                    if (img.dataset.srcset) img.srcset = img.dataset.srcset;
                    img.classList.remove('lazyload');
                }
                return true;
            } catch (error) {
                if (this.active !== entry || !this.root.isConnected) return;
                this.status(entry.panel, error.message || this.labels.failed, true);
                const retry = document.createElement('button');
                retry.type = 'button';
                retry.dataset.iroCommentPage = page;
                retry.textContent = this.labels.retry;
                entry.panel.querySelector('[data-iro-form-status]').append(' ', retry);
                return false;
            } finally {
                entry.loading = false;
                entry.panel.removeAttribute('aria-busy');
            }
        }

        async refreshCaptcha(entry) {
            const region = entry.panel.querySelector('[data-iro-captcha]');
            if (!region || entry.captchaLoading) return;
            entry.captchaLoading = true;
            const form = entry.panel.querySelector('.iro-shuoshuo-form');
            const button = region.querySelector('[data-iro-captcha-refresh]');
            if (button) button.disabled = true;
            const controller = new AbortController();
            this.requests.add(controller);
            const timer = setTimeout(() => controller.abort(), 15000);
            try {
                const response = await fetch(region.dataset.iroCaptcha, {credentials: 'same-origin', cache: 'no-store', signal: controller.signal});
                const data = await response.json();
                if (!response.ok || !data.data || !data.id || !data.time) throw new Error('captcha');
                if (this.active !== entry || !region.isConnected) return;
                region.querySelector('[data-iro-captcha-image]').src = data.data;
                form.elements.namedItem('timestamp').value = data.time;
                form.elements.namedItem('id').value = data.id;
                form.elements.namedItem('captcha').value = '';
            } catch (_) {
                if (this.active === entry) this.status(entry.panel, this.labels.captchaError, true);
            } finally {
                entry.captchaLoading = false;
                if (button) button.disabled = false;
                clearTimeout(timer);
                this.requests.delete(controller);
            }
        }

        async initCaptcha(entry) {
            this.refreshCaptcha(entry);
            const element = entry.panel.querySelector('[data-iro-turnstile]');
            if (!element) return;
            try {
                const api = await loadTurnstile();
                if (this.active !== entry || !element.isConnected) return;
                const input = entry.panel.querySelector('[name="cf-turnstile-response"]');
                entry.widget = api.render(element, {
                    sitekey: element.dataset.sitekey, theme: 'auto', 'response-field': false,
                    callback: token => { input.value = token; },
                    'expired-callback': () => { input.value = ''; },
                    'error-callback': () => {
                        input.value = '';
                        this.status(entry.panel, this.labels.captchaError, true);
                    }
                });
            } catch (_) {
                if (this.active === entry) this.status(entry.panel, this.labels.captchaError, true);
            }
        }

        click(event) {
            const toggle = event.target.closest('[data-iro-comments]');
            if (toggle) {
                if (this.active?.submitting) return;
                const same = this.active?.button === toggle;
                this.close();
                if (same) return;
                const panel = this.root.querySelector('#' + toggle.getAttribute('aria-controls'));
                const entry = {id: toggle.dataset.iroComments, button: toggle, panel, page: 1};
                this.active = entry;
                panel.hidden = false;
                toggle.setAttribute('aria-expanded', 'true');
                this.load(entry);
                return;
            }
            const entry = this.active;
            if (!entry || entry.submitting) return;
            const pager = event.target.closest('[data-iro-comment-page]');
            if (pager) {
                event.preventDefault();
                // A retry button is removed immediately; keep theme pagination handlers from reading it afterwards.
                event.stopPropagation();
                this.load(entry, Number(pager.dataset.iroCommentPage), true);
            }
            const reply = event.target.closest('[data-iro-reply]');
            const cancel = event.target.closest('[data-iro-cancel-reply]');
            if (reply || cancel) {
                event.preventDefault();
                const form = entry.panel.querySelector('.iro-shuoshuo-form');
                if (!form) return;
                form.elements.namedItem('comment_parent').value = reply ? reply.dataset.iroReply : '0';
                const status = entry.panel.querySelector('[data-iro-reply-status]');
                status.hidden = !reply;
                status.querySelector('[data-iro-reply-name]').textContent = reply ? this.labels.replyTo + reply.dataset.iroReplyAuthor : '';
                form.elements.namedItem('comment').focus();
            }
            if (event.target.closest('[data-iro-captcha-refresh]')) {
                event.preventDefault();
                this.refreshCaptcha(entry);
            }
        }

        async submit(event) {
            const form = event.target.closest('.iro-shuoshuo-form');
            if (!form) return;
            event.preventDefault();
            const entry = this.active;
            if (!entry || entry.submitting || entry.loading) return;
            const fields = new FormData(form);
            fields.set('action', 'iro_shuoshuo_comment');
            fields.set('post_id', entry.id);
            const submit = form.querySelector('[type="submit"]');
            const textarea = form.elements.namedItem('comment');
            entry.submitting = true;
            submit.disabled = true;
            textarea.readOnly = true;
            this.status(entry.panel, this.labels.submitting);
            try {
                const result = await this.request(fields, true);
                if (!this.root.isConnected) return;
                textarea.value = '';
                form.elements.namedItem('comment_parent').value = '0';
                entry.panel.querySelector('[data-iro-reply-status]').hidden = true;
                this.drafts.delete(entry.id);
                entry.button.querySelector('[data-iro-count]').textContent = result.count;
                const refreshed = await this.load(entry, 1, true);
                if (refreshed) this.status(entry.panel, result.message);
                else entry.panel.querySelector('[data-iro-form-status]')?.prepend(result.message + ' ');
            } catch (error) {
                if (this.root.isConnected) this.status(entry.panel, error.message || this.labels.submitFailed, true);
            } finally {
                entry.submitting = false;
                submit.disabled = false;
                textarea.readOnly = false;
                if (this.active === entry && this.root.isConnected) {
                    this.refreshCaptcha(entry);
                    if (entry.widget !== undefined && window.turnstile) {
                        entry.panel.querySelector('[name="cf-turnstile-response"]').value = '';
                        window.turnstile.reset(entry.widget);
                    }
                }
            }
        }

        destroy() {
            this.close();
            for (const request of this.requests) request.abort();
            this.root.removeEventListener('click', this.click);
            this.root.removeEventListener('submit', this.submit);
            this.drafts.clear();
        }
    }

    function init() {
        const root = document.querySelector('.iro-shuoshuo-feed');
        if (current?.root === root) return;
        if (current) current.destroy();
        current = root ? new Feed(root) : null;
    }

    document.addEventListener('pjax:complete', init);
    document.addEventListener('pjax:send', () => {
        if (!current) return;
        const status = current.root.querySelector('[data-iro-feed-status]');
        if (status) {
            status.textContent = current.labels.navigating;
            status.classList.remove('is-error');
            status.hidden = false;
        }
    });
    document.addEventListener('pjax:error', () => {
        if (!current) return;
        const status = current.root.querySelector('[data-iro-feed-status]');
        if (status) {
            status.textContent = current.labels.navigateError;
            status.classList.add('is-error');
            status.hidden = false;
        }
    });
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, {once: true});
    else init();
})();
