<?php
namespace DreamFactory\Core\Salesforce\Resources;

use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Exceptions\NotImplementedException;
use DreamFactory\Core\Resources\BaseDbSchemaResource;
use DreamFactory\Core\Salesforce\Services\Salesforce;

class Schema extends BaseDbSchemaResource
{
    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var null|Salesforce
     */
    protected $parent = null;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @return null|Salesforce
     */
    public function getService()
    {
        return $this->parent;
    }

    /**
     * {@inheritdoc}
     */
    public function describeTable($table, $refresh = true)
    {
        $name = (is_array($table)) ? array_get($table, 'name') : $table;

        try {
            $result = $this->parent->callResource('sobjects', 'GET', $table . '/describe');

            $out = $result;
            $out['access'] = $this->getPermissions($name);

            return $out;
        } catch (\Exception $ex) {
            throw new InternalServerErrorException(
                "Failed to get table properties for table '$name'.\n{$ex->getMessage()}"
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function describeField($table, $field, $refresh = false)
    {
        $result = $this->describeTable($table);
        $fields = array_get($result, 'fields');
        if (empty($fields)) {
            foreach ($fields as $item) {
                if (array_get($item, 'name') == $field) {
                    return $item;
                }
            }
        }

        throw new NotFoundException("Field '$field' not found.");
    }

    /**
     * {@inheritdoc}
     */
    public function describeRelationship($table, $relationship, $refresh = false)
    {
        $result = $this->describeTable($table);
        $fields = array_get($result, 'related');
        if (empty($fields)) {
            foreach ($fields as $item) {
                if (array_get($item, 'name') == $relationship) {
                    return $item;
                }
            }
        }

        throw new NotFoundException("Relationship '$relationship' not found.");
    }

    /**
     * {@inheritdoc}
     */
    public function createTable($table, $properties = [], $check_exist = false, $return_schema = false)
    {
        throw new NotImplementedException("Metadata actions currently not supported.");
    }

    /**
     * {@inheritdoc}
     */
    public function updateTable($table, $properties = [], $allow_delete_fields = false, $return_schema = false)
    {
        throw new NotImplementedException("Metadata actions currently not supported.");
    }

    /**
     * {@inheritdoc}
     */
    public function deleteTable($table, $check_empty = false)
    {
        throw new NotImplementedException("Metadata actions currently not supported.");
    }

    /**
     * {@inheritdoc}
     */
    public function createField($table, $field, $properties = [], $check_exist = false, $return_schema = false)
    {
        throw new NotImplementedException("Metadata actions currently not supported.");
    }

    /**
     * {@inheritdoc}
     */
    public function createRelationship(
        $table,
        $relationship,
        $properties = [],
        $check_exist = false,
        $return_schema = false
    ) {
        throw new NotImplementedException("Metadata actions currently not supported.");
    }

    /**
     * {@inheritdoc}
     */
    public function updateField(
        $table,
        $field,
        $properties = [],
        $allow_delete_parts = false,
        $return_schema = false
    ) {
        throw new NotImplementedException("Metadata actions currently not supported.");
    }

    /**
     * {@inheritdoc}
     */
    public function updateRelationship(
        $table,
        $relationship,
        $properties = [],
        $allow_delete_parts = false,
        $return_schema = false
    ) {
        throw new NotImplementedException("Metadata actions currently not supported.");
    }

    /**
     * {@inheritdoc}
     */
    public function deleteField($table, $field)
    {
        throw new NotImplementedException("Metadata actions currently not supported.");
    }

    /**
     * {@inheritdoc}
     */
    public function deleteRelationship($table, $relationship)
    {
        throw new NotImplementedException("Metadata actions currently not supported.");
    }
}