<?php

namespace Qoraiche\MailEclipse;

class MailEclipseMailables
{
    public static function get(string $name, string $key = 'name')
    {
        return self::all()->where($key, $name);
    }

    public static function all()
    {
        return collect(MailEclipse::getMailables());
    }
}
