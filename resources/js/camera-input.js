import { BrowserMultiFormatReader } from '@zxing/browser';
import { BarcodeFormat, DecodeHintType } from '@zxing/library';

// Botões [data-camera-for="#idDoCampo"] abrem a câmera para ler o código de barras do exemplar
// (Code 128) e preenchem o campo. A câmera é encerrada ao ler, ao fechar, ao ocultar a aba e ao sair.
const hints = new Map([
    [DecodeHintType.POSSIBLE_FORMATS, [BarcodeFormat.CODE_128, BarcodeFormat.EAN_13]],
    [DecodeHintType.TRY_HARDER, true],
]);

let overlay = null;
let controls = null;
let session = 0;

function buildOverlay() {
    const el = document.createElement('div');
    el.className = 'fixed inset-0 z-50 flex items-center justify-center bg-slate-900/70 p-4';
    el.innerHTML = `
        <div role="dialog" aria-modal="true" aria-label="Leitura de código de barras" class="w-full max-w-lg rounded-xl bg-white p-4 shadow-xl">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold">Ler código do exemplar</h2>
                <button type="button" data-close class="rounded-md px-3 py-1.5 text-sm font-medium text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-100">Fechar</button>
            </div>
            <div class="relative mt-3 aspect-[4/3] overflow-hidden rounded-lg bg-slate-900">
                <video data-video class="h-full w-full object-cover" playsinline muted autoplay></video>
                <div class="pointer-events-none absolute inset-x-[8%] top-1/2 h-1/3 -translate-y-1/2 rounded-lg border-2 border-white/80"></div>
            </div>
            <p data-status role="status" aria-live="polite" class="mt-3 text-sm text-slate-600">Iniciando a câmera…</p>
        </div>`;

    return el;
}

function stop() {
    session += 1;
    try {
        controls?.stop();
    } catch {
        /* já parada */
    }
    controls = null;
    const video = overlay?.querySelector('[data-video]');
    video?.srcObject?.getTracks?.().forEach((track) => track.stop());
    if (video) {
        video.srcObject = null;
    }
    overlay?.remove();
    overlay = null;
}

function describe(error) {
    switch (error?.name) {
        case 'NotAllowedError':
            return 'Permissão da câmera negada. Libere o acesso no navegador ou digite o código.';
        case 'NotFoundError':
            return 'Nenhuma câmera encontrada. Digite o código impresso abaixo das barras.';
        case 'NotReadableError':
            return 'A câmera está em uso por outro aplicativo.';
        default:
            return 'Não foi possível abrir a câmera. Digite o código.';
    }
}

async function open(input) {
    if (overlay) {
        return;
    }
    overlay = buildOverlay();
    document.body.append(overlay);
    overlay.querySelector('[data-close]').addEventListener('click', stop);

    const status = overlay.querySelector('[data-status]');
    const video = overlay.querySelector('[data-video]');

    if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
        status.textContent = 'A câmera exige HTTPS (ou localhost). Digite o código.';

        return;
    }

    const mine = ++session;
    const reader = new BrowserMultiFormatReader(hints, { delayBetweenScanAttempts: 250 });

    try {
        const started = await reader.decodeFromConstraints(
            { video: { facingMode: { ideal: 'environment' } }, audio: false },
            video,
            (result) => {
                if (mine !== session || !result) {
                    return;
                }
                input.value = result.getText().trim();
                input.dispatchEvent(new Event('input', { bubbles: true }));
                stop(); // um código por sessão de câmera
                if (input.dataset.autosubmit !== undefined) {
                    input.form?.requestSubmit();
                } else {
                    input.focus();
                }
            },
        );
        if (mine !== session) {
            started.stop();

            return;
        }
        controls = started;
        status.textContent = 'Aponte para o código de barras da etiqueta.';
    } catch (error) {
        status.textContent = describe(error);
    }
}

document.querySelectorAll('[data-camera-for]').forEach((button) => {
    button.addEventListener('click', () => {
        const input = document.querySelector(button.dataset.cameraFor);
        if (input) {
            open(input);
        }
    });
});

window.addEventListener('pagehide', stop);
document.addEventListener('visibilitychange', () => document.hidden && stop());
document.addEventListener('keydown', (event) => event.key === 'Escape' && stop());
