(function () {
    // audio-player.js - isolated file so it can be removed later
    const AUDIO_EXT = new Set(['mp3', 'wav', 'flac', 'ogg', 'm4a', 'webm']);

    // minimal ID3v2 parser to extract TIT2 and APIC frames (best-effort)
    function readSynchsafeInt(view, offset) {
        return (view.getUint8(offset) & 0x7f) << 21 |
            (view.getUint8(offset + 1) & 0x7f) << 14 |
            (view.getUint8(offset + 2) & 0x7f) << 7 |
            (view.getUint8(offset + 3) & 0x7f);
    }

    function readUint32(view, offset) {
        return view.getUint32(offset, false);
    }

    function decodeString(bytes, encoding) {
        try {
            if (encoding === 0) return new TextDecoder('iso-8859-1').decode(bytes);
            if (encoding === 1) return new TextDecoder('utf-16').decode(bytes);
            if (encoding === 2) return new TextDecoder('utf-16be').decode(bytes);
            return new TextDecoder('utf-8').decode(bytes);
        } catch (e) {
            return '';
        }
    }

    function parseID3v2(buffer) {
        const view = new DataView(buffer);
        if (view.byteLength < 10) return {};
        if (String.fromCharCode(view.getUint8(0), view.getUint8(1), view.getUint8(2)) !== 'ID3') return {};
        const ver = view.getUint8(3);
        const flags = view.getUint8(5);
        const tagSize = readSynchsafeInt(view, 6);
        let offset = 10;
        const end = 10 + tagSize;
        const result = {};

        while (offset + 10 <= Math.min(end, view.byteLength)) {
            const id = String.fromCharCode(
                view.getUint8(offset), view.getUint8(offset + 1), view.getUint8(offset + 2), view.getUint8(offset + 3)
            );
            let frameSize = 0;
            if (ver >= 4) frameSize = readSynchsafeInt(view, offset + 4);
            else frameSize = readUint32(view, offset + 4);
            const frameFlags = view.getUint16(offset + 8, false);
            offset += 10;
            if (frameSize <= 0 || offset + frameSize > view.byteLength) break;

            try {
                if (id === 'TIT2') {
                    const encoding = view.getUint8(offset);
                    const bytes = new Uint8Array(buffer, offset + 1, frameSize - 1);
                    result.title = decodeString(bytes, encoding);
                }

                if (id === 'APIC') {
                    const enc = view.getUint8(offset);
                    let p = offset + 1;
                    let mime = '';
                    while (p < offset + frameSize && view.getUint8(p) !== 0) {
                        mime += String.fromCharCode(view.getUint8(p));
                        p++;
                    }
                    p++; // skip 0
                    const picType = view.getUint8(p); p++;
                    while (p < offset + frameSize && view.getUint8(p) !== 0) p++;
                    p++;
                    const imgStart = p;
                    const imgLen = offset + frameSize - imgStart;
                    if (imgLen > 0) {
                        const imgBytes = new Uint8Array(buffer, imgStart, imgLen);
                        result.image = { mime: mime || 'image/jpeg', data: imgBytes };
                    }
                }
            } catch (e) {
                // ignore individual frame errors
            }

            offset += frameSize;
        }

        return result;
    }

    // Utility: check for an existing image with same base name
    async function findCoverFor(src) {
        try {
            const base = src.replace(/\.[^.?#]+(\?.*)?$/, '');
            const exts = ['jpg', 'png', 'webp', 'jpeg'];
            for (const e of exts) {
                const url = base + '.' + e;
                const resp = await fetch(url, { method: 'HEAD' });
                if (resp.ok) return url;
            }
        } catch (err) {
            // ignore
        }
        return null;
    }

    // Build a shared audio element to keep playback in background and show native controls
    const globalAudio = document.createElement('audio');
    globalAudio.id = 'globalAudio';
    globalAudio.preload = 'metadata';
    globalAudio.crossOrigin = 'anonymous';
    globalAudio.controls = true;
    globalAudio.style.width = '100%';
    try { globalAudio.setAttribute('controlsList', 'nodownload'); } catch (e) { }

    const isChromeDesktop = (typeof navigator !== 'undefined') && /Chrome\//.test(navigator.userAgent) && !/Mobile|Android/.test(navigator.userAgent) && !/Edg\//.test(navigator.userAgent) && !/OPR\//.test(navigator.userAgent);
    if (isChromeDesktop) {
        globalAudio.addEventListener('contextmenu', (ev) => {
            ev.preventDefault();
        }, { passive: false });
    }

    let playlist = [];
    let order = [];
    let currentIndex = 0;

    function shuffle(array) {
        for (let i = array.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [array[i], array[j]] = [array[j], array[i]];
        }
    }

    function setTrack(idx) {
        if (!playlist.length) return;
        currentIndex = ((idx % order.length) + order.length) % order.length;
        const track = playlist[order[currentIndex]];
        if (!track) return;
        globalAudio.src = track.src;
        try {
            const container = track.el.querySelector('.audio-controls');
            if (container) container.appendChild(globalAudio);
        } catch (e) { }
        globalAudio.play().catch(() => { });
        document.querySelectorAll('.audio-player.playing').forEach(el => el.classList.remove('playing'));
        if (track.el) track.el.classList.add('playing');
    }

    globalAudio.addEventListener('ended', () => {
        if (!order.length) return;
        currentIndex = (currentIndex + 1) % order.length;
        setTrack(currentIndex);
    });

    globalAudio.addEventListener('play', () => {
        document.querySelectorAll('.audio-player.playing').forEach(el => el.classList.remove('playing'));
        const active = playlist[order[currentIndex]];
        if (active && active.el) active.el.classList.add('playing');
    });
    globalAudio.addEventListener('pause', () => {
        document.querySelectorAll('.audio-player.playing').forEach(el => el.classList.remove('playing'));
    });

    // try to fetch ID3 metadata (best-effort). Returns {title, imageUrl}
    async function loadMetadata(src) {
        try {
            const resp = await fetch(src);
            if (!resp.ok) throw new Error('fetch failed');
            const buffer = await resp.arrayBuffer();
            const meta = parseID3v2(buffer);
            if (meta.image && meta.image.data) {
                const blob = new Blob([meta.image.data], { type: meta.image.mime || 'image/jpeg' });
                const url = URL.createObjectURL(blob);
                return { title: meta.title || null, imageUrl: url };
            }
            return { title: meta.title || null, imageUrl: null };
        } catch (e) {
            return { title: null, imageUrl: null };
        }
    }

    // find audio items on page and enhance them
    async function enhanceAudioItems() {
        const items = Array.from(document.querySelectorAll('.item'));
        for (const item of items) {
            const mediaPath = item.getAttribute('data-media-path') || '';
            const ext = (mediaPath.split('.').pop() || '').toLowerCase();
            const itemType = item.getAttribute('data-post-type') || '';
            if (!AUDIO_EXT.has(ext) && itemType !== 'audio') continue;
            const mediaWrap = item.querySelector('.media-wrap');
            if (!mediaWrap) continue;

            mediaWrap.classList.add('audio');

            const player = document.createElement('div');
            player.className = 'audio-player';

            const cover = document.createElement('img');
            cover.className = 'audio-cover';
            cover.alt = 'Audio cover';

            const inGrid = !!item.closest('.archive.grid');

            // placeholder for title (used in list view, but declared here so metadata callback can reference it)
            let titleEl;

            if (inGrid) {
                // grid preview: cover + indicator overlay only
                const indicator = document.createElement('div');
                indicator.className = 'audio-indicator';
                indicator.innerHTML = '<svg width="36px" height="36px" viewBox="0 0 24 24" stroke-width="1" fill="none" xmlns="http://www.w3.org/2000/svg" color="#ffffff"><path d="M4 13.4998L3.51493 13.6211C2.62459 13.8437 2 14.6437 2 15.5614V17.4383C2 18.356 2.62459 19.156 3.51493 19.3786L5.25448 19.8135C5.63317 19.9081 6 19.6217 6 19.2314V13.7683C6 13.378 5.63317 13.0916 5.25448 13.1862L4 13.4998ZM4 13.4998V13C4 8.02944 7.58172 4 12 4C16.4183 4 20 8.02944 20 13V13.5M20 13.5L20.4851 13.6211C21.3754 13.8437 22 14.6437 22 15.5614V17.4383C22 18.356 21.3754 19.156 20.4851 19.3786L18.7455 19.8135C18.3668 19.9081 18 19.6217 18 19.2314V13.7683C18 13.378 18.3668 13.0916 18.7455 13.1862L20 13.5Z" stroke="#ffffff" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"></path></svg>';

                player.appendChild(cover);
                player.appendChild(indicator);
                mediaWrap.innerHTML = '';
                mediaWrap.appendChild(player);
            } else {
                // single/list view: cover, title and native controls container
                titleEl = document.createElement('div');
                titleEl.className = 'audio-title';
                titleEl.textContent = '';

                const controls = document.createElement('div');
                controls.className = 'audio-controls';

                player.appendChild(cover);
                player.appendChild(titleEl);
                player.appendChild(controls);

                mediaWrap.innerHTML = '';
                mediaWrap.appendChild(player);
            }

            // register in playlist
            const absSrc = mediaPath;
            playlist.push({ src: absSrc, el: player, cover: null, title: null });

            const idx = playlist.length - 1;

            // attempt ID3 metadata extraction
            (async () => {
                const meta = await loadMetadata(absSrc);
                let coverUrl = meta.imageUrl;
                let title = meta.title;

                if (!coverUrl) {
                    const fallback = await findCoverFor(absSrc);
                    coverUrl = fallback;
                }

                if (coverUrl) {
                    cover.classList.remove('no-cover');
                    cover.src = coverUrl;
                } else {
                    cover.classList.add('no-cover');
                    try { cover.removeAttribute('src'); } catch (e) { }
                }

                if (titleEl) {
                    if (title) titleEl.textContent = title;
                    else {
                        const name = absSrc.split('/').pop() || absSrc;
                        titleEl.textContent = name.replace(/^[0-9_\-]+/, '').replace(/\.[^.]+$/, '');
                    }
                }

                playlist[idx].cover = coverUrl;
                if (titleEl) playlist[idx].title = titleEl.textContent;
            })();

            // clicking the cover/title starts playback for non-grid items
            const startPlayback = () => {
                const targetSrc = playlist[idx] && playlist[idx].src ? playlist[idx].src : '';
                let isSame = false;
                try {
                    if (globalAudio.src) {
                        const g = new URL(globalAudio.src, location.href).pathname;
                        const t = new URL(targetSrc, location.href).pathname;
                        isSame = g.endsWith(t) || g === t;
                    }
                } catch (e) {
                    isSame = globalAudio.src === targetSrc;
                }

                if (isSame) {
                    if (globalAudio.paused) {
                        globalAudio.play().catch(() => { });
                    } else {
                        globalAudio.pause();
                    }
                    return;
                }

                order = playlist.map((_, i) => i);
                shuffle(order);
                const pos = order.indexOf(idx);
                if (pos > 0) { order.splice(pos, 1); order.unshift(idx); }
                setTrack(0);
            };

            if (!inGrid) {
                cover.addEventListener('click', startPlayback);
                if (titleEl) titleEl.addEventListener('click', startPlayback);
            }
        }
    }

    // ── Video overlay click-to-play/pause (single / list view) ─────
    const VIDEO_PLAY_SVG = '<svg class="list-play-icon" width="36px" height="36px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6.90588 4.53682C6.50592 4.2998 6 4.58808 6 5.05299V18.947C6 19.4119 6.50592 19.7002 6.90588 19.4632L18.629 12.5162C19.0211 12.2838 19.0211 11.7162 18.629 11.4838L6.90588 4.53682Z" stroke="#ffffff" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"></path></svg>';
    const VIDEO_PAUSE_SVG = '<svg class="list-play-icon" width="36px" height="36px" stroke-width="1" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" color="#ffffff"><path d="M6 18.4V5.6C6 5.26863 6.26863 5 6.6 5H9.4C9.73137 5 10 5.26863 10 5.6V18.4C10 18.7314 9.73137 19 9.4 19H6.6C6.26863 19 6 18.7314 6 18.4Z" stroke="#ffffff" stroke-width="1"></path><path d="M14 18.4V5.6C14 5.26863 14.2686 5 14.6 5H17.4C17.7314 5 18 5.26863 18 5.6V18.4C18 18.7314 17.7314 19 17.4 19H14.6C14.2686 19 14 18.7314 14 18.4Z" stroke="#ffffff" stroke-width="1"></path></svg>';

    function initVideoOverlays() {
        document.querySelectorAll('.list-video-overlay').forEach((overlay) => {
            const mediaWrap = overlay.closest('.media-wrap');
            const video = mediaWrap && mediaWrap.querySelector('video');
            if (!video) return;

            overlay.addEventListener('click', () => {
                if (video.paused) {
                    video.play().catch(() => { });
                } else {
                    video.pause();
                }
            });

            video.addEventListener('play', () => {
                mediaWrap.classList.add('is-playing');
                overlay.innerHTML = VIDEO_PAUSE_SVG;
            });

            video.addEventListener('pause', () => {
                mediaWrap.classList.remove('is-playing');
                overlay.innerHTML = VIDEO_PLAY_SVG;
            });

            video.addEventListener('ended', () => {
                mediaWrap.classList.remove('is-playing');
                overlay.innerHTML = VIDEO_PLAY_SVG;
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            enhanceAudioItems();
            initVideoOverlays();
        });
    } else {
        enhanceAudioItems();
        initVideoOverlays();
    }

})();
