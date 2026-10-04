const refreshButton = document.querySelector('[data-reload-page]');

if (refreshButton instanceof HTMLButtonElement) {
    refreshButton.addEventListener('click', () => {
        window.location.reload();
    });
    refreshButton.hidden = false;
}
