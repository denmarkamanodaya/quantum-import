<?php

namespace Import\Framework\ETL\Profile;

use Import\Framework\ETL\Profile\Extract\Data\Adapter\AbstractAdapter;

class Extract extends AbstractProfile
{


    /**
     * @var string Database config setting
     */
    protected $_dbConfigName = 'etl|extract';

    /**
     * @var AbstractAdapter Source data to map
     */
    protected $_data;

    /**
     * @var array Optional runtime variables
     */
    protected $_runtimeVars = array();


    /**
     * Set raw data to map
     * @param $data
     * @return Extract
     * @throws Extract\Exception
     * @throws \Import\Framework\ETL\Exception
     */
    public function setData($data)
    {
        $this->_data = Extract\Data::factoryByFormat($this->getInputFormat());
        $this->_data->loadData($data);
        return $this;
    }


    /**
     * Set runtime variables
     *
     * This method allows you to supply an array of mapped data that can be used
     * in the ETL process.  For example, the data you're mapping may come from
     * SiteID 42 but that number isn't anywhere is the source document.  By
     * passing and array `array("SourceID"=>42)` you can then use SourceID
     * within your ETL.
     *
     * @param array $runtimeVars
     * @return Extract
     * @throws Exception
     */
    public function setRuntimeVars($runtimeVars)
    {
        if (!is_array($runtimeVars)) {
            throw new Exception("Run time variables must be set as an associative array.");
        }

        $this->_runtimeVars = $runtimeVars;
        return $this;
    }


    /**
     * Extract source data
     *
     * This is the first step in the extraction process where we attempt to
     * break down the underlying data structure into name/value pairs.
     *
     * NOTE: We support redundant lookups.  This means you could supply 2 or
     * more paths to check for a given extracted field.  Our code below will
     * always save the most recent/last match.  For example if you define 2 ways
     * to find a value that both map to a value in the source, only the last
     * match for the field is preserved.  This is particularly helpful for
     * BulkPost XMLs where there are 2 different ways clients typically provide
     * us the item name due to a vague spec.
     *
     * If no match is found for a lookup, we will create a NULL field entry for
     * it to ensure the transform and validate steps are not impacted unduly.
     *
     * @throws Exception
     * @return array
     */
    public function getExtractedData()
    {
        if (!$this->_data instanceof AbstractAdapter) {
            throw new Exception("Cannot extract data until source data is set.");
        }

        $extractedData = array();

        /*
         * Allow an arbitrary list of runtime variables to be used in the
         * data transformation process.  Runtime variables can be used in
         * transformations using the "_runtime_" prefix and then the runtime
         * variable name.  Ex: {{_runtime_userId}}
         */
        if (is_array($this->_runtimeVars) && count($this->_runtimeVars) > 0) {
            foreach ($this->_runtimeVars as $key => $value) {
                $extractedData[$key] = $value;
            }
        }


        /*
         * Run the extraction
         */
        foreach($this->getDataExtractionMapping() as $key => $path) {

            // Extract value
            $extractedValue = $this->_data->find($path);

            // If we found a value add it to the extracted data array
            if ($extractedValue) {
                $extractedData[$key] = $extractedValue;
            }
            /*
             * If we didn't find the value add NULL to the extracted data array
             * ONLY if there isn't already a previously found value.
             */
            elseif (!isset($extractedData[$key])) {
                $extractedData[$key] = null;
            }
        }



        return $extractedData;
    }


    /**
     * Get data extraction mapping
     * @return array
     */
    public function getDataExtractionMapping()
    {
        $fields = $this->getMappingConfig('fields');

        // Convert name/value pairs to an associative array
        $result = array();
        foreach($fields as $field) {
            $result[$field['name']] = $field['location'];
        }

        return $result;
    }


    /**
     * Get the input format
     * @return array
     */
    public function getInputFormat()
    {
        return $this->getMappingConfig('format');
    }
}