<?php

namespace Qoraiche\MailEclipse\Support;

use Illuminate\Support\Facades\Blade;
use PhpToken;
use Throwable;

class TemplateValidator
{
    /** Check generated PHP syntax without evaluating application code or variables. */
    public function validate(string $source): ?string
    {
        try {
            PhpToken::tokenize(Blade::compileString($source), TOKEN_PARSE);
            return null;
        } catch (Throwable $error) {
            return $error->getMessage();
        }
    }
}
