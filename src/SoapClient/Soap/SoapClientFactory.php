<?php
namespace DreamFactory\Core\Salesforce\SoapClient\Soap;

use DreamFactory\Core\Salesforce\SoapClient\Soap\TypeConverter;

/**
 * Factory to create a \SoapClient properly configured for the Salesforce SOAP
 * client
 */
class SoapClientFactory
{
    /**
     * Default classmap
     *
     * @var array
     */
    protected $classmap = [
        'Error'                    => 'DreamFactory\Core\Salesforce\SoapClient\Result\Error',
        'GetUserInfoResult'        => 'DreamFactory\Core\Salesforce\SoapClient\Result\GetUserInfoResult',
        'LoginResult'              => 'DreamFactory\Core\Salesforce\SoapClient\Result\LoginResult',
    ];

    /**
     * Type converters collection
     *
     * @var TypeConverter\TypeConverterCollection
     */
    protected $typeConverters;

    /**
     * @param string $wsdl Path to WSDL file
     * @param array  $soapOptions
     * @return SoapClient
     */
    public function factory($wsdl, array $soapOptions = [])
    {
        $defaults = [
            'trace'      => 1,
            'features'   => \SOAP_SINGLE_ELEMENT_ARRAYS,
            'classmap'   => $this->classmap,
            'typemap'    => $this->getTypeConverters()->getTypemap(),
            'cache_wsdl' => \WSDL_CACHE_MEMORY
        ];

        $options = array_merge($defaults, $soapOptions);

        return new SoapClient($wsdl, $options);
    }

    /**
     * test
     *
     * @param string $soap SOAP class
     * @param string $php  PHP class
     */
    public function setClassmapping($soap, $php)
    {
        $this->classmap[$soap] = $php;
    }

    /**
     * Get type converter collection that will be used for the \SoapClient
     *
     * @return TypeConverter\TypeConverterCollection
     */
    public function getTypeConverters()
    {
        if (null === $this->typeConverters) {
            $this->typeConverters = new TypeConverter\TypeConverterCollection(
                [
                    new TypeConverter\DateTimeTypeConverter(),
                    new TypeConverter\DateTypeConverter()
                ]
            );
        }

        return $this->typeConverters;
    }

    /**
     * Set type converter collection
     *
     * @param TypeConverter\TypeConverterCollection $typeConverters Type converter collection
     *
     * @return SoapClientFactory
     */
    public function setTypeConverters(TypeConverter\TypeConverterCollection $typeConverters)
    {
        $this->typeConverters = $typeConverters;

        return $this;
    }
}
