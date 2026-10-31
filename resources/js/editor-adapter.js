window.MailEclipseEditor = {
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
