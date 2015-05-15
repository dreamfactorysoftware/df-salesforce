<?php
/**
 * This file is part of the DreamFactory Rave(tm)
 *
 * DreamFactory Rave(tm) <http://github.com/dreamfactorysoftware/rave>
 * Copyright 2012-2014 DreamFactory Software, Inc. <support@dreamfactory.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace DreamFactory\Rave\Salesforce\Resources;

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Library\Utility\Inflector;
use DreamFactory\Rave\Exceptions\BadRequestException;
use DreamFactory\Rave\Exceptions\InternalServerErrorException;
use DreamFactory\Rave\Exceptions\NotFoundException;
use DreamFactory\Rave\Exceptions\RestException;
use DreamFactory\Rave\Resources\BaseDbTableResource;
use DreamFactory\Rave\Utility\DbUtilities;
use DreamFactory\Rave\Salesforce\Services\SalesforceDb;

class Table extends BaseDbTableResource
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

        $_names = $this->service->getSObjects();

        if (empty($fields))
        {
            return ['resource' => $_names];
        }

        $_extras = DbUtilities::getSchemaExtrasForTables( $this->service->getServiceId(), $_names, false, 'table,label,plural' );

        $_tables = [];
        foreach ( $_names as $name )
        {
            $label = '';
            $plural = '';
            foreach ( $_extras as $each )
            {
                if ( 0 == strcasecmp( $name, ArrayUtils::get( $each, 'table', '' ) ) )
                {
                    $label = ArrayUtils::get( $each, 'label' );
                    $plural = ArrayUtils::get( $each, 'plural' );
                    break;
                }
            }

            if ( empty( $label ) )
            {
                $label = Inflector::camelize( $name, ['_','.'], true );
            }

            if ( empty( $plural ) )
            {
                $plural = Inflector::pluralize( $label );
            }

            $_tables[] = ['name' => $name, 'label' => $label, 'plural' => $plural];
        }

        return $this->makeResourceList($_tables, 'name', $fields, 'resource' );
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveRecordsByFilter( $table, $filter = null, $params = array(), $extras = array() )
    {
        $_fields = ArrayUtils::get( $extras, 'fields' );
        $_idField = ArrayUtils::get( $extras, 'id_field' );
        $fields = $this->_buildFieldList( $table, $_fields, $_idField );

        $_next = ArrayUtils::get( $extras, 'next' );
        if ( !empty( $_next ) )
        {
            $_result = $this->service->callGuzzle( 'GET', 'query/' . $_next );
        }
        else
        {
            // build query string
            $_query = 'SELECT ' . $fields . ' FROM ' . $table;

            if ( !empty( $filter ) )
            {
                $_query .= ' WHERE ' . $filter;
            }

            $_order = ArrayUtils::get( $extras, 'order' );
            if ( !empty( $_order ) )
            {
                $_query .= ' ORDER BY ' . $_order;
            }

            $_offset = intval( ArrayUtils::get( $extras, 'offset', 0 ) );
            if ( $_offset > 0 )
            {
                $_query .= ' OFFSET ' . $_offset;
            }

            $_limit = intval( ArrayUtils::get( $extras, 'limit', 0 ) );
            if ( $_limit > 0 )
            {
                $_query .= ' LIMIT ' . $_limit;
            }

            $_result = $this->service->callGuzzle( 'GET', 'query', array('q' => $_query) );
        }

        $_data = ArrayUtils::get( $_result, 'records', array() );

        $_includeCount = ArrayUtils::getBool( $extras, 'include_count', false );
        $_moreToken = ArrayUtils::get( $_result, 'nextRecordsUrl' );
        if ( $_includeCount || $_moreToken )
        {
            // count total records
            $_data['meta']['count'] = intval( ArrayUtils::get( $_result, 'totalSize' ) );
            if ( $_moreToken )
            {
                $_data['meta']['next'] = substr( $_moreToken, strrpos( $_moreToken, '/' ) + 1 );
            }
        }

        return $_data;
    }

    protected function getFieldsInfo( $table )
    {
        $_result = $this->service->callGuzzle( 'GET', 'sobjects/' . $table . '/describe' );
        $_result = ArrayUtils::get( $_result, 'fields' );
        if ( empty( $_result ) )
        {
            return array();
        }

        $_fields = array();
        foreach ( $_result as $_field )
        {
            $_fields[] = ArrayUtils::get( $_field, 'name' );
        }

        return $_result;
    }

    protected function getIdsInfo( $table, $fields_info = null, &$requested_fields = null, $requested_types = null )
    {
        $requested_fields = static::DEFAULT_ID_FIELD; // can only be this
        $requested_types = ArrayUtils::clean( $requested_types );
        $_type = ArrayUtils::get( $requested_types, 0, 'string' );
        $_type = ( empty( $_type ) ) ? 'string' : $_type;

        return array(array('name' => static::DEFAULT_ID_FIELD, 'type' => $_type, 'required' => false));
    }

    /**
     * @param      $table
     * @param bool $as_array
     *
     * @return array|string
     */
    protected function _getAllFields( $table, $as_array = false )
    {
        $_result = $this->service->callGuzzle( 'GET', 'sobjects/' . $table . '/describe' );
        $_result = ArrayUtils::get( $_result, 'fields' );
        if ( empty( $_result ) )
        {
            return array();
        }

        $_fields = array();
        foreach ( $_result as $_field )
        {
            $_fields[] = ArrayUtils::get( $_field, 'name' );
        }

        if ( $as_array )
        {
            return $_fields;
        }

        return implode( ',', $_fields );
    }

    /**
     * @param      $table
     * @param null $fields
     * @param null $id_field
     *
     * @return array|null|string
     */
    protected function _buildFieldList( $table, $fields = null, $id_field = null )
    {
        if ( empty( $id_field ) )
        {
            $id_field = static::DEFAULT_ID_FIELD;
        }

        if ( empty( $fields ) )
        {
            $fields = $id_field;
        }
        elseif ( '*' == $fields )
        {
            $fields = $this->_getAllFields( $table );
        }
        else
        {
            if ( is_array( $fields ) )
            {
                $fields = implode( ',', $fields );
            }

            // make sure the Id field is always returned
            if ( false === array_search(
                    strtolower( $id_field ),
                    array_map(
                        'trim',
                        explode( ',', strtolower( $fields ) )
                    )
                )
            )
            {
                $fields = array_map( 'trim', explode( ',', $fields ) );
                $fields[] = $id_field;
                $fields = implode( ',', $fields );
            }
        }

        return $fields;
    }

    /**
     * {@inheritdoc}
     */
    protected function initTransaction( $handle = null )
    {

        return parent::initTransaction( $handle );
    }

    /**
     * {@inheritdoc}
     */
    protected function addToTransaction( $record = null, $id = null, $extras = null, $rollback = false, $continue = false, $single = false )
    {
        $_fields = ArrayUtils::get( $extras, 'fields' );
        $_fieldsInfo = ArrayUtils::get( $extras, 'fields_info' );
        $_ssFilters = ArrayUtils::get( $extras, 'ss_filters' );
        $_updates = ArrayUtils::get( $extras, 'updates' );
        $_idsInfo = ArrayUtils::get( $extras, 'ids_info' );
        $_idFields = ArrayUtils::get( $extras, 'id_fields' );
        $_needToIterate = ( $single || $continue || ( 1 < count( $_idsInfo ) ) );
        $_requireMore = ArrayUtils::getBool( $extras, 'require_more' );

        $_client = $this->service->getGuzzleClient();

        $_out = array();
        switch ( $this->getAction() )
        {
            case Verbs::POST:
                $_parsed = $this->parseRecord( $record, $_fieldsInfo, $_ssFilters );
                if ( empty( $_parsed ) )
                {
                    throw new BadRequestException( 'No valid fields were found in record.' );
                }

                $_native = json_encode( $_parsed );
                $_result =
                    $this->service->callGuzzle( 'POST', 'sobjects/' . $this->_transactionTable . '/', null, $_native, $_client );
                if ( !ArrayUtils::getBool( $_result, 'success', false ) )
                {
                    $_msg = json_encode( ArrayUtils::get( $_result, 'errors' ) );
                    throw new InternalServerErrorException( "Record insert failed for table '$this->_transactionTable'.\n" .
                                                            $_msg );
                }

                $id = ArrayUtils::get( $_result, 'id' );

                // add via record, so batch processing can retrieve extras
                return ( $_requireMore ) ? parent::addToTransaction( $id ) : array($_idFields => $id);

            case Verbs::PUT:
            case Verbs::MERGE:
            case Verbs::PATCH:
                if ( !empty( $_updates ) )
                {
                    $record = $_updates;
                }

                $_parsed = $this->parseRecord( $record, $_fieldsInfo, $_ssFilters, true );
                if ( empty( $_parsed ) )
                {
                    throw new BadRequestException( 'No valid fields were found in record.' );
                }

                static::removeIds( $_parsed, $_idFields );
                $_native = json_encode( $_parsed );

                $_result = $this->service->callGuzzle(
                    'PATCH',
                    'sobjects/' . $this->_transactionTable . '/' . $id,
                    null,
                    $_native,
                    $_client
                );
                if ( $_result && !ArrayUtils::getBool( $_result, 'success', false ) )
                {
                    $msg = ArrayUtils::get( $_result, 'errors' );
                    throw new InternalServerErrorException( "Record update failed for table '$this->_transactionTable'.\n" .
                                                            $msg );
                }

                // add via record, so batch processing can retrieve extras
                return ( $_requireMore ) ? parent::addToTransaction( $id ) : array($_idFields => $id);

            case Verbs::DELETE:
                $_result = $this->service->callGuzzle(
                    'DELETE',
                    'sobjects/' . $this->_transactionTable . '/' . $id,
                    null,
                    null,
                    $_client
                );
                if ( $_result && !ArrayUtils::getBool( $_result, 'success', false ) )
                {
                    $msg = ArrayUtils::get( $_result, 'errors' );
                    throw new InternalServerErrorException( "Record delete failed for table '$this->_transactionTable'.\n" .
                                                            $msg );
                }

                // add via record, so batch processing can retrieve extras
                return ( $_requireMore ) ? parent::addToTransaction( $id ) : array($_idFields => $id);

            case Verbs::GET:
                if ( !$_needToIterate )
                {
                    return parent::addToTransaction( null, $id );
                }

                $_fields = $this->_buildFieldList( $this->_transactionTable, $_fields, $_idFields );

                $_result = $this->service->callGuzzle(
                    'GET',
                    'sobjects/' . $this->_transactionTable . '/' . $id,
                    array('fields' => $_fields)
                );
                if ( empty( $_result ) )
                {
                    throw new NotFoundException( "Record with identifier '" . print_r( $id, true ) . "' not found." );
                }

                $_out = $_result;
                break;
        }

        return $_out;
    }

    /**
     * {@inheritdoc}
     */
    protected function commitTransaction( $extras = null )
    {
        if ( empty( $this->_batchRecords ) && empty( $this->_batchIds ) )
        {
            if ( isset( $this->_transaction ) )
            {
                $this->_transaction->commit();
            }

            return null;
        }

        $_fields = ArrayUtils::get( $extras, 'fields' );
        $_idsInfo = ArrayUtils::get( $extras, 'ids_info' );
        $_idFields = ArrayUtils::get( $extras, 'id_fields' );

        $_out = array();
        $_action = $this->getAction();
        if ( !empty( $this->_batchRecords ) )
        {
            if ( 1 == count( $_idsInfo ) )
            {
                // records are used to retrieve extras
                // ids array are now more like records
                $_fields = $this->_buildFieldList( $this->_transactionTable, $_fields, $_idFields );

                $_idList = "('" . implode( "','", $this->_batchRecords ) . "')";
                $_query =
                    'SELECT ' .
                    $_fields .
                    ' FROM ' .
                    $this->_transactionTable .
                    ' WHERE ' .
                    $_idFields .
                    ' IN ' .
                    $_idList;

                $_result = $this->service->callGuzzle( 'GET', 'query', array('q' => $_query) );

                $_out = ArrayUtils::get( $_result, 'records', array() );
                if ( empty( $_out ) )
                {
                    throw new NotFoundException( 'No records were found using the given identifiers.' );
                }
            }
            else
            {
                $_out = $this->retrieveRecords( $this->_transactionTable, $this->_batchRecords, $extras );
            }

            $this->_batchRecords = array();
        }
        elseif ( !empty( $this->_batchIds ) )
        {
            switch ( $_action )
            {
                case Verbs::PUT:
                case Verbs::MERGE:
                case Verbs::PATCH:
                    break;

                case Verbs::DELETE:
                    break;

                case Verbs::GET:
                    $_fields = $this->_buildFieldList( $this->_transactionTable, $_fields, $_idFields );

                    $_idList = "('" . implode( "','", $this->_batchIds ) . "')";
                    $_query =
                        'SELECT ' .
                        $_fields .
                        ' FROM ' .
                        $this->_transactionTable .
                        ' WHERE ' .
                        $_idFields .
                        ' IN ' .
                        $_idList;

                    $_result = $this->service->callGuzzle( 'GET', 'query', array('q' => $_query) );

                    $_out = ArrayUtils::get( $_result, 'records', array() );
                    if ( empty( $_out ) )
                    {
                        throw new NotFoundException( 'No records were found using the given identifiers.' );
                    }

                    break;

                default:
                    break;
            }

            if ( empty( $_out ) )
            {
                $_out = $this->_batchIds;
            }

            $this->_batchIds = array();
        }

        return $_out;
    }

    /**
     * {@inheritdoc}
     */
    protected function rollbackTransaction()
    {
        if ( !empty( $this->_rollbackRecords ) )
        {
            switch ( $this->getAction() )
            {
                case Verbs::POST:
                    break;

                case Verbs::PUT:
                case Verbs::PATCH:
                case Verbs::MERGE:
                case Verbs::DELETE:
                    break;

                default:
                    break;
            }

            $this->_rollbackRecords = array();
        }

        return true;
    }
}