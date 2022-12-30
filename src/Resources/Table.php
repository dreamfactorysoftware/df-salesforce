<?php

namespace DreamFactory\Core\Salesforce\Resources;

use DreamFactory\Core\Database\Schema\ColumnSchema;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Database\Resources\BaseNoSqlDbTableResource;
use DreamFactory\Core\Salesforce\Services\Salesforce;
use DreamFactory\Core\Enums\Verbs;
use Arr;

class Table extends BaseNoSqlDbTableResource
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * Default record identifier field
     */
    const DEFAULT_ID_FIELD = 'Id';

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
    public function retrieveRecordsByFilter($table, $filter = null, $params = [], $extras = [])
    {
        $fields = Arr::get($extras, ApiOptions::FIELDS);
        $idField = Arr::get($extras, ApiOptions::ID_FIELD);
        $countOnly = array_get_bool($extras, ApiOptions::COUNT_ONLY);
        $includeCount = array_get_bool($extras, ApiOptions::INCLUDE_COUNT);

        $next = Arr::get($extras, 'next');
        $count = 0;

        /**
         * Build list of fields
         */
        $fields = $this->buildFieldList($table, $fields, $idField);

        /**
         * Get total counts if needed (with conditions)
         */
        if ($countOnly || $includeCount || $next) {
            // Build select with count() only
            $query = $this->buildConditionsStr($table, $fields, $filter, $extras, true);
            if ($qResult = $this->parent->callResource('query', 'GET', null, ['q' => $query])) {
                $count = intval($qResult['totalSize']);
            }
        }

        if ($countOnly) {
            return $count;
        }

        /**
         * Build normal select w/ fields
         */
        $query = $this->buildConditionsStr($table, $fields, $filter, $extras);

        if (!empty($next)) {
            $result = $this->parent->callResource('query', 'GET', $next);
        } else {
            $result = $this->parent->callResource('query', 'GET', null, ['q' => $query]);
        }

        // SF will always include totalSize
        $data = Arr::get($result, 'records', []);

        $moreToken = Arr::get($result, 'nextRecordsUrl');

        if ($includeCount || $moreToken) {
            // count total records
            $data['meta']['count'] = $count;
            if ($moreToken) {
                $data['meta']['next'] = substr($moreToken, strrpos($moreToken, '/') + 1);
            }
        }

        return $data;
    }

    protected function buildConditionsStr($table, $fields, $filter, $extras, $countOnly = false)
    {
        $order = Arr::get($extras, ApiOptions::ORDER);
        $offset = intval(Arr::get($extras, ApiOptions::OFFSET, 0));
        $limit = intval(Arr::get($extras, ApiOptions::LIMIT, 0));

        // build query string either count or fields
        if ($countOnly === true) {
            $queryStr = 'SELECT COUNT() FROM ' . $table;
        } else {
            $queryStr = 'SELECT ' . $fields . ' FROM ' . $table;
        }

        if (!empty($filter)) {
            $queryStr .= ' WHERE ' . $filter;
        }
        if (!$countOnly) {
            if (!empty($order)) {
                $queryStr .= ' ORDER BY ' . $order;
            }
            if ($limit > 0) {
                $queryStr .= ' LIMIT ' . $limit;
            }
            if ($offset > 0) {
                $queryStr .= ' OFFSET ' . $offset;
            }
        }

        return $queryStr;
    }

    protected function getFieldsInfo($table)
    {
        $result = $this->parent->callResource('sobjects', 'GET', $table . '/describe');
        $result = Arr::get($result, ApiOptions::FIELDS);
        if (empty($result)) {
            return [];
        }

        $fields = [];
        foreach ($result as $field) {
            $fields[] = new ColumnSchema($field);
        }

        return $fields;
    }

    protected function getIdsInfo($table, $fields_info = null, &$requested_fields = null, $requested_types = null)
    {
        $requested_fields = static::DEFAULT_ID_FIELD; // can only be this
        $requested_types = (array)$requested_types;
        $type = Arr::get($requested_types, 0, 'string');
        $type = (empty($type)) ? 'string' : $type;

        return [new ColumnSchema(['name' => static::DEFAULT_ID_FIELD, 'type' => $type, 'required' => false])];
    }

    /**
     * @param      $table
     * @param bool $as_array
     *
     * @return array|string
     */
    protected function getAllFields($table, $as_array = false)
    {
        $result = $this->parent->callResource('sobjects', 'GET', $table . '/describe');
        $result = Arr::get($result, ApiOptions::FIELDS);
        if (empty($result)) {
            return [];
        }

        $fields = [];
        foreach ($result as $field) {
            $fields[] = Arr::get($field, 'name');
        }

        if ($as_array) {
            return $fields;
        }

        return implode(',', $fields);
    }

    /**
     * @param      $table
     * @param null $fields
     * @param null $id_field
     *
     * @return array|null|string
     */
    protected function buildFieldList($table, $fields = null, $id_field = null)
    {
        if (empty($id_field)) {
            $id_field = static::DEFAULT_ID_FIELD;
        }

        if (empty($fields)) {
            $fields = $id_field;
        } elseif (ApiOptions::FIELDS_ALL == $fields) {
            $fields = $this->getAllFields($table);
        } else {
            if (is_array($fields)) {
                $fields = implode(',', $fields);
            }

            // make sure the Id field is always returned
            if (false === array_search(
                    strtolower($id_field),
                    array_map(
                        'trim',
                        explode(',', strtolower($fields))
                    )
                )
            ) {
                $fields = array_map('trim', explode(',', $fields));
                $fields[] = $id_field;
                $fields = implode(',', $fields);
            }
        }

        return $fields;
    }

    protected function parseValueForSet($value, $field_info, $for_update = false)
    {
        // base class does too much datetime stuff here
        return $value;
    }

    /**
     * {@inheritdoc}
     */
    protected function addToTransaction(
        $record = null,
        $id = null,
        $extras = null,
        $rollback = false,
        $continue = false,
        $single = false
    ){
        $fields = Arr::get($extras, ApiOptions::FIELDS);
        $ssFilters = Arr::get($extras, 'ss_filters');
        $updates = Arr::get($extras, 'updates');
        $idFields = Arr::get($extras, 'id_fields');
        $needToIterate = ($single || $continue || (1 < count($this->tableIdsInfo)));
        $requireMore = array_get_bool($extras, 'require_more');

        $out = [];
        switch ($this->getAction()) {
            case Verbs::POST:
                $parsed = $this->parseRecord($record, $this->tableFieldsInfo, $ssFilters);
                if (empty($parsed)) {
                    throw new BadRequestException('No valid fields were found in record.');
                }

                $native = json_encode($parsed);
                $result = $this->parent->callResource('sobjects', 'POST', $this->transactionTable . '/', null, $native);
                if (!array_get_bool($result, 'success')) {
                    $msg = json_encode(Arr::get($result, 'errors'));
                    throw new InternalServerErrorException("Record insert failed for table '$this->transactionTable'.\n" .
                        $msg);
                }

                $id = Arr::get($result, 'id');

                // add via record, so batch processing can retrieve extras
                return ($requireMore) ? parent::addToTransaction($id) : [$idFields => $id];

            case Verbs::PUT:
            case Verbs::PATCH:
                if (!empty($updates)) {
                    $record = $updates;
                }

                $parsed = $this->parseRecord($record, $this->tableFieldsInfo, $ssFilters, true);
                if (empty($parsed)) {
                    throw new BadRequestException('No valid fields were found in record.');
                }

                static::removeIds($parsed, $idFields);
                $native = json_encode($parsed);

                $result = $this->parent->callResource('sobjects', 'PATCH', $this->transactionTable . '/' . $id, null,
                    $native);
                if ($result && !array_get_bool($result, 'success')) {
                    $msg = Arr::get($result, 'errors');
                    throw new InternalServerErrorException("Record update failed for table '$this->transactionTable'.\n" .
                        $msg);
                }

                // add via record, so batch processing can retrieve extras
                return ($requireMore) ? parent::addToTransaction($id) : [$idFields => $id];

            case Verbs::DELETE:
                $result = $this->parent->callResource('sobjects', 'DELETE', $this->transactionTable . '/' . $id);
                if ($result && !array_get_bool($result, 'success')) {
                    $msg = Arr::get($result, 'errors');
                    throw new InternalServerErrorException("Record delete failed for table '$this->transactionTable'.\n" .
                        $msg);
                }

                // add via record, so batch processing can retrieve extras
                return ($requireMore) ? parent::addToTransaction($id) : [$idFields => $id];

            case Verbs::GET:
                if (!$needToIterate) {
                    return parent::addToTransaction(null, $id);
                }

                $fields = $this->buildFieldList($this->transactionTable, $fields, $idFields);

                $result = $this->parent->callResource('sobjects', 'GET', $this->transactionTable . '/' . $id,
                    ['fields' => $fields]);
                if (empty($result)) {
                    throw new NotFoundException("Record with identifier '" . print_r($id, true) . "' not found.");
                }

                $out = $result;
                break;
        }

        return $out;
    }

    /**
     * {@inheritdoc}
     */
    protected function commitTransaction($extras = null)
    {
        if (empty($this->batchRecords) && empty($this->batchIds)) {
            if (isset($this->transaction)) {
                $this->transaction->commit();
            }

            return null;
        }

        $fields = Arr::get($extras, ApiOptions::FIELDS);
        $idFields = Arr::get($extras, 'id_fields');

        $out = [];
        $action = $this->getAction();
        if (!empty($this->batchRecords)) {
            if (1 == count($this->tableIdsInfo)) {
                // records are used to retrieve extras
                // ids array are now more like records
                $fields = $this->buildFieldList($this->transactionTable, $fields, $idFields);

                $idList = "('" . implode("','", $this->batchRecords) . "')";
                $query =
                    'SELECT ' .
                    $fields .
                    ' FROM ' .
                    $this->transactionTable .
                    ' WHERE ' .
                    $idFields .
                    ' IN ' .
                    $idList;

                $result = $this->parent->callResource('query', 'GET', null, ['q' => $query]);

                $out = Arr::get($result, 'records', []);
                if (empty($out)) {
                    throw new NotFoundException('No records were found using the given identifiers.');
                }
            } else {
                $out = $this->retrieveRecords($this->transactionTable, $this->batchRecords, $extras);
            }

            $this->batchRecords = [];
        } elseif (!empty($this->batchIds)) {
            switch ($action) {
                case Verbs::PUT:
                case Verbs::PATCH:
                    break;

                case Verbs::DELETE:
                    break;

                case Verbs::GET:
                    $fields = $this->buildFieldList($this->transactionTable, $fields, $idFields);

                    $idList = "('" . implode("','", $this->batchIds) . "')";
                    $query =
                        'SELECT ' .
                        $fields .
                        ' FROM ' .
                        $this->transactionTable .
                        ' WHERE ' .
                        $idFields .
                        ' IN ' .
                        $idList;

                    $result = $this->parent->callResource('query', 'GET', null, ['q' => $query]);

                    $out = Arr::get($result, 'records', []);
                    if (empty($out)) {
                        throw new NotFoundException('No records were found using the given identifiers.');
                    }

                    break;

                default:
                    break;
            }

            if (empty($out)) {
                $out = $this->batchIds;
            }

            $this->batchIds = [];
        }

        return $out;
    }

    /**
     * {@inheritdoc}
     */
    protected function rollbackTransaction()
    {
        if (!empty($this->rollbackRecords)) {
            switch ($this->getAction()) {
                case Verbs::POST:
                    break;

                case Verbs::PUT:
                case Verbs::PATCH:
                case Verbs::DELETE:
                    break;

                default:
                    break;
            }

            $this->rollbackRecords = [];
        }

        return true;
    }
}