const registerClipboardItemFast = () => {
    if (!window.Alpine || window.__clipboardItemFastRegistered) {
        return;
    }

    window.__clipboardItemFastRegistered = true;

    window.Alpine.data('clipboardItemFast', (textToCopy) => ({
        copied: false,
        copy() {
            const value = String(textToCopy ?? '');

            const finish = (wasCopied) => {
                if (!wasCopied) {
                    console.error('Error al copiar: no se pudo acceder al portapapeles');
                    return;
                }

                this.copied = true;
                setTimeout(() => {
                    this.copied = false;
                }, 500);
            };

            const fallbackCopy = () => {
                try {
                    const textArea = document.createElement('textarea');
                    textArea.value = value;
                    textArea.setAttribute('readonly', '');
                    textArea.style.position = 'fixed';
                    textArea.style.opacity = '0';
                    textArea.style.left = '-9999px';
                    document.body.appendChild(textArea);
                    textArea.focus();
                    textArea.select();
                    textArea.setSelectionRange(0, textArea.value.length);
                    const copied = document.execCommand('copy');
                    document.body.removeChild(textArea);

                    return copied;
                } catch (error) {
                    return false;
                }
            };

            if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                navigator.clipboard.writeText(value)
                    .then(() => finish(true))
                    .catch(() => finish(fallbackCopy()));

                return;
            }

            finish(fallbackCopy());
        },
    }));
};

document.addEventListener('alpine:init', registerClipboardItemFast);
registerClipboardItemFast();