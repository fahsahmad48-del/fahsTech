(() => {
    const bar = document.getElementById('offerBar');
    if (!bar) return;

    const track = bar.querySelector('.offer-track');
    const viewport = bar.querySelector('.offer-window');
    const pause = document.getElementById('offerPause');
    if (!track || !viewport || !pause) return;

    // Blade supplies escaped settings text; no extra request or HTML insertion is needed.
    const updateSpeed = () => {
        const distance = track.scrollWidth / 2;
        bar.style.setProperty('--offer-duration', `${Math.max(18, distance / 40)}s`);
    };
    pause.hidden = false;
    pause.addEventListener('click', () => {
        const paused = pause.getAttribute('aria-pressed') !== 'true';
        pause.setAttribute('aria-pressed', String(paused));
        pause.setAttribute('aria-label', paused ? 'Resume announcement animation' : 'Pause announcement animation');
        pause.title = pause.getAttribute('aria-label');
        bar.dataset.paused = String(paused);
    });

    updateSpeed();
    if ('ResizeObserver' in globalThis) {
        new ResizeObserver(updateSpeed).observe(viewport);
    }
    document.fonts?.ready.then(updateSpeed);
})();
