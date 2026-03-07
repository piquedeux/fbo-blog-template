
(function () {
    // instant-capture.js - add quick record/take-photo buttons for the upload form
    const ready = () => {
        const input = document.getElementById('inlineUploadFiles');
        const form = document.getElementById('inlineUploadForm');
        if (!input || !form) return;

        const heroActions = form.querySelector('.hero-actions');
        if (!heroActions) return;

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

        // helper to add File to input.files via DataTransfer
        const addFileToInput = (file) => {
            const dt = new DataTransfer();
            // preserve existing files
            const existing = Array.from(input.files || []);
            for (const f of existing) dt.items.add(f);
            dt.items.add(file);
            input.files = dt.files;
            // trigger change so preview logic picks it up
            input.dispatchEvent(new Event('change', { bubbles: true }));
        };

        // Audio recording
        let mediaRecorder = null;
        let audioChunks = [];
        let audioStream = null;

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

            mediaRecorder = new MediaRecorder(audioStream);
            audioChunks = [];

            const stopBtn = document.createElement('button');
            stopBtn.type = 'button';
            stopBtn.className = 'ui-btn';
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
                const blob = new Blob(audioChunks, { type: audioChunks[0]?.type || 'audio/webm' });
                const filename = 'recording_' + Date.now() + (blob.type.includes('ogg') ? '.ogg' : blob.type.includes('mpeg') ? '.mp3' : '.webm');
                const file = new File([blob], filename, { type: blob.type });
                addFileToInput(file);

                // cleanup
                if (audioStream) {
                    for (const t of audioStream.getTracks()) t.stop();
                    audioStream = null;
                }
                mediaRecorder = null;
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
