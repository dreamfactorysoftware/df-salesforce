<?php
namespace DreamFactory\Core\Salesforce\Database\Schema;

use DreamFactory\Core\Database\Schema\ColumnSchema;
use DreamFactory\Core\Database\Schema\TableSchema;
use DreamFactory\Core\Enums\DbSimpleTypes;
use DreamFactory\Core\Exceptions\NotImplementedException;
use DreamFactory\Core\Salesforce\Services\Salesforce;
use Arr;

/**
 * Schema is the class for retrieving metadata information from a MongoDB database (version 4.1.x and 5.x).
 */
class Schema extends \DreamFactory\Core\Database\Components\Schema
{
    /**
     * @var Salesforce
     */
    protected $connection;

    /**
     * @inheritdoc
     */
    protected function loadTableColumns(TableSchema $table)
    {
        $result = $this->connection->callResource('sobjects', 'GET', $table->name . '/describe');

        if (!empty($columns = Arr::get($result, 'fields'))) {
            foreach ($columns as $column) {
                $column = array_change_key_case((array)$column, CASE_LOWER);
                $c = new ColumnSchema(Arr::only($column, ['name', 'label', 'precision', 'scale']));
                $c->quotedName = $this->quoteColumnName($c->name);
                $c->autoIncrement = Arr::get($column, 'autoNumber', false);
                $c->allowNull = Arr::get($column, 'nillable', false);
                $c->refTable = Arr::get($column, 'referenceTo');
                $c->isUnique = Arr::get($column, 'unique', false);
                $c->size = Arr::get($column, 'length');
                $c->dbType = Arr::get($column, 'type', 'string');
                $this->extractType($c, $c->dbType);
                $this->extractDefault($c, Arr::get($column, 'defaultvalue'));

                if ($c->isPrimaryKey) {
                    if ($c->autoIncrement) {
                        $table->sequenceName = Arr::get($column, 'sequence', $c->name);
                        if ((DbSimpleTypes::TYPE_INTEGER === $c->type)) {
                            $c->type = DbSimpleTypes::TYPE_ID;
                        }
                    }
                    $table->addPrimaryKey($c->name);
                }
                $table->addColumn($c);
            }
        }
    }

    /**
     * @inheritdoc
     */
    protected function getTableNames($schema = '')
    {
        $tables = [];
        $names = $this->connection->getSObjects(true);
        foreach ($names as $name) {
            $tables[strtolower($name)] = new TableSchema(['name' => $name]);
        }

        return $tables;
    }

    /**
     * @inheritdoc
     */
    public function createTable($table, $options)
    {
        throw new NotImplementedException("Metadata actions currently not supported.");
    }

    /**
     * @inheritdoc
     */
    public function updateTable($tableSchema, $changes)
    {
        throw new NotImplementedException("Metadata actions currently not supported.");
    }

    /**
     * @inheritdoc
     */
    public function dropTable($table)
    {
        throw new NotImplementedException("Metadata actions currently not supported.");
    }

    /**
     * @inheritdoc
     */
    public function dropColumns($table, $column)
    {
        // Do nothing here for now
        throw new NotImplementedException("Metadata actions currently not supported.");
    }

    /**
     * @inheritdoc
     */
    public function createFieldReferences($references)
    {
        // Do nothing here for now
        throw new NotImplementedException("Metadata actions currently not supported.");
    }

    /**
     * @inheritdoc
     */
    public function createFieldIndexes($indexes)
    {
        // Do nothing here for now
        throw new NotImplementedException("Metadata actions currently not supported.");
    }
}
