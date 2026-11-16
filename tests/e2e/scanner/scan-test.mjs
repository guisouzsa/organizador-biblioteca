import puppeteer from 'puppeteer-core';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const CHROME = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const BASE = process.env.BASE_URL || 'http://127.0.0.1:8765';
const results = [];
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

function record(name, pass, detail = '') {
    results.push({ name, pass });
    console.log(`${pass ? 'PASS' : 'FAIL'}  ${name}${detail ? ' — ' + detail : ''}`);
}

async function launch({ feed, fakeUi = true, fakeDevice = true, devices = 1 }) {
    const args = ['--no-sandbox', '--window-size=1000,900'];
    if (fakeUi) args.push('--use-fake-ui-for-media-stream');
    if (fakeDevice) args.push(`--use-fake-device-for-media-stream${devices > 1 ? `=device-count=${devices}` : ''}`);
    if (feed) args.push(`--use-file-for-fake-video-capture=${path.join(here, feed)}`);
    return puppeteer.launch({ executablePath: CHROME, headless: 'new', args, defaultViewport: { width: 1000, height: 900 } });
}

async function openScanner(browser, { patchStop = false } = {}) {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', (e) => errors.push(e.message));
    const lookups = [];
    page.on('request', (r) => { if (r.url().endsWith('/catalogo/isbn')) lookups.push(r.postData()); });
    if (patchStop) {
        await page.evaluateOnNewDocument(() => {
            const orig = MediaStreamTrack.prototype.stop;
            MediaStreamTrack.prototype.stop = function () {
                sessionStorage.setItem('stops', String(Number(sessionStorage.getItem('stops') || 0) + 1));
                return orig.call(this);
            };
        });
    }
    await page.goto(`${BASE}/catalogo/scanner`, { waitUntil: 'networkidle0' });
    return { page, errors, lookups };
}

const state = (page) => page.$eval('#scanner-status', (el) => ({ state: el.dataset.state, text: el.textContent.trim() }));
const waitState = (page, states, timeout = 25000) =>
    page.waitForFunction((s) => s.includes(document.getElementById('scanner-status').dataset.state), { timeout }, states);
const toggleText = (page) => page.$eval('#scanner-toggle', (el) => el.textContent.trim());
const videoActive = (page) => page.$eval('#scanner-video', (v) => v.srcObject !== null);

// ---------- A/C: leitura de ISBN, uma única consulta, câmera encerrada ----------
{
    const browser = await launch({ feed: 'isbn.y4m' });
    const { page, errors, lookups } = await openScanner(browser, { patchStop: true });
    const initial = await state(page);
    record('Estado inicial "idle" e câmera desligada', initial.state === 'idle' && !(await videoActive(page)), initial.text);

    await page.click('#scanner-toggle');
    try {
        await waitState(page, ['scanning', 'detected', 'searching', 'done', 'error'], 15000);
        await waitState(page, ['detected', 'searching', 'done', 'error'], 25000);
        await waitState(page, ['done', 'error'], 40000);
    } catch (e) { /* segue para avaliação */ }
    const s = await state(page);
    await sleep(2500); // tempo para eventuais leituras duplicadas
    record('ZXing leu o EAN-13 da câmera virtual e concluiu a consulta', s.state === 'done', `${s.state}: ${s.text}`);
    record('Apenas UMA consulta enviada (sem leituras duplicadas)', lookups.length === 1, `consultas=${lookups.length} corpo=${lookups[0]}`);
    record('Câmera encerrada após a detecção (srcObject nulo, botão "Iniciar")', !(await videoActive(page)) && (await toggleText(page)) === 'Iniciar câmera');
    const stops = await page.evaluate(() => Number(sessionStorage.getItem('stops') || 0));
    record('Trilhas de vídeo foram realmente paradas (track.stop chamado)', stops >= 1, `chamadas=${stops}`);
    const html = await page.$eval('#scanner-result', (el) => el.textContent);
    record('Formulário de confirmação exibido com dados da fonte real', /Confirmar e salvar/.test(html) && /Fantastic/i.test(await page.$eval('#f-title', (e) => e.value).catch(() => '')), (await page.$eval('#f-title', (e) => e.value).catch(() => 'sem campo título')));
    await page.screenshot({ path: path.join(here, 'shot-isbn.png'), fullPage: true });
    record('Sem erros JavaScript na página', errors.length === 0, errors.join(' | '));
    await browser.close();
}

// ---------- B/F/G/I: EAN que não é ISBN; troca de aba; saída da página; múltiplas câmeras ----------
{
    const browser = await launch({ feed: 'ean.y4m' });
    const { page, lookups } = await openScanner(browser, { patchStop: true });
    await page.click('#scanner-toggle');
    await waitState(page, ['scanning'], 15000);
    await sleep(6000);
    const s = await state(page);
    record('EAN-13 que não é ISBN é rejeitado com aviso e a leitura continua', s.state === 'scanning' && /não é um ISBN/.test(s.text) && (await toggleText(page)) === 'Parar câmera', `${s.state}: ${s.text}`);
    record('Nenhuma consulta externa disparada para código não-ISBN', lookups.length === 0, `consultas=${lookups.length}`);

    // Aba oculta
    await page.evaluate(() => {
        Object.defineProperty(document, 'hidden', { configurable: true, get: () => true });
        document.dispatchEvent(new Event('visibilitychange'));
    });
    await sleep(500);
    const hidden = await state(page);
    record('Aba oculta: câmera é desligada e usuário é avisado', hidden.state === 'idle' && !(await videoActive(page)) && /sair da aba/.test(hidden.text), hidden.text);
    await page.evaluate(() => { delete document.hidden; });

    // Saída da página com a câmera ligada
    await page.click('#scanner-toggle');
    await waitState(page, ['scanning'], 15000);
    const before = await page.evaluate(() => Number(sessionStorage.getItem('stops') || 0));
    await page.goto(`${BASE}/catalogo`, { waitUntil: 'networkidle0' });
    const afterStops = await page.evaluate(() => Number(sessionStorage.getItem('stops') || 0));
    record('Sair da página com a câmera ativa encerra as trilhas de vídeo', afterStops > before, `stops ${before} → ${afterStops}`);
    await browser.close();
}

