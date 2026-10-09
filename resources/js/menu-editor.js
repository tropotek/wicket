import htmx from 'htmx.org';
import Sortable from 'sortablejs';

// Every list shares one group; nested (dropdown) lists refuse dropdowns, keeping the menu two levels deep.
htmx.onLoad((root) => {
    root.querySelectorAll('[data-menu-list]').forEach((list) => {
        if (list.sortable) return;

        list.sortable = Sortable.create(list, {
            group: {
                name: 'menu',
                put: 'nested' in list.dataset ? (to, from, dragged) => dragged.dataset.type !== 'dropdown' : true,
            },
            handle: '.menu-handle',
            animation: 150,
            fallbackOnBody: true,
            swapThreshold: 0.65,
            emptyInsertThreshold: 10,
            onEnd: (evt) => {
                if (evt.from !== evt.to || evt.oldIndex !== evt.newIndex) save();
            },
        });
    });
});

/** Post every row in display order with its parent dropdown (blank at the top level). */
function save() {
    const root = document.querySelector('[data-menu-root]');
    const values = { 'order[]': [] };

    root.querySelectorAll('li[data-id]').forEach((li) => {
        const parent = li.parentElement.closest('li[data-id]');
        values['order[]'].push(li.dataset.id);
        values[`parent[${li.dataset.id}]`] = parent ? parent.dataset.id : '';
    });

    htmx.ajax('PUT', root.dataset.orderUrl, { source: root, target: '#menu-editor', swap: 'outerHTML', values });
}
