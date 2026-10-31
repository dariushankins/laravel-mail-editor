import { test, before, after } from 'node:test';
import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { cp, mkdir, mkdtemp, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { chromium } from 'playwright';

let server, browser, data;
let serverLog = '';
const base = 'http://127.0.0.1:8765';
before(async () => {
  data = await mkdtemp(`${tmpdir()}/maileclipse-browser-`);
  await mkdir(`${data}/app`, {recursive: true});
  await writeFile(`${data}/composer.json`, JSON.stringify({autoload: {'psr-4': {'App\\': 'app/'}}}));
  await cp('tests/Fixtures/Mail', `${data}/Mail`, { recursive: true });
  await cp('tests/Fixtures/views', `${data}/resources/views/fixture`, { recursive: true });
  for (const dir of ['views', 'sessions', 'cache']) await mkdir(`${data}/storage/framework/${dir}`, { recursive: true });
  server = spawn(process.env.PHP_BINARY || 'php', ['-S', '127.0.0.1:8765', 'tests/browser/router.php'], {
    env: { ...process.env, MAILECLIPSE_BROWSER_DATA: data }, stdio: ['ignore', 'pipe', 'pipe'],
  });
  server.stderr.on('data', chunk => serverLog += chunk);
  server.on('error', error => serverLog += error);
  let ready = false, lastResponse = "";
  for (let attempt = 0; attempt < 30; attempt++) {
    try { const response = await fetch(`${base}/maileclipse/mailables`); ready = response.ok; lastResponse = await response.text(); if (ready) break; } catch {}
    await new Promise(resolve => setTimeout(resolve, 100));
  }
  assert.ok(ready, `Fixture server did not start: ${serverLog.slice(-1500)}\n${lastResponse.replace(/<[^>]+>/g, " ").slice(0,1800)}`);
  browser = await chromium.launch({ headless: true, executablePath: process.env.CHROMIUM_PATH || undefined, args: ['--no-sandbox'] });
});
after(async () => { await browser?.close(); server?.kill(); if (data) await rm(data, { recursive: true, force: true }); });

async function pageAt(path, viewport = { width: 1440, height: 1000 }) {
  const page = await browser.newPage({ viewport });
  page.setDefaultTimeout(10000);
  page.errors = [];
  page.on('pageerror', error => { page.errors.push(error.message); console.error('JS ERROR', error.message); });
  page.on('response', async response => { if(response.status() >= 400) console.error('HTTP', response.status(), response.url(), (await response.text()).slice(0,300)); });
  await page.goto(base + path);
  await page.locator('[data-theme-select]').waitFor();
  return page;
}

async function waitForEditor(page) {
  try { await page.waitForFunction(() => window.tinymce?.get('template_editor')?.initialized); }
  catch (error) { throw new Error(error.message + '\n' + JSON.stringify(page.errors) + '\n' + (await page.locator('body').innerText()).slice(0,1600)); }
}

test('mailables search, theme persistence, creation modal and mobile layout', async () => {
  const page = await pageAt('/maileclipse/mailables');
  assert.equal(await page.locator('#mailables_list tbody tr').count(), 3);
  await page.getByLabel('Search this list').fill('weekly');
  assert.equal(await page.locator('#mailables_list tbody tr:visible').count(), 1);
  await page.getByLabel('Search this list').fill('no matching email');
  assert.ok(await page.locator('[data-search-empty]').isVisible());
  await page.getByLabel('Search this list').fill('');
  await page.getByLabel('Appearance').selectOption('dark');
  await page.reload();
  assert.equal(await page.locator('html').getAttribute('data-bs-theme'), 'dark');
  await page.getByRole('link', { name: 'Add Mailable', exact: true }).click();
  await page.locator('#newMailableModal.show').waitFor();
  await page.getByRole('button', { name: 'Close', exact: true }).last().click();
  await page.locator('#newMailableModal').waitFor({state:'hidden'});
  await page.locator('.modal-backdrop').waitFor({state:'hidden'});
  await page.getByLabel('Appearance').selectOption('light');
  if (process.env.SCREENSHOT_DIR) await page.screenshot({ path: `${process.env.SCREENSHOT_DIR}/mailables-light.png`, fullPage: true });
  await page.getByLabel('Appearance').selectOption('dark');
  if (process.env.SCREENSHOT_DIR) await page.screenshot({ path: `${process.env.SCREENSHOT_DIR}/mailables-dark.png`, fullPage: true });
  await page.setViewportSize({ width: 390, height: 844 });
  assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
  if (process.env.SCREENSHOT_DIR) await page.screenshot({ path: `${process.env.SCREENSHOT_DIR}/mailables-mobile.png`, fullPage: true });
  assert.deepEqual(page.errors, []);
  await page.close();
});

test('template picker Bootstrap tabs and single modal per design work', async () => {
  const page = await pageAt('/maileclipse/templates/new');
  await page.getByRole('tab', { name: 'Markdown', exact: true }).click();
  assert.ok(await page.locator('#pills-markdown').isVisible());
  await page.locator('[data-bs-target="#postmarkModal"]').click();
  await page.locator('#postmarkModal.show').waitFor();
  assert.equal(await page.locator('#postmarkModal').count(), 1);
  assert.deepEqual(page.errors, []);
  await page.close();
});

test('HTML editor loads locally and preserves document head and body on round trip', async () => {
  const page = await pageAt('/maileclipse/mailables/edit/template/ModernMail');
  await waitForEditor(page);
  const content = await page.evaluate(() => tinymce.get('template_editor').getContent());
  assert.match(content, /<!doctype html>/i);
  assert.match(content, /<title>Welcome<\/title>/);
  assert.match(content, /<body style=/);
  assert.match(content, /\{\{ \$intro \}\}/);
  await page.getByRole('tab', { name: 'Plain Text', exact: true }).click();
  assert.ok(await page.locator('#pills-profile .CodeMirror').isVisible());
  await page.getByRole('tab', { name: 'Editor', exact: true }).click();
  if (process.env.SCREENSHOT_DIR) await page.screenshot({ path: `${process.env.SCREENSHOT_DIR}/editor.png`, fullPage: true });
  assert.deepEqual(page.errors, []);
  await page.close();
});

test('Markdown editor previews email inside an isolated frame', async () => {
  const page = await pageAt('/maileclipse/mailables/edit/template/MarkdownMail');
  await page.waitForFunction(() => document.querySelector('.CodeMirror')?.CodeMirror);
  await page.locator('.preview-toggle').click();
  await page.locator('.editor-preview iframe').waitFor();
  assert.match(await page.locator('.editor-preview iframe').getAttribute('srcdoc'), /Weekly digest/);
  assert.equal(await page.locator('.editor-preview iframe').getAttribute('sandbox'), '');
  assert.deepEqual(page.errors, []);
  await page.close();
});

test('create HTML template and save edited application Blade view', async () => {
  const page = await pageAt('/maileclipse/templates/new/html/plain/plain');
  await waitForEditor(page);
  await page.getByRole('button', { name: 'Create', exact: true }).click();
  await page.locator('.notie-input-field').last().fill('Browser Welcome');
  await page.locator('.notie-button').filter({ hasText: 'Submit' }).last().click();
  await page.locator('.notie-input-field').last().fill('A reusable welcome email');
  await page.locator('.notie-button').filter({ hasText: 'Create Template' }).last().click();
  await page.waitForURL('**/templates/edit/browserWelcome');
  await waitForEditor(page);
  await page.evaluate(() => tinymce.get('template_editor').setContent('<p>Updated browser template</p>'));
  await page.getByRole('button', { name: 'Update', exact: true }).click();
  await page.locator('.notie-button').filter({hasText: 'Yes'}).last().click();
  await page.getByText('Template updated', {exact: true}).waitFor();
  await page.reload();
  await waitForEditor(page);
  assert.match(await page.evaluate(() => tinymce.get('template_editor').getContent()), /Updated browser template/);
  assert.deepEqual(page.errors, []);
  await page.close();
});

test('a bad template is flagged and its diagnostic opens without blocking the workspace', async () => {
  await cp('tests/Fixtures/Bad/BrokenMail.php', `${data}/Mail/BrokenMail.php`);
  const page = await pageAt('/maileclipse/mailables');
  assert.equal(await page.locator('#mailables_list tbody tr').count(), 4);
  assert.ok(await page.getByText('Bad template', {exact: true}).isVisible());
  if (process.env.SCREENSHOT_DIR) await page.screenshot({path: `${process.env.SCREENSHOT_DIR}/bad-template.png`, fullPage: true});
  await page.locator('#mailable_item_BrokenMail .table-action').first().click();
  await page.getByRole('heading', {name: 'Preview check failed'}).waitFor();
  assert.ok(await page.getByRole('link', {name: 'Back to mailables'}).isVisible());
  assert.deepEqual(page.errors, []);
  await page.close();
});
