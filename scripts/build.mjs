import { cp, mkdir, readFile, writeFile, rm } from 'node:fs/promises';
import * as sass from 'sass';

await mkdir('public/css', { recursive: true });
await mkdir('public/js', { recursive: true });
const css = sass.compile('resources/scss/maileclipse-app.scss', {
  style: 'compressed', loadPaths: ['node_modules'], silenceDeprecations: ['import', 'global-builtin', 'color-functions', 'if-function'],
}).css;
await writeFile('public/css/maileclipse-app.min.css', css);
await writeFile('public/css/maileclipse-app.css', css);
const appSources = [
  'node_modules/jquery/dist/jquery.min.js',
  'node_modules/bootstrap/dist/js/bootstrap.bundle.min.js',
  'node_modules/axios/dist/axios.min.js',
  'node_modules/notie/dist/notie.min.js',
  'resources/js/maileclipse-app.js',
];
await writeFile('public/js/maileclipse-app.js', (await Promise.all(appSources.map(p => readFile(p, 'utf8')))).join('\n;\n'));
const editorSources = [
  'node_modules/easymde/dist/easymde.min.js',
  'node_modules/codemirror/lib/codemirror.js',
  ...['xml', 'javascript', 'css', 'htmlmixed'].map(mode => `node_modules/codemirror/mode/${mode}/${mode}.js`),
  'node_modules/codemirror/addon/display/placeholder.js',
  'resources/js/editor-adapter.js',
];
await writeFile('public/js/maileclipse-editor.js', (await Promise.all(editorSources.map(p => readFile(p, 'utf8')))).join('\n;\n') + '\nwindow.SimpleMDE = window.EasyMDE;\n');
await writeFile('public/css/maileclipse-editor.css', (await Promise.all([
  'node_modules/easymde/dist/easymde.min.css', 'node_modules/codemirror/lib/codemirror.css',
].map(p => readFile(p, 'utf8')))).join('\n').replace(/[ \t]+$/gm, ''));
for (const [from, to] of [
  ['node_modules/notie/dist/notie.min.css', 'public/css/notie.min.css'],
  ['node_modules/@fortawesome/fontawesome-free/css/all.min.css', 'public/css/fontawesome.min.css'],
  ['node_modules/@fortawesome/fontawesome-free/webfonts', 'public/webfonts'],
]) {
  await rm(to, { recursive: true, force: true });
  await cp(from, to, { recursive: true });
}
await rm('public/js/tinymce', { recursive: true, force: true });
for (const path of ['tinymce.min.js', 'icons/default/icons.min.js', 'models/dom/model.min.js', 'themes/silver/theme.min.js', 'skins/ui/oxide/skin.min.css', 'skins/ui/oxide/content.min.css', 'skins/ui/oxide-dark/skin.min.css', 'skins/ui/oxide-dark/content.min.css', 'skins/content/default/content.min.css', 'skins/content/dark/content.min.css']) {
  await mkdir(`public/js/tinymce/${path.includes('.') ? path.slice(0, Math.max(path.lastIndexOf('/'), 0)) : path}`, { recursive: true });
  await cp(`node_modules/tinymce/${path}`, `public/js/tinymce/${path}`, { recursive: true });
}
for (const plugin of 'advlist autolink link image lists charmap preview anchor searchreplace wordcount visualblocks visualchars code fullscreen insertdatetime media nonbreaking save table directionality emoticons'.split(' ')) {
  await mkdir(`public/js/tinymce/plugins/${plugin}`, { recursive: true });
  for (const file of plugin === 'emoticons' ? ['plugin.min.js', 'js/emojis.min.js'] : ['plugin.min.js']) {
    await mkdir(`public/js/tinymce/plugins/${plugin}/${file.includes('/') ? 'js' : ''}`, { recursive: true });
    await cp(`node_modules/tinymce/plugins/${plugin}/${file}`, `public/js/tinymce/plugins/${plugin}/${file}`);
  }
}
// Publish upstream license notices with the distributed browser dependencies.
await mkdir('public/licenses', { recursive: true });
for (const pkg of ['bootstrap', 'jquery', 'axios', 'notie', 'codemirror', 'easymde', 'tinymce', '@fortawesome/fontawesome-free']) {
  for (const name of ['LICENSE', 'LICENSE.md', 'LICENSE.txt', 'License.txt', 'license.md']) {
    try {
      await cp(`node_modules/${pkg}/${name}`, `public/licenses/${pkg.replaceAll('/', '-')}-${name}`);
      break;
    } catch (error) { if (error.code !== 'ENOENT') throw error; }
  }
}
console.log('Built local application and editor assets.');
