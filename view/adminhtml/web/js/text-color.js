(function () {
    'use strict';

    var LABELS = {
        button: 'Text color',
        remove: 'Remove color'
    };

    // Same validation pattern as the native color field (default_design.json).
    var COLOR_PATTERN = /^#?[a-zA-Z0-9,().\-\s%]+$/;

    function isValidColor(value) {
        return typeof value === 'string' && value.trim() !== '' && COLOR_PATTERN.test(value.trim());
    }

    var ICON =
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
        'stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
        '<path d="M4 20h16"/><path d="M7 16l5-11 5 11"/><path d="M9 12h6"/></svg>';

    function buildMark(Mark) {
        return Mark.create({
            name: 'textColor',
            addAttributes: function () {
                return {
                    color: {
                        default: null,
                        parseHTML: function (el) { return el.style.color || null; },
                        renderHTML: function (attrs) {
                            return attrs.color ? { style: 'color: ' + attrs.color } : {};
                        }
                    }
                };
            },
            parseHTML: function () {
                return [{
                    tag: 'span',
                    getAttrs: function (el) { return (el.style && el.style.color) ? {} : false; }
                }];
            },
            renderHTML: function (props) {
                return ['span', props.HTMLAttributes, 0];
            },
            addCommands: function () {
                return {
                    setTextColor: function (color) {
                        return function (ctx) {
                            if (!isValidColor(color)) { return false; }
                            return ctx.chain().setMark('textColor', { color: color.trim() }).run();
                        };
                    },
                    unsetTextColor: function () {
                        return function (ctx) { return ctx.chain().unsetMark('textColor').run(); };
                    }
                };
            }
        });
    }

    function addColorButton(toolbar, editor) {
        if (!toolbar || toolbar.querySelector('[data-blackbird-textcolor]')) { return; }

        var wrap = document.createElement('span');
        wrap.style.cssText = 'position:relative;display:inline-flex;';

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'hyva-tiptap-btn';
        btn.setAttribute('data-blackbird-textcolor', '1');
        btn.title = LABELS.button;
        btn.innerHTML = ICON;

        var pop = document.createElement('div');
        pop.style.cssText =
            'position:absolute;z-index:50;top:100%;left:0;margin-top:4px;padding:8px;background:#fff;' +
            'border:1px solid #d9e2e8;border-radius:6px;box-shadow:0 4px 16px rgba(0,0,0,.15);' +
            'display:none;';

        var picker = document.createElement('input');
        picker.type = 'color';
        picker.style.cssText = 'width:56px;height:32px;padding:0;border:none;background:none;cursor:pointer;';
        picker.addEventListener('change', function () { apply(picker.value); });

        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn';
        remove.style.cssText = 'width:100%;margin-top:8px;font-size:12px;';
        remove.textContent = LABELS.remove;
        remove.addEventListener('mousedown', function (e) {
            e.preventDefault();
            editor.chain().focus().unsetTextColor().run();
            close();
        });

        pop.appendChild(picker);
        pop.appendChild(remove);

        function apply(value) {
            if (!isValidColor(value)) { return; }
            editor.chain().focus().setTextColor(value).run();
            close();
        }
        function open() {
            pop.style.display = 'block';
            document.addEventListener('mousedown', onDocDown, true);
        }
        function close() {
            pop.style.display = 'none';
            document.removeEventListener('mousedown', onDocDown, true);
        }
        function onDocDown(e) { if (!wrap.contains(e.target)) { close(); } }

        btn.addEventListener('mousedown', function (e) {
            e.preventDefault();
            if (pop.style.display === 'block') { close(); } else { open(); }
        });

        wrap.appendChild(btn);
        wrap.appendChild(pop);

        // Anchored on the first separator: the Strikethrough button's only handle is a translatable title.
        var separator = toolbar.querySelector('.hyva-tiptap-sep');
        if (separator) {
            toolbar.insertBefore(wrap, separator);
        } else {
            toolbar.appendChild(wrap);
        }
    }

    // The Hyvä TipTap bundle exports no Mark base class, hence the throwaway probe editor.
    var markPromise = null;

    function ensureTextColorMark() {
        if (markPromise) { return markPromise; }
        markPromise = (async function () {
            var mod = await import(window.hyvaTiptapBundleUrl);
            var element = document.createElement('div');
            var probe = new mod.Editor({ element: element, extensions: [mod.StarterKit] });
            var markExtension = probe.extensionManager.extensions.find(function (extension) {
                return extension.type === 'mark';
            });
            var MarkClass = markExtension && markExtension.constructor;
            probe.destroy();
            element.remove();
            if (!MarkClass) { throw new Error('TipTap Mark base class not found'); }
            return buildMark(MarkClass);
        })().catch(function (e) {
            // Reset so a transient bundle-load failure can be retried on the next session.
            markPromise = null;
            throw e;
        });
        return markPromise;
    }

    // Not hyvaTiptapUtility.extend(): the mark is async, extend() would let a session start without it.
    function patchUtility(util) {
        if (!util || util.blackbirdTextColorPatched) { return; }
        util.blackbirdTextColorPatched = true;

        var originalCreateSession = util.createSession;
        util.createSession = function (config) {
            config = config || {};
            return (async function () {
                var mark = null;
                try {
                    mark = await ensureTextColorMark();
                } catch (e) {
                    console.error('[Blackbird textColor] mark init failed', e);
                }

                var sessionConfig = mark
                    ? Object.assign({}, config, { extensions: (config.extensions || []).concat([mark]) })
                    : config;

                var session = await originalCreateSession.call(util, sessionConfig);

                if (mark && config.toolbarContainer && session && session.editor) {
                    try {
                        addColorButton(config.toolbarContainer, session.editor);
                    } catch (e) {
                        console.error('[Blackbird textColor] toolbar button failed', e);
                    }
                }

                return session;
            })();
        };
    }

    if (window.hyvaTiptapUtility) {
        patchUtility(window.hyvaTiptapUtility);
    } else {
        var pending;
        Object.defineProperty(window, 'hyvaTiptapUtility', {
            configurable: true,
            get: function () { return pending; },
            set: function (value) { pending = value; patchUtility(value); }
        });
    }
})();
