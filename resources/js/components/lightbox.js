/**
 * Page image viewer: put x-data="lightbox" on the content wrapper. Clicking an a.wk-lightbox
 * opens its image full-screen; arrows step through the page's images, Esc or the backdrop closes.
 */
export default () => ({
    images: [],
    index: -1,
    opener: null,

    get current() {
        return this.images[this.index] ?? null;
    },

    open(event) {
        const link = event.target.closest('a.wk-lightbox');
        if (! link || ! this.$el.contains(link)) return;
        event.preventDefault();

        const links = [...this.$el.querySelectorAll('a.wk-lightbox')];
        this.images = links.map((a) => ({ src: a.getAttribute('href'), alt: a.querySelector('img')?.getAttribute('alt') ?? '' }));
        this.index = links.indexOf(link);
        this.opener = link;
        document.body.style.overflow = 'hidden';
        this.$nextTick(() => this.$refs.close?.focus());
    },

    close() {
        if (this.index < 0) return;
        this.index = -1;
        document.body.style.overflow = '';
        this.opener?.focus();
    },

    step(by) {
        if (this.index < 0 || this.images.length < 2) return;
        this.index = (this.index + by + this.images.length) % this.images.length;
    },
});
