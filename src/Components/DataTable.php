<?php

namespace Rembon\LaravelAuditor\Components;

use Illuminate\View\Component;

class DataTable extends Component
{
    public $id;
    public $title;
    public $columns;
    public $ajaxUrl;
    public $columnDefs;

    public function __construct($id, $title, $columns, $ajaxUrl, $columnDefs)
    {
        $this->id = $id;
        $this->title = $title;
        $this->columns = $columns;
        $this->ajaxUrl = $ajaxUrl;
        $this->columnDefs = $columnDefs;
    }

    public function render()
    {
        return view('auditor::components.data-table');
    }
}