// ---------- I: seleção manual entre várias câmeras ----------
{
    const browser = await launch({ devices: 3 });
    const { page } = await openScanner(browser);
    await page.click('#scanner-toggle');
    await waitState(page, ['scanning'], 15000);
    await sleep(1500);
    const opts = await page.$$eval('#scanner-device option', (o) => o.map((x) => x.textContent));
    const enabled = await page.$eval('#scanner-device', (el) => !el.disabled);
    record('Seleção manual: lista de câmeras preenchida e habilitada', enabled && opts.length >= 3, JSON.stringify(opts));
    const values = await page.$$eval('#scanner-device option', (o) => o.map((x) => x.value).filter(Boolean));
    const current = await page.$eval('#scanner-device', (el) => el.value);
    const other = values.find((v) => v !== current) ?? values[0];
    await page.select('#scanner-device', other);
    await sleep(3000);
    const after = await state(page);
    const chosen = await page.$eval('#scanner-device', (el) => el.value);
    record('Trocar de câmera reinicia a leitura na câmera escolhida', after.state === 'scanning' && (await videoActive(page)) && chosen === other, `${after.state}; selecionada=${chosen === other}`);
    await browser.close();
}

// ---------- H: contexto inseguro (emulado) ----------
{
    const browser = await launch({ feed: 'isbn.y4m' });
    const { page } = await openScanner(browser);
    await page.evaluate(() => Object.defineProperty(window, 'isSecureContext', { value: false }));
    await page.click('#scanner-toggle');
    await sleep(500);
    const s = await state(page);
    record('Contexto não seguro (emulado): mensagem sobre HTTPS e nenhuma câmera aberta', s.state === 'error' && /HTTPS/.test(s.text) && !(await videoActive(page)), s.text);
    await browser.close();
}

// ---------- D: permissão negada ----------
{
    const browser = await launch({ feed: 'isbn.y4m', fakeUi: false });
    const client = await browser.target().createCDPSession();
    await client.send('Browser.setPermission', { origin: BASE, permission: { name: 'camera' }, setting: 'denied' });
    const { page } = await openScanner(browser);
    await page.click('#scanner-toggle');
    await sleep(3000);
    const s = await state(page);
    record('Permissão negada: mensagem clara e alternativa manual', s.state === 'error' && /Permissão da câmera negada/.test(s.text), s.text);
    const manualVisible = await page.$eval('#manual-isbn', (el) => el.offsetParent !== null);
    record('Campo de ISBN manual continua disponível após negar a câmera', manualVisible);
    await browser.close();
}

// ---------- E: sem dispositivo de câmera (emulado: getUserMedia rejeita com NotFoundError) ----------
{
    const browser = await launch({ fakeDevice: false });
    const page = await browser.newPage();
    await page.evaluateOnNewDocument(() => {
        navigator.mediaDevices.getUserMedia = () => Promise.reject(new DOMException('no device', 'NotFoundError'));
    });
    await page.goto(`${BASE}/catalogo/scanner`, { waitUntil: 'networkidle0' });
    await page.click('#scanner-toggle');
    await sleep(1500);
    const s = await state(page);
    record('Sem câmera (emulado): mensagem "Nenhuma câmera foi encontrada"', s.state === 'error' && /Nenhuma câmera/.test(s.text), `${s.state}: ${s.text}`);
    await browser.close();
}

// ---------- J: digitação manual ----------
{
    const browser = await launch({});
    const { page, lookups } = await openScanner(browser);
    await page.type('#manual-isbn', '9780140328722'); // dígito verificador errado
    await page.click('#manual-isbn-form button[type=submit]');
    await waitState(page, ['error', 'done'], 15000);
    let s = await state(page);
    const res = await page.$eval('#scanner-result', (el) => el.textContent);
    record('ISBN manual inválido: erro compreensível', s.state === 'error' && /ISBN inválido/.test(res), `${s.text}`);

    await page.$eval('#manual-isbn', (el) => (el.value = ''));
    await page.type('#manual-isbn', '978-0-14-032872-1');
    await page.click('#manual-isbn-form button[type=submit]');
    await waitState(page, ['done'], 40000).catch(() => {});
    s = await state(page);
    record('ISBN manual válido (com hifens): consulta e formulário de confirmação', s.state === 'done', s.text);

    await page.$eval('#manual-isbn', (el) => (el.value = ''));
    await page.click('#manual-isbn-form button[type=submit]');
    await sleep(300);
    s = await state(page);
    record('Campo manual vazio: pede para digitar o ISBN', s.state === 'error' && /Digite um ISBN/.test(s.text), s.text);
    await browser.close();
}

const failed = results.filter((r) => !r.pass);
console.log(`\nResumo: ${results.length - failed.length}/${results.length} verificações passaram.`);
process.exit(failed.length ? 1 : 0);
