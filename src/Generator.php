<?php

namespace Code;

use Carbon\Carbon;
use Code\Structure\Column;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class Generator
{
    private string $tablePrefix;

    private array $propertyMap = [
        'tinyint' => 'int',
        'smallint' => 'int',
        'int' => 'int',
        'bigint' => 'int',
        'varchar' => 'string',
    ];

    public function __construct()
    {
        $this->tablePrefix = DB::getTablePrefix();
    }

    public function desc(): string
    {
        return "laravel code generator";
    }

    public function tableDesc(string $tableName)
    {
        $columnObjects = DB::select("desc `{$this->tablePrefix}{$tableName}`");

        $createTable = DB::select("show create table `{$this->tablePrefix}{$tableName}`");

        $selectResult = DB::select("select table_comment from information_schema.TABLES where TABLE_SCHEMA = 'great.qsl.dev' AND TABLE_NAME = '{$this->tablePrefix}{$tableName}'");
        $tableComment = empty($selectResult) ? '' : $selectResult[0]->TABLE_COMMENT;

        return [
            'table' => [
                'name' => $this->tablePrefix . $tableName,
                'comment' => $tableComment
            ],
            'columns' => collect($columnObjects)->map(function (object $columnObject) { return new Column($columnObject); }),
        ];
    }

    public function replaceTemplate(string $template, array $replaces)
    {
        foreach ($replaces as $search => $replace) {
            if($replace instanceof \Closure) $replace = $replace();
            $template = str_replace('{@' . $search . '@}', $replace, $template);
        }
        return $template;
    }

    public function generateModel(string $tableName): bool
    {
        // 查询表结构
        $table = $this->tableDesc($tableName);

        // 拆分表结构
        // 表名
        $tableName = $table['table']['name'];
        // 表注释
        $tableComment = $table['table']['comment'];
        /** @var Collection $columns 字段 */
        $columns = $table['columns'];

        // 获取数据表模型模板
        $modelTemplate = file_get_contents(__DIR__ . '/stubs/model.stub');

        // 设置变量
        $modelTemplate = $this->replaceTemplate($modelTemplate, [
            // 设置模型名称
            'model_name' => $table['table']['comment'],
            // 设置模型创建时间
            'model_create_time' => Carbon::now()->format('Y/m/d H:i'),
            // 设置模型属性定义
            'model_properties_define' => function() use($columns) {
                $properties = '';
                $columns->map(function(Column $column) use(&$properties) {
                    if(in_array($column->getField(), ['id', 'created_at', 'updated_at']))
                        return;
                    $properties .= " * @property int {$column->getField()}\n";
                });
                return rtrim($properties, "\n");
            },
        ]);

        // 生成模型文件
        $dirname = resource_path('/code');
        if(!is_dir($dirname))
            mkdir($dirname, 0777, true);

        $filename = $dirname . '/model.php';
        if(file_exists($filename))
            @unlink($filename);

        return (bool)file_put_contents($dirname . '/model.php', $modelTemplate);
    }
}
