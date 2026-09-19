import { BrowserQRCodeReader } from '@zxing/browser';
import { BarcodeFormat, DecodeHintType } from '@zxing/library';
import jsQR from 'jsqr';

type DetectedBarcode = { rawValue: string };
type NativeDetector = { detect: (source: ImageBitmapSource) => Promise<DetectedBarcode[]> };

let nativeDetector: NativeDetector | null | undefined;
let zxingReader: BrowserQRCodeReader | null = null;

const canvas = document.createElement('canvas');
const context = canvas.getContext('2d', { willReadFrequently: true });

const VIEWFINDER = 0.72;

export function looksLikeFiscalQr(value: string): boolean {
    return /(?:^|[?&;])fn=\d+/i.test(value) && /(?:^|[?&;])fp=\d+/i.test(value);
}

export function pickQr(values: string[]): string | null {
    const unique = [...new Set(values.map((value) => value.trim()).filter(Boolean))];

    return unique.find(looksLikeFiscalQr) ?? unique[0] ?? null;
}

export function viewfinderCrop(video: HTMLVideoElement): {
    sx: number;
    sy: number;
    sw: number;
    sh: number;
} {
    const width = video.videoWidth;
    const height = video.videoHeight;
    const side = Math.floor(Math.min(width, height) * VIEWFINDER);

    return {
        sx: Math.floor((width - side) / 2),
        sy: Math.floor((height - side) / 2),
        sw: side,
        sh: side,
    };
}

function getNativeDetector(): NativeDetector | null {
    if (nativeDetector !== undefined) {
        return nativeDetector;
    }

    const ctor = (
        globalThis as unknown as {
            BarcodeDetector?: new (options: { formats: string[] }) => NativeDetector;
        }
    ).BarcodeDetector;

    if (!ctor) {
        nativeDetector = null;

        return null;
    }

    try {
        nativeDetector = new ctor({ formats: ['qr_code'] });
    } catch {
        nativeDetector = null;
    }

    return nativeDetector;
}

function getZxing(): BrowserQRCodeReader {
    if (zxingReader) {
        return zxingReader;
    }

    const hints = new Map<DecodeHintType, boolean | BarcodeFormat[]>();
    hints.set(DecodeHintType.TRY_HARDER, true);
    hints.set(DecodeHintType.POSSIBLE_FORMATS, [BarcodeFormat.QR_CODE]);
    zxingReader = new BrowserQRCodeReader(hints);

    return zxingReader;
}

async function detectNative(source: ImageBitmapSource): Promise<string | null> {
    const detector = getNativeDetector();

    if (!detector) {
        return null;
    }

    try {
        const codes = await detector.detect(source);

        return pickQr(codes.map((code) => code.rawValue));
    } catch {
        return null;
    }
}

function detectJsQr(): string | null {
    if (!context || canvas.width < 8 || canvas.height < 8) {
        return null;
    }

    const image = context.getImageData(0, 0, canvas.width, canvas.height);
    const code = jsQR(image.data, image.width, image.height, {
        inversionAttempts: 'attemptBoth',
    });

    return code?.data ?? null;
}

function detectZxing(): string | null {
    try {
        return getZxing().decodeFromCanvas(canvas).getText();
    } catch {
        return null;
    }
}

function drawRegion(
    source: CanvasImageSource,
    sx: number,
    sy: number,
    sw: number,
    sh: number,
    maxEdge: number,
    filter = 'none',
): boolean {
    if (!context || sw < 8 || sh < 8) {
        return false;
    }

    const scale = Math.min(1, maxEdge / Math.max(sw, sh));
    canvas.width = Math.max(8, Math.round(sw * scale));
    canvas.height = Math.max(8, Math.round(sh * scale));
    context.filter = filter;
    context.drawImage(source, sx, sy, sw, sh, 0, 0, canvas.width, canvas.height);
    context.filter = 'none';

    return true;
}

function decodeDrawnCanvas(): string | null {
    return detectJsQr() ?? detectZxing();
}

export async function decodeQrFromVideo(video: HTMLVideoElement): Promise<string | null> {
    if (video.readyState < HTMLMediaElement.HAVE_CURRENT_DATA || video.videoWidth < 8) {
        return null;
    }

    const hits: string[] = [];
    const native = await detectNative(video);

    if (native) {
        hits.push(native);
    }

    const crop = viewfinderCrop(video);
    const filters = [
        'none',
        'grayscale(1) contrast(1.45)',
        'grayscale(1) contrast(1.7) invert(1)',
    ];

    for (const filter of filters) {
        if (!drawRegion(video, crop.sx, crop.sy, crop.sw, crop.sh, 1000, filter)) {
            continue;
        }

        const nativeCrop = await detectNative(canvas);

        if (nativeCrop) {
            hits.push(nativeCrop);
        }

        const drawn = decodeDrawnCanvas();

        if (drawn) {
            hits.push(drawn);
        }

        if (pickQr(hits) && looksLikeFiscalQr(pickQr(hits) as string)) {
            break;
        }
    }

    return pickQr(hits);
}

export async function grabViewfinderJpeg(video: HTMLVideoElement): Promise<Blob | null> {
    if (video.videoWidth < 8 || !context) {
        return null;
    }

    const crop = viewfinderCrop(video);

    if (!drawRegion(video, crop.sx, crop.sy, crop.sw, crop.sh, 1400, 'grayscale(1) contrast(1.35)')) {
        return null;
    }

    return new Promise((resolve) => {
        canvas.toBlob((blob) => resolve(blob), 'image/jpeg', 0.95);
    });
}

export async function decodeQrFromFile(file: File): Promise<string | null> {
    const bitmap = await createImageBitmap(file);
    const hits: string[] = [];
    const native = await detectNative(bitmap);

    if (native) {
        hits.push(native);
    }

    const regions: Array<[number, number, number, number]> = [
        [0, 0, bitmap.width, bitmap.height],
        [
            Math.floor(bitmap.width * 0.15),
            Math.floor(bitmap.height * 0.15),
            Math.floor(bitmap.width * 0.7),
            Math.floor(bitmap.height * 0.7),
        ],
        [
            Math.floor(bitmap.width * 0.2),
            Math.floor(bitmap.height * 0.45),
            Math.floor(bitmap.width * 0.6),
            Math.floor(bitmap.height * 0.5),
        ],
        [
            Math.floor(bitmap.width * 0.2),
            Math.floor(bitmap.height * 0.08),
            Math.floor(bitmap.width * 0.6),
            Math.floor(bitmap.height * 0.45),
        ],
    ];

    for (const [sx, sy, sw, sh] of regions) {
        if (!drawRegion(bitmap, sx, sy, sw, sh, 1100, 'grayscale(1) contrast(1.4)')) {
            continue;
        }

        const nativeRegion = await detectNative(canvas);

        if (nativeRegion) {
            hits.push(nativeRegion);
        }

        const drawn = decodeDrawnCanvas();

        if (drawn) {
            hits.push(drawn);
        }
    }

    bitmap.close();

    return pickQr(hits);
}
