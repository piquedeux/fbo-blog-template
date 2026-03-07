(function () {
    // audio-player.js - isolated file so it can be removed later
    const AUDIO_EXT = new Set(['mp3', 'wav', 'flac', 'ogg', 'm4a']);

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
        const ver = view.getUint8(3); // 3 or 4
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
                    // read mime until 0x00
                    let mime = '';
                    while (p < offset + frameSize && view.getUint8(p) !== 0) {
                        mime += String.fromCharCode(view.getUint8(p));
                        p++;
                    }
                    p++; // skip 0
                    const picType = view.getUint8(p); p++;
                    // description terminated with 0x00 (encoding aware - but we'll seek 0x00 byte)
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
        }
        return result;
    }
    // ...existing code...
})();
