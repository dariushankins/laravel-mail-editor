const toolbarIconStyles = document.createElement('style');
toolbarIconStyles.textContent = `
  .editor-toolbar button.image i { color: #2879d0; }
  .editor-toolbar button.button-component i { color: var(--me-accent, #087f70); }
  [data-bs-theme="dark"] .editor-toolbar button.image i { color: #8ac5ff; }
  [data-bs-theme="dark"] .editor-toolbar button.button-component i { color: #68d8ba; }
`;
document.head.append(toolbarIconStyles);

window.MailEclipseEditor = {
  enableSideBySide(editor, button) {
    if (!button) return;

    if (!document.getElementById('me-live-preview-styles')) {
      const style = document.createElement('style');
      style.id = 'me-live-preview-styles';
      style.textContent = `
        .me-live-editor-layout { min-width: 0; }
        .me-live-editor-layout.is-split { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; align-items: start; }
        .me-live-editor-layout > .tox-tinymce { min-width: 0; width: 100% !important; }
        .me-live-preview-panel { display: none; min-width: 0; overflow: hidden; border: 1px solid var(--me-line, #e0e8e9); border-radius: .65rem; background: #fff; }
        .me-live-editor-layout.is-split .me-live-preview-panel { display: block; }
        .me-live-preview-title { margin: 0; padding: .65rem .9rem; border-bottom: 1px solid var(--me-line, #e0e8e9); color: var(--me-muted, #64777d); font-size: .75rem; font-weight: 600; }
        .me-live-preview-frame { display: block; width: 100%; height: 600px; border: 0; background: #fff; }
        @media (max-width: 900px) { .me-live-editor-layout.is-split { grid-template-columns: minmax(0, 1fr); } .me-live-preview-frame { height: 500px; } }
      `;
      document.head.append(style);
    }

    const container = editor.getContainer();
    const layout = document.createElement('div');
    layout.className = 'me-live-editor-layout';
    const panel = document.createElement('section');
    panel.className = 'me-live-preview-panel';
    panel.setAttribute('aria-label', 'Live email preview');
    const heading = document.createElement('h2');
    heading.className = 'me-live-preview-title';
    heading.textContent = 'Live preview';
    const frame = document.createElement('iframe');
    frame.className = 'me-live-preview-frame';
    frame.title = 'Live email preview';
    frame.setAttribute('sandbox', '');
    panel.append(heading, frame);
    container.before(layout);
    layout.append(container, panel);

    let updateTimer;
    const updatePreview = () => {
      window.clearTimeout(updateTimer);
      updateTimer = window.setTimeout(() => {
        frame.srcdoc = editor.getContent();
      }, 120);
    };

    editor.on('input change undo redo SetContent', updatePreview);
    updatePreview();

    button.addEventListener('click', () => {
      const enabled = layout.classList.toggle('is-split');
      button.setAttribute('aria-pressed', String(enabled));
      if (enabled) updatePreview();
      window.dispatchEvent(new Event('resize'));
    });
  },
  init(options) {
    const setup = options.setup;
    return tinymce.init({ ...options, setup(editor) {
      let prefix = '', suffix = '';
      editor.on('BeforeSetContent', event => {
        if (event.selection) return;
        const document = event.content.match(/^([\s\S]*?<body\b[^>]*>)([\s\S]*?)(<\/body\s*>[\s\S]*)$/i);
        if (document) {
          [, prefix, event.content, suffix] = document;
        }
      });
      editor.on('GetContent', event => {
        if (prefix && !event.selection) event.content = prefix + event.content + suffix;
      });
      editor.on('init', () => {
        // Retain email head styles in the editing surface without changing the host page.
        for (const match of prefix.matchAll(/<style\b[^>]*>([\s\S]*?)<\/style>/gi)) editor.dom.addStyle(match[1]);
      });
      setup?.(editor);
    }});
  },
  preview(container, html) {
    const frame = document.createElement('iframe');
    frame.title = 'Email preview';
    frame.setAttribute('sandbox', '');
    frame.srcdoc = html;
    container.replaceChildren(frame);
  },
};
