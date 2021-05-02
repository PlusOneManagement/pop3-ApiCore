<?php

namespace Core\Classes;

class Path
{
    public static function import($filepath, $extra = false)
    {
        $realpath = realpath($filepath);
        abort_if(!$filepath, 500, __("Filepath [$filepath] is invalid or does not exist!"));

        $extension = pathinfo($realpath, PATHINFO_EXTENSION);

        switch (strtolower($extension)) {
            case 'php':
                $imported = require $realpath;
                break;
            case 'json':
                $json = file_get_contents($realpath);
                $imported = json_decode($json, $extra);
                break;
            case 'csv':
                $csv = file_get_contents($realpath);
                $imported = str_getcsv($csv);
                break;
            default:
                $imported = file_get_contents($realpath);
                break;
        }

        return $imported;
    }

    public static function files($baseDir, $pattern)
    {

    }
}
