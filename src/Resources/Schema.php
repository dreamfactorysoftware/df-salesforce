<?php
namespace DreamFactory\Core\Salesforce\Resources;

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Library\Utility\Inflector;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Exceptions\NotImplementedException;
use DreamFactory\Core\Resources\BaseDbSchemaResource;
use DreamFactory\Core\Utility\DbUtilities;
use DreamFactory\Core\Salesforce\Services\SalesforceDb;

class Schema extends BaseDbSchemaResource
{
    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var null|SalesforceDb
     */
    protected $service = null;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @return null|SalesforceDb
     */
    public function getService()
    {
        return $this->service;
    }

    /**
     * {@inheritdoc}
     */
    public function listResources($fields = null)
    {
//        $refresh = $this->request->queryBool('refresh');

        $_names = $this->service->getSObjects(true);

        if (empty($fields)) {
            return $this->cleanResources($_names);
        }

        $_extras =
            DbUtilities::getSchemaExtrasForTables($this->service->getServiceId(), $_names, false, 'table,label,plural');

        $_tables = [];
        foreach ($_names as $name) {
            $label = '';
            $plural = '';
            foreach ($_extras as $each) {
                if (0 == strcasecmp($name, ArrayUtils::get($each, 'table', ''))) {
                    $label = ArrayUtils::get($each, 'label');
                    $plural = ArrayUtils::get($each, 'plural');
                    break;
                }
            }

            if (empty($label)) {
                $label = Inflector::camelize($name, ['_', '.'], true);
            }

            if (empty($plural)) {
                $plural = Inflector::pluralize($label);
            }

            $_tables[] = ['name' => $name, 'label' => $label, 'plural' => $plural];
        }

        return $this->cleanResources($_tables, 'name', $fields);
    }

    /**
     * {@inheritdoc}
     */
    public function describeTable($table, $refresh = true)
    {
        $_name = (is_array($table)) ? ArrayUtils::get($table, 'name') : $table;

        try {
            $result = $this->service->callGuzzle('GET', 'sobjects/' . $table . '/describe');

            $_out = $result;
            $_out['access'] = $this->getPermissions($_name);

            return $_out;
        } catch (\Exception $_ex) {
            throw new InternalServerErrorException(
                "Failed to get table properties for table '$_name'.\n{$_ex->getMessage()}"
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function describeField($table, $field, $refresh = false)
    {
        $_result = $this->describeTable($table);
        $_fields = ArrayUtils::get($_result, 'fields');
        if (empty($_fields)) {
            foreach ($_fields as $_item) {
                if (ArrayUtils::get($_item, 'name') == $field) {
                    return $_item;
                }
            }
        }

        throw new NotFoundException("Field '$field' not found.");
    }

    /**
     * {@inheritdoc}
     */
    public function createTable($table, $properties = array(), $check_exist = false, $return_schema = false)
    {
        throw new NotImplementedException("Metadata actions currently not supported.");
    }

    /**
     * {@inheritdoc}
     */
    public function updateTable($table, $properties = array(), $allow_delete_fields = false, $return_schema = false)
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
    public function createField($table, $field, $properties = array(), $check_exist = false, $return_schema = false)
    {
        throw new NotImplementedException("Metadata actions currently not supported.");
    }

    /**
     * {@inheritdoc}
     */
    public function updateField(
        $table,
        $field,
        $properties = array(),
        $allow_delete_parts = false,
        $return_schema = false
    ){
        throw new NotImplementedException("Metadata actions currently not supported.");
    }

    /**
     * {@inheritdoc}
     */
    public function deleteField($table, $field)
    {
        throw new NotImplementedException("Metadata actions currently not supported.");
    }
}