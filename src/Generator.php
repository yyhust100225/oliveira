<?php

namespace Code;

use Carbon\Carbon;
use Code\Structure\Column;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Generator
{
    private string $tablePrefix;

    private array $propertyMap = [
        'int' => 'int',
        'varchar' => 'string',
        'text' => 'string',
        'time' => 'string',
        'date' => 'string',
        'decimal' => 'float',
        'json' => 'array'
    ];

    public function __construct()
    {
        $this->tablePrefix = DB::getTablePrefix();
    }

    private function tableDesc(string $tableName)
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

    private function replaceTemplate(string $template, array $replaces)
    {
        foreach ($replaces as $search => $replace) {
            if($replace instanceof \Closure) $replace = $replace();
            $template = str_replace('{@' . $search . '@}', $replace, $template);
        }
        return $template;
    }

    private function getColumnType(Column $column)
    {
        $mapKeys = array_keys($this->propertyMap);
        foreach ($mapKeys as $mapKey) {
            if(Str::contains($column->getType(), $mapKey)) {
                $columnType = $this->propertyMap[$mapKey];
                if($column->getNull() === 'YES')
                    $columnType = '?' . $columnType;
                return $columnType;
            }
        }
        return 'mixed';
    }

    private function getCamelTableName(string $tableName)
    {
        return Str::camel($tableName);
    }

    private function getModelName(string $tableName)
    {
        return ucfirst($this->getCamelTableName($tableName));
    }

    private function getModelClassName(string $tableName)
    {
        return ucfirst($this->getModelName($tableName)) . 'Model';
    }

    private function generateGettersAndSetters(Collection $columns): string
    {
        $gettersAndSetters = '';
        /** @var Column $column */
        foreach ($columns as $column) {
            if(in_array($column->getField(), ['id', 'created_at', 'updated_at']))
                continue;
            $MethodName = ucfirst(Str::camel($column->getField()));
            $gettersAndSetters .= "public function get{$MethodName}(): {$this->getColumnType($column)}\n\t{\n\t\treturn \$this->{$column->getField()};\n\t}\n\n\tpublic function set{$MethodName}({$this->getColumnType($column)} \${$column->getField()}): void \n\t{\n\t\t\$this->{$column->getField()} = \${$column->getField()};\n\t}\n\n\t";
        }
        return rtrim(ltrim($gettersAndSetters, "\n"), "\n\t");
    }

    public function generateModel(string $tableName): bool
    {
        // 查询表结构
        $table = $this->tableDesc($tableName);

        // 拆分表结构
        // 表注释
        $tableComment = $table['table']['comment'];
        /** @var Collection $columns 字段 */
        $columns = $table['columns'];

        // 获取数据表模型模板
        $modelTemplate = file_get_contents(__DIR__ . '/stubs/model.stub');

        // 设置变量
        $modelTemplate = $this->replaceTemplate($modelTemplate, [
            // 设置模型名称注释
            'model_comment' => $tableComment,
            // 设置模型创建时间
            'model_create_time' => Carbon::now()->format('Y/m/d H:i'),
            // 设置模型属性定义
            'model_properties_define' => function() use($columns) {
                $properties = '';
                $columns->map(function(Column $column) use(&$properties) {
                    if(in_array($column->getField(), ['id', 'created_at', 'updated_at']))
                        return;
                    $properties .= " * @property {$this->getColumnType($column)} {$column->getField()}\n";
                });
                return rtrim($properties, "\n");
            },
            // 设置类名
            'model_class_name' => $this->getModelClassName($tableName),
            // 设置表名
            'table_name' => Str::snake($tableName),
            // 设置getter和setter
            'getters_and_setters' => $this->generateGettersAndSetters($columns),
            // 替换其他变量
            'camel_table_name' => $this->getCamelTableName($tableName),
            'model_name' => $this->getModelName($tableName)
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
