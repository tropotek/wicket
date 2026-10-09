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
            content_style: 'body { padding: 1rem; }',
            plugins: [
                'advlist', 'autolink', 'lists', 'link', 'image', 'media', 'charmap', 'preview', 'anchor',
                'searchreplace', 'visualblocks', 'code', 'fullscreen', 'insertdatetime', 'table', 'help',
                'wordcount', 'save',
            ],
            toolbar: 'save | bold italic strikethrough | blocks | alignleft aligncenter alignright alignjustify'
                + ' | bullist numlist outdent indent | link image media table | removeformat code fullscreen',
            menubar: 'edit view insert format table tools help',
            contextmenu: false,
            statusbar: false,
            image_advtab: true,
            extended_valid_elements: 'i[*],em[*],b[*],a[*],div[*],span[*],img[*]',
            relative_urls: false,
            remove_script_host: true,
            save_enablewhendirty: false,
            save_onsavecallback: (editor) => editor.formElement?.requestSubmit(),
            setup: (editor) => {
                editor.on('PreInit', () => {
                    editor.getDoc().documentElement.dataset.bsTheme = theme;
                });
            },
        });
    });
}

mountEditors();
