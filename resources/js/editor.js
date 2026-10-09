// TinyMCE bundled per tiny.cloud's "Bundling with Vite" guide: the global first, then theme, model, icons, skins, plugins.
import tinymce from 'tinymce';
import 'tinymce/icons/default/icons.min.js';
import 'tinymce/themes/silver/theme.min.js';
import 'tinymce/models/dom/model.min.js';
import 'tinymce/skins/ui/oxide/skin.js';
import 'tinymce/skins/ui/oxide/content.js';
import 'tinymce/skins/ui/oxide-dark/skin.js';
import 'tinymce/skins/ui/oxide-dark/content.js';

import 'tinymce/plugins/advlist';
import 'tinymce/plugins/anchor';
import 'tinymce/plugins/autolink';
import 'tinymce/plugins/charmap';
import 'tinymce/plugins/code';
import 'tinymce/plugins/codesample';
import 'tinymce/plugins/fullscreen';
import 'tinymce/plugins/help';
import 'tinymce/plugins/help/js/i18n/keynav/en.js';
import 'tinymce/plugins/image';
import 'tinymce/plugins/insertdatetime';
import 'tinymce/plugins/link';
import 'tinymce/plugins/lists';
import 'tinymce/plugins/media';
import 'tinymce/plugins/preview';
import 'tinymce/plugins/save';
import 'tinymce/plugins/searchreplace';
import 'tinymce/plugins/table';
import 'tinymce/plugins/visualblocks';
import 'tinymce/plugins/wordcount';

/**
 * Mount the editor on every textarea[data-editor]. Its data-content-css is the site stylesheet,
 * so content looks as it will on the page. The save plugin (and Ctrl+S) submits the surrounding form.
 */
export function mountEditors() {
    const theme = document.documentElement.dataset.bsTheme === 'dark' ? 'dark' : 'light';

    document.querySelectorAll('textarea[data-editor]').forEach((textarea) => {
        tinymce.init({
            target: textarea,
            license_key: 'gpl',
            promotion: false,
            height: 600,
            skin: theme === 'dark' ? 'oxide-dark' : 'oxide',
            skin_url: 'default',
            content_css: textarea.dataset.contentCss ? [textarea.dataset.contentCss] : false,
            content_style: 'body { padding: 1rem; }'
                + ' wk-secret { display: inline-block; padding: 0 .4em; border: 1px dashed currentColor; border-radius: .3em;'
                + ' opacity: .8; cursor: default; white-space: nowrap; }'
                + ' wk-secret::before { content: "\\1F512\\00A0"; }',
            plugins: [
                'advlist', 'autolink', 'lists', 'link', 'image', 'media', 'charmap', 'preview', 'anchor',
                'searchreplace', 'visualblocks', 'code', 'fullscreen', 'insertdatetime', 'table', 'help',
                'wordcount', 'save', 'codesample',
            ],
            codesample_languages: [
                { text: 'Plain text', value: 'none' },
                { text: 'HTML/XML', value: 'markup' },
                { text: 'CSS', value: 'css' },
                { text: 'SCSS', value: 'scss' },
                { text: 'JavaScript', value: 'javascript' },
                { text: 'JSON', value: 'json' },
                { text: 'PHP', value: 'php' },
                { text: 'Python', value: 'python' },
                { text: 'SQL', value: 'sql' },
                { text: 'Bash', value: 'bash' },
                { text: 'Batch', value: 'batch' },
                { text: 'C', value: 'c' },
                { text: 'C++', value: 'cpp' },
                { text: 'Java', value: 'java' },
                { text: 'Arduino', value: 'arduino' },
            ],
            toolbar: 'save wikiPage wikiSecret | bold italic strikethrough | blocks | alignleft aligncenter alignright alignjustify'
                + ' | bullist numlist outdent indent | link image media table codesample | removeformat code fullscreen',
            menubar: 'edit view insert format table tools help',
            contextmenu: false,
            statusbar: false,
            image_advtab: true,
            image_uploadtab: true,
            automatic_uploads: true,
            images_upload_handler: (blobInfo, progress) => uploadImage(textarea.dataset.uploadUrl, blobInfo, progress),
            file_picker_types: 'file image',
            file_picker_callback: (callback, value, meta) => pickMedia(textarea.dataset.mediaUrl, callback, meta),
            extended_valid_elements: 'i[*],em[*],b[*],a[*],div[*],span[*],img[*],wk-secret[data-id|contenteditable]',
            custom_elements: '~wk-secret',
            relative_urls: false,
            remove_script_host: true,
            save_enablewhendirty: false,
            save_onsavecallback: (editor) => editor.formElement?.requestSubmit(),
            setup: (editor) => {
                editor.ui.registry.addButton('wikiPage', {
                    icon: 'addtag',
                    tooltip: 'Link to a wiki page',
                    onAction: () => pickPage(editor, textarea.dataset.pagePickerUrl),
                });
                addPageAutocompleter(editor, textarea.dataset.pageSuggestUrl, textarea.dataset.pageSlugUrl);
                if (textarea.dataset.secretPickerUrl) {
                    addSecretButton(editor, textarea.dataset.secretPickerUrl, textarea.dataset.secretEditUrl);
                }
                editor.on('PreInit', () => {
                    editor.getDoc().documentElement.dataset.bsTheme = theme;
                });
            },
        });
    });
}

/**
 * wikiSecret: pick (or create) a secret and embed it as <wk-secret data-id>, which the page view
 * replaces with the secret. Double-click a placeholder to edit that secret in a new tab.
 */
