/**
 * <x-secret-card>: fetches a secret's protected values only when asked (POST, so they stay out of
 * URLs and caches) and keeps them in memory. The password hides itself again after 30 s; a shown
 * 2FA code refreshes when its period runs out.
 */
export default (revealUrl) => ({
    values: null,
    busy: false,
    error: '',
    showPassword: false,
    showNotes: false,
    otp: null,
    copied: '',
    timers: {},

    async load(force = false) {
        if (this.values && ! force) return this.values;
        this.busy = true;
        this.error = '';
        try {
            const response = await fetch(revealUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
            });
            if (! response.ok) throw new Error(response.status === 429 ? 'Too many requests. Wait a minute.' : `Could not load (${response.status}).`);
            this.values = await response.json();
            return this.values;
        } catch (e) {
            this.error = e.message;
            throw e;
        } finally {
            this.busy = false;
        }
    },

    /** Showing the password copies it too. */
    async togglePassword() {
        clearTimeout(this.timers.password);
        if (this.showPassword) {
            this.showPassword = false;
            return;
        }
        await this.load();
        this.showPassword = true;
        this.timers.password = setTimeout(() => (this.showPassword = false), 30000);
        await this.copy('password');
    },

    async toggleNotes() {
        if (! this.showNotes) await this.load();
        this.showNotes = ! this.showNotes;
    },

    async toggleOtp() {
        if (this.otp) {
            clearInterval(this.timers.otp);
            this.otp = null;
            return;
        }
        await this.showOtp();
    },

    async showOtp() {
        clearInterval(this.timers.otp);
        await this.refreshOtp();
        this.timers.otp = setInterval(() => {
            if (! this.otp) return;
            this.otp.remaining -= 1;
            if (this.otp.remaining <= 0) this.refreshOtp();
        }, 1000);
    },

    async refreshOtp() {
        const values = await this.load(true);
        this.otp = values.otp ? { ...values.otp } : null;
    },

    /** Dash offset for the countdown ring (circumference 2πr, r = 8). */
    ringOffset() {
        const c = 2 * Math.PI * 8;
        return this.otp ? c * (1 - this.otp.remaining / this.otp.period) : c;
    },

    /** Copies a value, fetching it first if it is not held. The 2FA code is shown as it is copied. */
    async copy(what, text) {
        try {
            if (what === 'otp') {
                if (! this.otp) await this.showOtp();
                text = this.otp?.code;
            } else if (text === undefined) {
                text = (await this.load())[what];
            }
            if (! text) throw new Error('nothing to copy');
            await copyText(text);
            // Cleared first, so copying the same line again replays its flash.
            this.copied = '';
            await this.$nextTick();
            this.copied = what;
            clearTimeout(this.timers.copied);
            this.timers.copied = setTimeout(() => (this.copied = ''), 1500);
        } catch {
            this.error ||= 'Copy failed.';
        }
    },

    destroy() {
        Object.values(this.timers).forEach((t) => { clearTimeout(t); clearInterval(t); });
    },
});

/** Clipboard API where allowed (https / localhost), otherwise the old textarea trick. */
async function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
        return navigator.clipboard.writeText(text);
    }
    const area = Object.assign(document.createElement('textarea'), { value: text });
    area.style.cssText = 'position:fixed;opacity:0';
    document.body.append(area);
    area.select();
    const ok = document.execCommand('copy');
    area.remove();
    if (! ok) throw new Error('copy failed');
}
