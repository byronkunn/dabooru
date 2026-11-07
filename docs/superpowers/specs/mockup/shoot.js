/* Screenshot every mockup screen at desktop, tablet and mobile widths. */
const { chromium } = require('playwright-core');
const fs = require('fs');
const path = require('path');

const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const OUT = process.env.OUT || '/Users/byron/Desktop/letssee/docs/superpowers/specs/mockup/shots';
fs.mkdirSync(OUT, { recursive: true });

const VIEWPORTS = [
  { name: 'desktop', width: 1440, height: 900 },
  { name: 'tablet', width: 834, height: 1112 },
  { name: 'mobile', width: 390, height: 844 },
];

const SCREENS = [
  ['gallery', 'screens-gallery.html'],
  ['following', 'screen-following.html'],
  ['post', 'screen-post.html?id=w08'],
  ['post-comic', 'screen-post.html?id=w06'],
  ['profile', 'screen-profile.html'],
  ['profile-edit', 'screen-profile-edit.html'],
  ['comic-series', 'screen-comic-series.html?series=neon-after-dark'],
  ['reader', 'screen-reader.html'],
  ['messages', 'screen-messages.html'],
  ['notifications', 'screen-notifications.html'],
  ['settings', 'screen-settings.html'],
  ['upload', 'screen-upload.html'],
  ['gate', 'screen-gate.html'],
  ['signup', 'screen-signup.html'],
  ['login', 'screen-auth.html'],
  ['index', 'index.html'],
];

(async () => {
  const browser = await chromium.launch();
  const problems = [];
  for (const vp of VIEWPORTS) {
    const ctx = await browser.newContext({ viewport: { width: vp.width, height: vp.height }, deviceScaleFactor: 1 });
    const page = await ctx.newPage();
    page.on('pageerror', e => problems.push(`${vp.name}: JS error on page: ${e.message}`));
    page.on('console', m => { if (m.type() === 'error') problems.push(`${vp.name}: console error: ${m.text()}`); });
    for (const [name, url] of SCREENS) {
      await page.goto(`${BASE}/${url}`, { waitUntil: 'networkidle', timeout: 45000 }).catch(e => problems.push(`${vp.name}/${name}: navigation ${e.message}`));
      await page.waitForTimeout(500);
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
      if (overflow > 2) problems.push(`${vp.name}/${name}: horizontal overflow ${overflow}px`);
      await page.screenshot({ path: path.join(OUT, `${name}-${vp.name}.png`), fullPage: false });
    }
    await ctx.close();
  }
  await browser.close();
  fs.writeFileSync(path.join(OUT, 'report.txt'), problems.length ? problems.join('\n') : 'no problems');
  console.log(problems.length ? problems.join('\n') : 'no problems');
})();