function addSecretButton(editor, pickerUrl, editBaseUrl) {
    editor.ui.registry.addButton('wikiSecret', {
        icon: 'lock',
        tooltip: 'Insert a secret',
        onAction: () => editor.windowManager.openUrl({
            title: 'Insert a secret',
            url: new URL(pickerUrl, window.location.href).toString(),
            width: Math.min(900, window.innerWidth - 40),
            height: Math.min(700, window.innerHeight - 120),
            onMessage: (api, data) => {
                if (data.mceAction !== 'wicketSecretPick') return;
                editor.insertContent(editor.dom.createHTML('wk-secret', { 'data-id': String(data.id), contenteditable: 'false' }, editor.dom.encode(data.name)) + '&nbsp;');
                api.close();
            },
        }),
    });

    editor.on('dblclick', (e) => {
        const placeholder = e.target.closest?.('wk-secret[data-id]');
        if (placeholder) window.open(`${editBaseUrl}/${encodeURIComponent(placeholder.dataset.id)}/edit`, '_blank');
    });
}

/** Link HTML for a wiki page. */
function pageLink(editor, slug, title) {
    return editor.dom.createHTML('a', { href: '/' + slug, class: 'wk-page' }, editor.dom.encode(title));
}

/**
 * `[[` + text suggests pages as you type (TinyMCE positions the menu and handles the keys).
 * The last row links to a new page with the typed title unless a page already has that title.
 */
function addPageAutocompleter(editor, suggestUrl, slugUrl) {
    const getJson = (url, params) => {
        const u = new URL(url, window.location.href);
        Object.entries(params).forEach(([k, v]) => u.searchParams.set(k, v));
        return fetch(u, { headers: { Accept: 'application/json' } }).then((r) => (r.ok ? r.json() : Promise.reject(r.status)));
    };

    editor.ui.registry.addAutocompleter('wikiPageLink', {
        trigger: '[[',
        minChars: 1,
        columns: 1,
        fetch: (pattern) => {
            const typed = pattern.trim();
            return getJson(suggestUrl, { q: typed }).then((pages) => {
                const items = pages.map((p) => ({ type: 'autocompleteitem', value: 'page:' + p.slug + ':' + p.title, text: p.title, icon: 'link' }));
                if (typed && ! pages.some((p) => p.title.toLowerCase() === typed.toLowerCase())) {
                    items.push({ type: 'autocompleteitem', value: 'new:' + typed, text: `Create page: ${typed}`, icon: 'plus' });
                }
                return items;
            }).catch(() => []);
        },
        onAction: (api, rng, value) => {
            api.hide();
            const insert = (slug, title) => {
                editor.selection.setRng(rng);
                editor.insertContent(pageLink(editor, slug, title));
                // Leave the caret after the link, not inside it.
                const link = editor.selection.getNode().closest('a.wk-page');
                if (link) {
                    editor.selection.select(link);
                    editor.selection.collapse(false);
                }
            };

            if (value.startsWith('page:')) {
                const [, slug, ...title] = value.split(':');
                insert(slug, title.join(':'));
            } else {
                const title = value.slice(4);
                getJson(slugUrl, { title }).then(({ slug }) => insert(slug, title));
            }
        },
    });
}

/**
 * Open the page picker. It posts { mceAction: 'wicketPagePick', slug, title }; the link wraps the
 * selection, or is inserted with the page title as its text.
 */
function pickPage(editor, pickerUrl) {
    const url = new URL(pickerUrl, window.location.href);
    const selected = editor.selection.getContent({ format: 'text' }).trim();
    if (selected) url.searchParams.set('q', selected);

    editor.windowManager.openUrl({
        title: 'Link to a page',
        url: url.toString(),
        width: Math.min(900, window.innerWidth - 40),
        height: Math.min(640, window.innerHeight - 120),
        onMessage: (api, data) => {
            if (data.mceAction !== 'wicketPagePick') return;
            const href = '/' + data.slug;

            if (editor.selection.isCollapsed()) {
                editor.insertContent(pageLink(editor, data.slug, data.title));
            } else {
                editor.execCommand('mceInsertLink', false, { href, class: 'wk-page' });
            }
            api.close();
        },
    });
}

/**
 * Open the media browser in picker mode in a TinyMCE dialog. Its Select buttons post
 * { mceAction: 'wicketMediaPick', url, name }, which fills the dialog field that asked.
 */
function pickMedia(mediaUrl, callback, meta) {
    const isImage = meta.filetype === 'image';
    const url = new URL(mediaUrl, window.location.href);
    url.searchParams.set('picker', isImage ? 'image' : 'file');

    tinymce.activeEditor.windowManager.openUrl({
        title: isImage ? 'Choose an image' : 'Choose a file',
        url: url.toString(),
        width: Math.min(1100, window.innerWidth - 40),
        height: Math.min(700, window.innerHeight - 120),
        onMessage: (api, data) => {
            if (data.mceAction !== 'wicketMediaPick') return;
            callback(data.url, isImage ? { alt: '' } : { text: data.name, title: data.name });
            api.close();
        },
    });
}

/** POST a pasted, dropped or chosen image to the media endpoint; resolves with its URL. */
function uploadImage(url, blobInfo, progress) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', url);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name=csrf-token]').content);
        xhr.upload.onprogress = (e) => progress((e.loaded / e.total) * 100);
        xhr.onerror = () => reject({ message: 'Upload failed: network error.', remove: true });
        xhr.onload = () => {
            let json = {};
            try {
                json = JSON.parse(xhr.responseText);
            } catch {
                // fall through to the status message
            }
            if (xhr.status === 200 && json.location) {
                resolve(json.location);
            } else {
                const message = json.errors?.file?.[0] ?? json.message ?? `HTTP ${xhr.status}`;
                reject({ message: `Upload failed: ${message}`, remove: true });
            }
        };

        const data = new FormData();
        data.append('file', blobInfo.blob(), blobInfo.filename());
        xhr.send(data);
    });
}

mountEditors();
