import bwipjs from 'bwip-js';
import fs from 'node:fs';
for (const [name, code] of [['isbn', '978014032872'], ['ean', '789100010010']]) {
  const png = await bwipjs.toBuffer({ bcid: 'ean13', text: code, scale: 4, height: 18, includetext: true, textxalign: 'center', paddingwidth: 30, paddingheight: 30, backgroundcolor: 'FFFFFF' });
  fs.writeFileSync(`${name}.png`, png);
}
console.log('ok');
