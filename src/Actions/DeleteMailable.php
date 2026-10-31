<?php

namespace Qoraiche\MailEclipse\Actions;

use Illuminate\Support\Facades\File;

class DeleteMailable
{
    public function handle(array $request)
    {
        $name = $request['mailablename'] ?? '';
        abort_unless(preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name), 422);
        $root = realpath(config('maileclipse.mailables_dir'));
        $mailableFile = $root ? realpath($root.'/'.$name.'.php') : false;
        abort_if($mailableFile && ! str_starts_with($mailableFile, $root.DIRECTORY_SEPARATOR), 422);

        if (! $mailableFile || ! File::exists($mailableFile)) {
            return [
                'status' => 'error',
                'message' => 'Mailable '.$request['mailablename'].' does not exist',
            ];
        }

        if (File::delete($mailableFile)) {
            return [
                'status' => 'ok',
                'message' => 'Mailable Deleted',
            ];
        }

        return [
            'status' => 'error',
            'message' => 'Unknown error, please check the log file',
        ];
    }
}
