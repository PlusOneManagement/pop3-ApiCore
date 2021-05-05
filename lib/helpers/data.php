<?php


use Popcx\Business\Models\Business;

if(!function_exists('pop')){
    function pop()
    {
        return config('pop');
    }
}

/**
 *
 */

if(!function_exists('import')){
    function import($filepath, $extra = false)
    {
        $realpath = realpath($filepath);
        if(!$realpath){
            return null;
        }

        $extension = pathinfo($realpath, PATHINFO_EXTENSION);

        switch (strtolower($extension)) {
            case 'php':
                $import = require $realpath;
                break;
            case 'json':
                $json = file_get_contents($realpath);
                $import = json_decode($json, $extra);
                break;
            case 'csv':
                $import = parse_csv_file($realpath);
                break;
            default:
                $import = file_get_contents($realpath);
                break;
        }

        return $import;
    }
}
if(!function_exists('parse_csv_file')){
    function parse_csv_file($csv_file)
    {
        $csv_rows = array_map('str_getcsv', file($csv_file));
        $csv_keys = array_shift($csv_rows);

        $csv_data = [];
        foreach($csv_rows as $row){
            $csv_data[] = array_combine($csv_keys, $row);
        }
        return $csv_data;
    }
}
