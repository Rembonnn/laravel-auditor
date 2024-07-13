<?php

namespace Rembon\LaravelAuditor\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Yajra\DataTables\Facades\DataTables;

class DataTableService
{
    /**
     * @var array
     */
    protected $customableField = [];

    /**
     * @return DataTables
     */
    public function setupDataTables(Collection|array $data, array $actionUrl = [])
    {
        $dataTable = DataTables::of($data)
            ->addIndexColumn()
            ->addColumn('action', function ($row) use ($actionUrl) {
                if (!empty($actionUrl)) {
                    $viewUrl = str_replace('key', $row->id, $actionUrl['view']);

                    $actionButton = '<a href="'.$viewUrl.'" class="text-emerald-700 hover:text-white border border-emerald-700 hover:bg-emerald-800 focus:ring-4 focus:outline-none focus:ring-emerald-300 font-medium rounded-lg text-sm px-3 py-1.5 text-center me-2 mb-2">View</a>';

                    return $actionButton;
                }
            })
            ->rawColumns(['action']);

        foreach ($this->customableField as $field) {
            if (isset($field['field']) && isset($field['format'])) {
                $dataTable->addColumn($field['field'], function ($row) use ($field) {
                    return call_user_func($field['format'], $row->{$field['field']});
                });
            }
        }

        return $dataTable->make(true);
    }

    /**
     * @return self
     */
    public function setCustomableField(array $fields): self
    {
        $this->customableField = $fields;

        return $this;
    }
}
