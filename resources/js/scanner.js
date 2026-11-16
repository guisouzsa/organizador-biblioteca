import { BrowserMultiFormatReader } from '@zxing/browser';
import { BarcodeFormat, DecodeHintType, NotFoundException } from '@zxing/library';

const root = document.getElementById('scanner-app');

if (root) {
    initScanner(root);
}

function initScanner(root) {
    const els = {
        video: document.getElementById('scanner-video'),
        placeholder: document.getElementById('scanner-placeholder'),
        frame: document.getElementById('scanner-frame'),
        toggle: document.getElementById('scanner-toggle'),
        device: document.getElementById('scanner-device'),
        status: document.getElementById('scanner-status'),
        statusText: document.getElementById('scanner-status-text'),
        result: document.getElementById('scanner-result'),
        manualForm: document.getElementById('manual-isbn-form'),
        manualInput: document.getElementById('manual-isbn'),
    };
    const lookupUrl = root.dataset.lookupUrl;

    // Somente EAN-13 (ISBN-13). Leituras de outros formatos são ignoradas.
    const hints = new Map([
        [DecodeHintType.POSSIBLE_FORMATS, [BarcodeFormat.EAN_13]],
        [DecodeHintType.TRY_HARDER, true],
    ]);
    const reader = new BrowserMultiFormatReader(hints, { delayBetweenScanAttempts: 250 });

    let controls = null;
    let starting = false;
    let handling = false; // evita leituras duplicadas enquanto o mesmo código está na frente da câmera
    let lastRejected = { code: null, at: 0 };

    function setState(state, text) {
        els.status.dataset.state = state;
        els.statusText.textContent = text;
    }

    function isRunning() {
        return controls !== null;
    }

    function stopCamera() {
        if (controls) {
            try {
                controls.stop();
            } catch {
                /* já parada */
            }
            controls = null;
        }
        const stream = els.video.srcObject;
        if (stream && typeof stream.getTracks === 'function') {
            stream.getTracks().forEach((track) => track.stop());
        }
        els.video.srcObject = null;
        els.placeholder.classList.remove('hidden');
        els.frame.classList.add('hidden');
        els.toggle.textContent = 'Iniciar câmera';
    }

    function describeCameraError(error) {
        switch (error && error.name) {
            case 'NotAllowedError':
            case 'PermissionDeniedError':
                return 'Permissão da câmera negada. Libere o acesso nas configurações do navegador ou digite o ISBN abaixo.';
            case 'NotFoundError':
            case 'DevicesNotFoundError':
                return 'Nenhuma câmera foi encontrada neste dispositivo. Digite o ISBN abaixo.';
            case 'NotReadableError':
            case 'TrackStartError':
                return 'Não foi possível acessar a câmera. Ela pode estar em uso por outro aplicativo.';
            case 'OverconstrainedError':
                return 'A câmera selecionada não está disponível. Escolha outra ou use a opção automática.';
            case 'SecurityError':
                return 'O navegador bloqueou a câmera por segurança. Use HTTPS ou localhost.';
            default:
                return 'Não foi possível iniciar a câmera. Digite o ISBN abaixo ou cadastre manualmente.';
        }
    }

    async function loadDevices(activeDeviceId) {
        try {
            const devices = await BrowserMultiFormatReader.listVideoInputDevices();
            els.device.innerHTML = '';
            els.device.append(new Option('Automática (traseira)', ''));
            devices.forEach((device, index) => {
                els.device.append(new Option(device.label || `Câmera ${index + 1}`, device.deviceId));
            });
            els.device.value = devices.some((d) => d.deviceId === activeDeviceId) ? activeDeviceId : '';
            els.device.disabled = devices.length < 2;
        } catch {
            els.device.disabled = true;
        }
    }

    async function startCamera() {
        if (starting || isRunning()) {
            return;
        }
        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
            setState('error', 'A câmera exige HTTPS (ou localhost) e um navegador compatível. Digite o ISBN abaixo.');
            return;
        }

        starting = true;
        els.result.innerHTML = '';
        setState('requesting', 'Solicitando permissão para usar a câmera…');

        const deviceId = els.device.value;
        const constraints = {
            video: deviceId
                ? { deviceId: { exact: deviceId } }
                : { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } },
            audio: false,
        };

        try {
            controls = await reader.decodeFromConstraints(constraints, els.video, (result, error) => {
                if (result) {
                    onCode(result.getText());
                } else if (error && !(error instanceof NotFoundException)) {
                    // Erros de decodificação transitórios são esperados; ignorados.
                }
            });

            els.placeholder.classList.add('hidden');
            els.frame.classList.remove('hidden');
            els.toggle.textContent = 'Parar câmera';
            setState('scanning', 'Lendo código… aproxime o código de barras e mantenha-o dentro da moldura.');

            const track = els.video.srcObject?.getVideoTracks?.()[0];
            await loadDevices(track?.getSettings?.().deviceId);
        } catch (error) {
            stopCamera();
            setState('error', describeCameraError(error));
        } finally {
            starting = false;
        }
    }

    function onCode(raw) {
        if (handling) {
            return;
        }

        const code = String(raw).replace(/\D/g, '');
        const isIsbnPrefix = /^97[89]\d{10}$/.test(code);

        if (!isIsbnPrefix) {
            // EAN-13 que não é ISBN: avisa (sem repetir a cada quadro) e continua lendo.
            const now = Date.now();
            if (lastRejected.code !== code || now - lastRejected.at > 4000) {
                lastRejected = { code, at: now };
                setState('scanning', `O código ${code} não é um ISBN (deve começar com 978 ou 979). Continue procurando o código do livro.`);
            }
            return;
        }

        handling = true;
        stopCamera(); // interrompe novas leituras e libera a câmera
        setState('detected', `ISBN identificado: ${code}`);
        lookup(code);
    }

    async function lookup(isbn) {
        handling = true;
        setState('searching', 'Pesquisando o livro nas fontes bibliográficas…');
        els.result.innerHTML = '';

        try {
            const response = await fetch(lookupUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ isbn }),
            });

            if (response.status === 422) {
                setState('error', 'ISBN inválido. Confira o número e tente novamente.');
                return;
            }
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            els.result.innerHTML = data.html;

            const messages = {
                found: ['done', 'Resultado encontrado. Confira os dados e salve no catálogo.'],
                exists: ['done', 'Este livro já está cadastrado no catálogo.'],
                invalid: ['error', data.message || 'ISBN inválido.'],
                not_found: ['error', 'Livro não encontrado nas fontes consultadas.'],
                unavailable: ['error', 'As fontes bibliográficas estão indisponíveis no momento.'],
            };
            const [state, text] = messages[data.status] ?? ['done', 'Consulta concluída.'];
            setState(state, text);

            els.result.focus({ preventScroll: true });
            els.result.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } catch {
            setState('error', 'Não foi possível consultar o livro agora. Verifique a conexão e tente novamente.');
        } finally {
            handling = false;
        }
    }

    // Eventos
    els.toggle.addEventListener('click', () => {
        if (isRunning()) {
            stopCamera();
            setState('idle', 'Câmera desligada.');
        } else {
            startCamera();
        }
    });

    els.device.addEventListener('change', async () => {
        if (isRunning()) {
            stopCamera();
            await startCamera();
        }
    });

    els.manualForm.addEventListener('submit', (event) => {
        event.preventDefault();
        const value = els.manualInput.value.trim();
        if (value === '') {
            setState('error', 'Digite um ISBN para pesquisar.');
            els.manualInput.focus();
            return;
        }
        if (handling) {
            return;
        }
        stopCamera();
        lookup(value);
    });

    // Nunca deixar a câmera ativa ao sair da tela ou ocultar a aba.
    window.addEventListener('pagehide', stopCamera);
    window.addEventListener('beforeunload', stopCamera);
    document.addEventListener('visibilitychange', () => {
        if (document.hidden && isRunning()) {
            stopCamera();
            setState('idle', 'Câmera desligada ao sair da aba. Toque em “Iniciar câmera” para continuar.');
        }
    });
}
