<?php

namespace Rembon\LaravelAuditor\Http\Controllers;

use App\Http\Controllers\Controller;

class BaseController extends Controller
{
    public function getAllModels()
    {
        $modelList = [];
        $path = app_path() . "/Models";
        $results = scandir($path);

        foreach ($results as $result) {
            if ($result === '.' or $result === '..') continue;
            $filename = $result;
            if (is_dir($filename)) {
                $modelList = array_merge($modelList, getModels($filename));
            } else {
                $modelList[] = substr($filename, 0, -4);
            }
        }

        return $modelList;
    }
}
