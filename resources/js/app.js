document.addEventListener('alpine:init', () => {
    Alpine.data('clipboardItemFast', (textToCopy) => ({
        copied: false,
        copy() {
            navigator.clipboard.writeText(textToCopy).then(() => {
                this.copied = true;
                setTimeout(() => this.copied = false, 500); 
            }).catch(err => {
                console.error('Error al copiar: ', err);
                // Opcionalmente, muestra un mensaje de error al usuario
            });
        }
    }));
});