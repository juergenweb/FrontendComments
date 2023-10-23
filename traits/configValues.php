<?php
declare(strict_types=1);

namespace FrontendComments;

/*
 * Various general methods for getting configuration values that can be used inside any class
 *
 * Created by Jürgen K.
 * https://github.com/juergenweb 
 * File name: configValues.php
 * Created: 22.07.2023 
 */


trait configValues {

    /**
     * Get all configuration settings from the FrontendForms module as an assoc. array
     * @return array
     * @throws \ProcessWire\WireException
     */
    function getFrontendFormsConfigValues(): array {
        $configValues = [];
        foreach ($this->wire('modules')->getConfig('FrontendForms') as $key => $value) {
            $configValues[$key] = $value;
        }
        return $configValues;
    }

    /**
     * Get all configuration settings from the FrontendComments input field as an assoc. array
     * @return array
     * @throws \ProcessWire\WireException
     * @throws \ProcessWire\WirePermissionException
     */
    function getFrontendCommentsInputfieldConfigValues(): array {
        $configValues = [];
        foreach ($this->wire('fields')->get($this->field->name) as $key => $value) {
            $configValues[$key] = $value;
        }
        return $configValues;
    }

    /**
     * Create a property of each item in the properties array if value is not null
     * @param array $configArray
     * @param array $properties
     * @return void
     */
    function createPropertiesOfArray(array $configArray, array $properties){
        // extract all properties from configArray
        $filteredArr = array_filter($configArray,
            fn ($key) => in_array($key, $properties),
            ARRAY_FILTER_USE_KEY
        );

        foreach($filteredArr as $propName => $value)
        {
            if(!is_null($value)){
                $this->$propName = $value;
            }
        }
    }

}
