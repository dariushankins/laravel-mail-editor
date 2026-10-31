<?php

namespace Qoraiche\MailEclipse\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\App;
use Qoraiche\MailEclipse\Facades\MailEclipse;

class MailablesController extends Controller
{
    public function __construct()
    {
        abort_unless(
            App::environment(config('maileclipse.allowed_environments', ['local'])),
            403,
            'Environment Not Allowed'
        );
    }

    public function toMailablesList()
    {
        return redirect()->route('mailableList');
    }

    public function index()
    {
        $mailables = MailEclipse::getMailables();

        $mailables = (null !== $mailables) ? $mailables->sortBy('name') : collect([]);

        return view(MailEclipse::VIEW_NAMESPACE.'::sections.mailables', compact('mailables'));
    }

    public function generateMailable(Request $request)
    {
        return MailEclipse::generateMailable($request);
    }

    public function viewMailable($name)
    {
        $mailable = MailEclipse::getMailable('name', $name);

        if ($mailable->isEmpty()) {
            return redirect()->route('mailableList');
        }

        $resource = $mailable->first();

        $view = $resource['status'] === 'invalid' ? 'invalid-mailable' : 'view-mailable';

        return view(MailEclipse::VIEW_NAMESPACE.'::sections.'.$view)->with(compact('resource'));
    }

    public function editMailable($name)
    {
        $templateData = MailEclipse::getMailableTemplateData($name);

        if (! $templateData) {
            return redirect()->route('viewMailable', ['name' => $name]);
        }

        return view(MailEclipse::VIEW_NAMESPACE.'::sections.edit-mailable-template', compact('templateData', 'name'));
    }

    public function parseTemplate(Request $request)
    {
        $request->validate(['markdown' => 'required|string', 'viewpath' => 'required|string', 'template' => 'sometimes|boolean']);
        $template = $request->boolean('template');

        $viewPath = $template ? $request->viewpath : base64_decode($request->viewpath, true);

        // ref https://regexr.com/4dflu
        $bladeRenderable = preg_replace('/((?!{{.*?-)(&gt;)(?=.*?}}))/', '>', $request->markdown);

        if (MailEclipse::markdownedTemplateToView(true, $bladeRenderable, $viewPath, $template)) {
            return response()->json([
                'status' => 'ok',
            ]);
        }

        return response()->json([
            'status' => 'error',
        ]);
    }

    public function previewMarkdownView(Request $request)
    {
        return MailEclipse::previewMarkdownViewContent(false, $request->markdown, $request->name, false, $request->namespace);
    }

    public function previewMailable($name)
    {
        return MailEclipse::renderMailable($name);
    }

    public function delete(Request $request)
    {
        $request->validate(['mailablename' => ['required', 'string', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/']]);

        return response()->json((new \Qoraiche\MailEclipse\Actions\DeleteMailable)->handle($request->only('mailablename')));
    }

    public function sendTest(Request $request)
    {
        $validatedData = $request->validate([
            'email' => 'email|nullable',
            'name' => 'string|required',
        ]);

        $email = $request->get('email') ?? config('maileclipse.test_mail');

        MailEclipse::sendTest($request->get('name'), $email);

        return response()->json(['status' => 'ok']);
    }
}
