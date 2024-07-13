<?php

namespace Rembon\LaravelAuditor\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

class BaseController extends Controller
{
    /**
     * @return array
     */
    public function getAllModels(): array
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

    /**
     * @param string $filename
     * @return array
     */
    public function accessMigrationData(string $filename): array
    {
        $filePath = database_path('migrations/' . $filename);

        if (!File::exists($filePath)) return [];

        $fileContent = File::get($filePath);
        preg_match_all('/\$table->(\w+)\(([^)]+)\)/', $fileContent, $matches, PREG_SET_ORDER);

        $columns = [];
        foreach ($matches as $match) {
            $type = $match[1];
            $params = str_getcsv($match[2]);
            $name = trim($params[0], "'");

            $column = [
                'name' => $name,
                'type' => $type,
                'length' => isset($params[1]) ? $params[1] : null,
            ];

            $columns[] = $column;
        }

        return $columns;
    }

    /**
     * @return array
     */
    public function getAllRouteList(): array
    {
        $routes = Route::getRoutes();
        $routeList = [];

        foreach ($routes as $route) {
            $routeList[] = [
                'method' => implode('|', $route->methods()),
                'uri' => $route->uri(),
                'name' => $route->getName(),
            ];
        }

        return $routeList;
    }
}
