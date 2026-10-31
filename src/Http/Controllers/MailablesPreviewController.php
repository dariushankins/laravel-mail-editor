<?php

namespace Qoraiche\MailEclipse\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\App;
use Qoraiche\MailEclipse\Facades\MailEclipse;
use Qoraiche\MailEclipse\Http\Exceptions\PreviewErrorException;

class MailablesPreviewController extends Controller
{
    public function __construct()
    {
        abort_unless(
            App::environment(config('maileclipse.allowed_environments', ['local'])),
            403
        );
    }

    public function previewError()
    {
        return view(MailEclipse::VIEW_NAMESPACE.'::previewerror');
    }

    public function markdownView(Request $request)
    {
        return MailEclipse::previewMarkdownViewContent(false, $request->markdown, $request->name, false, $request->namespace);
    }

    public function mailable($name)
    {
        try {
            return MailEclipse::renderMailable($name);
        } catch (\Throwable $e) {
            throw new PreviewErrorException($e);
        }
    }
}
