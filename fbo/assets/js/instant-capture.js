
(function () {
    // instant-capture.js - add quick record/take-photo buttons for the upload form
    const ready = () => {
        const input = document.getElementById('inlineUploadFiles');
        const form = document.getElementById('inlineUploadForm') || input?.closest('form');
        if (!input || !form) return;

        let heroActions = form.querySelector('.hero-actions');
        if (!heroActions) {
            heroActions = document.createElement('div');
            heroActions.className = 'hero-actions';
            form.insertBefore(heroActions, form.firstChild);
        }

        if (document.getElementById('instantRecordBtn') || document.getElementById('instantPhotoBtn')) {
            return;
        }

        // create buttons
        const recordBtn = document.createElement('button');
        recordBtn.type = 'button';
        recordBtn.className = 'ui-btn';
        recordBtn.id = 'instantRecordBtn';
        recordBtn.textContent = 'Record audio';

        const photoBtn = document.createElement('button');
        photoBtn.type = 'button';
        photoBtn.className = 'ui-btn';
        photoBtn.id = 'instantPhotoBtn';
        photoBtn.textContent = 'Take photo';

        heroActions.insertBefore(photoBtn, heroActions.firstChild);
        heroActions.insertBefore(recordBtn, heroActions.firstChild);

        const allowedExt = new Set(
            String(document.body?.dataset?.mediaExtensions || '')
                .split(',')
                .map((value) => value.trim().toLowerCase())
                .filter(Boolean)
        );
        const maxUploadBytes = Number(document.body?.dataset?.maxUploadFileSizeBytes || '104857600');

        const extFromName = (name) => (String(name || '').split('.').pop() || '').toLowerCase();
        const isAllowedFile = (file) => {
            const ext = extFromName(file.name);
            if (!ext || (allowedExt.size > 0 && !allowedExt.has(ext))) return false;
            if (Number.isFinite(maxUploadBytes) && maxUploadBytes > 0 && file.size > maxUploadBytes) return false;
            return true;
        };

        // helper to add File to input.files via DataTransfer
        const addFileToInput = (file) => {
            if (!isAllowedFile(file)) {
                alert('Captured file is not allowed or too large.');
                return false;
            }
            const dt = new DataTransfer();
            // preserve existing files
            const existing = Array.from(input.files || []);
            for (const f of existing) dt.items.add(f);
            dt.items.add(file);
            input.files = dt.files;
            // trigger change so preview logic picks it up
            input.dispatchEvent(new Event('change', { bubbles: true }));
            return true;
        };

        // Audio recording
        let mediaRecorder = null;
        let audioChunks = [];
        let audioStream = null;
        let audioContext = null;
        let analyser = null;
        let visualizerFrame = 0;
        let visualizerWrap = null;

        const stopVisualizer = () => {
            if (visualizerFrame) {
                cancelAnimationFrame(visualizerFrame);
                visualizerFrame = 0;
            }
            if (audioContext) {
                audioContext.close().catch(() => { });
                audioContext = null;
            }
            analyser = null;
            if (visualizerWrap) {
                visualizerWrap.remove();
                visualizerWrap = null;
            }
        };

        const startVisualizer = (stream) => {
            stopVisualizer();

            visualizerWrap = document.createElement('div');
            visualizerWrap.className = 'recording-visualizer';

            const label = document.createElement('div');
            label.className = 'recording-visualizer-label';
            label.textContent = 'Recording…';

            const canvas = document.createElement('canvas');
            canvas.className = 'recording-visualizer-canvas';
            canvas.width = 480;
            canvas.height = 64;

            visualizerWrap.appendChild(label);
            visualizerWrap.appendChild(canvas);
            form.appendChild(visualizerWrap);

            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;

            audioContext = new AudioCtx();
            const source = audioContext.createMediaStreamSource(stream);
            analyser = audioContext.createAnalyser();
            analyser.fftSize = 256;
            source.connect(analyser);

            const ctx = canvas.getContext('2d');
            if (!ctx) return;

            const bufferLength = analyser.frequencyBinCount;
            const dataArray = new Uint8Array(bufferLength);

            const draw = () => {
                if (!analyser) return;

                analyser.getByteFrequencyData(dataArray);
                ctx.clearRect(0, 0, canvas.width, canvas.height);

                const barCount = 32;
                const step = Math.max(1, Math.floor(bufferLength / barCount));
                const barWidth = canvas.width / barCount;

                for (let i = 0; i < barCount; i++) {
                    const value = dataArray[i * step] || 0;
                    const height = Math.max(2, (value / 255) * (canvas.height - 8));
                    const x = i * barWidth;
                    const y = canvas.height - height;
                    ctx.fillStyle = 'currentColor';
                    ctx.fillRect(x + 1, y, Math.max(1, barWidth - 2), height);
                }

                visualizerFrame = requestAnimationFrame(draw);
            };

            draw();
        };

        recordBtn.addEventListener('click', async () => {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                alert('Audio recording not supported in this browser.');
                return;
            }

            if (mediaRecorder && mediaRecorder.state === 'recording') {
                // stop
                mediaRecorder.stop();
                return;
            }

            try {
                audioStream = await navigator.mediaDevices.getUserMedia({ audio: true });
            } catch (e) {
                alert('Permission denied or no microphone available.');
                return;
            }

            if (typeof MediaRecorder === 'undefined') {
                for (const t of audioStream.getTracks()) t.stop();
                audioStream = null;
                alert('Audio recording not supported in this browser.');
                return;
            }

            const mp4Candidates = ['audio/mp4;codecs=mp4a.40.2', 'audio/mp4'];
            const fallbackCandidates = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/ogg'];

            const recorderMime = mp4Candidates.find((type) => {
                return typeof MediaRecorder.isTypeSupported === 'function'
                    ? MediaRecorder.isTypeSupported(type)
                    : false;
            }) || fallbackCandidates.find((type) => {
                return typeof MediaRecorder.isTypeSupported === 'function'
                    ? MediaRecorder.isTypeSupported(type)
                    : false;
            }) || '';

            if (!recorderMime) {
                for (const t of audioStream.getTracks()) t.stop();
                audioStream = null;
                alert('Audio recording is not supported in this browser.');
                return;
            }

            mediaRecorder = new MediaRecorder(audioStream, { mimeType: recorderMime });
            audioChunks = [];
            startVisualizer(audioStream);
            recordBtn.classList.add('ui-btn-record-live', 'active');

            const stopBtn = document.createElement('button');
            stopBtn.type = 'button';
            stopBtn.className = 'ui-btn ui-btn-record-stop';
            stopBtn.textContent = 'Stop recording';
            stopBtn.id = 'stopRecordBtn';
            heroActions.appendChild(stopBtn);

            stopBtn.addEventListener('click', () => {
                if (mediaRecorder && mediaRecorder.state === 'recording') mediaRecorder.stop();
            });

            mediaRecorder.addEventListener('dataavailable', (e) => {
                if (e.data && e.data.size) audioChunks.push(e.data);
            });

            mediaRecorder.addEventListener('stop', () => {
                const blobType = recorderMime.includes('mp4') ? 'audio/mp4' : recorderMime;
                const blob = new Blob(audioChunks, { type: blobType });
                const filename = 'recording_' + Date.now() + (
                    recorderMime.includes('ogg') ? '.ogg'
                        : recorderMime.includes('webm') ? '.webm'
                            : '.m4a'
                );
                const file = new File([blob], filename, { type: blobType });
                addFileToInput(file);

                // cleanup
                stopVisualizer();
                if (audioStream) {
                    for (const t of audioStream.getTracks()) t.stop();
                    audioStream = null;
                }
                mediaRecorder = null;
                recordBtn.classList.remove('ui-btn-record-live', 'active');
                stopBtn.remove();
            });

            mediaRecorder.start();
        });

        // Photo capture
        photoBtn.addEventListener('click', async () => {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                alert('Camera capture not supported in this browser.');
                return;
            }

            let camStream = null;
            try {
                camStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
            } catch (e) {
                alert('Permission denied or no camera available.');
                return;
            }

            // build small capture UI
            const overlay = document.createElement('div');
            overlay.style.position = 'fixed';
            overlay.style.inset = '0';
            overlay.style.background = 'rgba(0,0,0,0.8)';
            overlay.style.display = 'flex';
            overlay.style.alignItems = 'center';
            overlay.style.justifyContent = 'center';
            overlay.style.zIndex = '9999';

            const video = document.createElement('video');
            video.autoplay = true;
            video.playsInline = true;
            video.srcObject = camStream;
            video.style.maxWidth = '100%';
            video.style.maxHeight = '80%';

            const controls = document.createElement('div');
            controls.style.marginTop = '12px';
            controls.style.display = 'flex';
            controls.style.gap = '8px';
            controls.style.justifyContent = 'center';

            const snapBtn = document.createElement('button');
            snapBtn.type = 'button';
            snapBtn.className = 'ui-btn';
            snapBtn.textContent = 'Take photo';

            const cancelBtn = document.createElement('button');
            cancelBtn.type = 'button';
            cancelBtn.className = 'ui-btn';
            cancelBtn.textContent = 'Cancel';

            controls.appendChild(snapBtn);
            controls.appendChild(cancelBtn);

            const wrapper = document.createElement('div');
            wrapper.style.display = 'flex';
            wrapper.style.flexDirection = 'column';
            wrapper.style.alignItems = 'center';
            wrapper.appendChild(video);
            wrapper.appendChild(controls);

            overlay.appendChild(wrapper);
            document.body.appendChild(overlay);

            cancelBtn.addEventListener('click', () => {
                for (const t of camStream.getTracks()) t.stop();
                overlay.remove();
            });

            snapBtn.addEventListener('click', () => {
                const canvas = document.createElement('canvas');
                canvas.width = video.videoWidth || 1280;
                canvas.height = video.videoHeight || 720;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                canvas.toBlob((blob) => {
                    if (!blob) {
                        for (const t of camStream.getTracks()) t.stop();
                        overlay.remove();
                        return;
                    }
                    const filename = 'photo_' + Date.now() + '.jpg';
                    const file = new File([blob], filename, { type: 'image/jpeg' });
                    addFileToInput(file);
                    for (const t of camStream.getTracks()) t.stop();
                    overlay.remove();
                }, 'image/jpeg', 0.9);
            });
        });
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ready);
    else ready();

})();
