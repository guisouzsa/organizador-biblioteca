import puppeteer from 'puppeteer-core';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const CHROME = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const BASE = process.env.BASE_URL || 'http://127.0.0.1:8765';
const pages = ['/catalogo', '/catalogo/novo', '/catalogo/scanner', '/catalogo/1', '/catalogo/1/editar', '/catalogo?q=zzzz'];
const viewports = { desktop: [1280, 900], tablet: [768, 1024], mobile: [390, 844] };

const browser = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
let problems = 0;

for (const [vpName, [w, h]] of Object.entries(viewports)) {
    for (const url of pages) {
        const page = await browser.newPage();
        await page.setViewport({ width: w, height: h, isMobile: vpName === 'mobile', hasTouch: vpName !== 'desktop' });
        const errors = [];
        page.on('pageerror', (e) => errors.push(e.message));
        page.on('console', (m) => { if (m.type() === 'error' && !/Failed to load resource/.test(m.text())) errors.push(m.text()); });
        const resp = await page.goto(BASE + url, { waitUntil: 'networkidle0' });

        const audit = await page.evaluate(() => {
            const out = {};
            const vw = document.documentElement.clientWidth;
            out.overflowX = document.documentElement.scrollWidth > vw + 1;
            out.overflowers = [...document.querySelectorAll('body *')]
                .filter((el) => el.getBoundingClientRect().right > vw + 1 && getComputedStyle(el).position !== 'fixed' && !el.closest('.sr-only'))
                .slice(0, 3).map((el) => el.tagName + '.' + (el.className || '').toString().slice(0, 40));
            out.lang = document.documentElement.lang;
            out.h1 = document.querySelectorAll('h1').length;
            out.imgNoAlt = [...document.querySelectorAll('img')].filter((i) => !i.hasAttribute('alt')).length;
            out.unlabeled = [...document.querySelectorAll('input:not([type=hidden]):not([type=checkbox]), select, textarea')]
                .filter((el) => !el.id || !document.querySelector(`label[for="${el.id}"]`) && !el.getAttribute('aria-label')).map((el) => el.name || el.id);
            out.smallTargets = [...document.querySelectorAll('a, button, select, input:not([type=hidden]):not([type=checkbox])')]
                .filter((el) => { const r = el.getBoundingClientRect(); return r.width > 0 && (r.height < 32); })
                .slice(0, 4).map((el) => (el.textContent || el.name || '').trim().slice(0, 20) + ' ' + Math.round(el.getBoundingClientRect().height) + 'px');
            return out;
        });

        const flags = [];
        if (resp.status() >= 400) flags.push(`HTTP ${resp.status()}`);
        if (audit.overflowX) flags.push(`ROLAGEM HORIZONTAL (${audit.overflowers.join(', ')})`);
        if (audit.h1 !== 1) flags.push(`h1=${audit.h1}`);
        if (audit.imgNoAlt) flags.push(`${audit.imgNoAlt} img sem alt`);
        if (audit.unlabeled.length) flags.push(`campos sem label: ${audit.unlabeled.join(',')}`);
        if (errors.length) flags.push(`erros JS: ${errors.join(' | ').slice(0, 120)}`);
        if (vpName === 'mobile' && audit.smallTargets.length) flags.push(`alvos pequenos: ${audit.smallTargets.join('; ')}`);
        if (flags.length) problems++;
        console.log(`${flags.length ? 'ATENÇÃO' : 'ok     '} ${vpName.padEnd(7)} ${url.padEnd(22)} ${flags.join(' · ')}`);

        const slug = url.replace(/[^a-z0-9]+/gi, '_');
        if (['/catalogo', '/catalogo/1/editar', '/catalogo/1'].includes(url) && vpName !== 'tablet') {
            await page.screenshot({ path: path.join(here, `ui-${vpName}${slug}.png`), fullPage: true });
        }
        await page.close();
    }
}
await browser.close();
console.log(`\n${problems} combinações com observações.`);
