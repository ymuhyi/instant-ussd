<?php

namespace Bitmarshals\InstantUssd;

/**
 * Description of UssdService
 *
 * @author David Bwire
 */
class UssdService {

    const LOAD_MORE_KEY = "98";
    const GO_BACK_KEY = "0";
    const HOME_KEY = "00";
    const EXIT_KEY = "000";

    /**
     *
     * @var array 
     */
    protected $aTrimmedUssdValues;

    /**
     *
     * @var array
     */
    protected $aNonExtraneousUssdValues;

    /**
     * An array containing all trimmed values keyed without the navigation inouts
     * eg 0, 00. Used to track pagination
     *  
     * @var array 
     */
    protected $aNonExtraneousUssdValuesWithLoadMoreKey;

    /**
     *
     * @var string 
     */
    protected $ussdText;

    public function __construct($ussdText, $separator) {
        $this->ussdText = $ussdText;
        // convert ussd text to array and remove extra spaces
        $this->aTrimmedUssdValues = $this->trimArrayValues(explode($separator, $ussdText));
        // rid $aTrimmedUssdValues of extraneous values like navigation text etc
        $this->aNonExtraneousUssdValues = $this->removeExtraneousValues($this->aTrimmedUssdValues);
        // does like above but keeps LOAD_MORE key
        $this->aNonExtraneousUssdValuesWithLoadMoreKey = $this->removeExtraneousValues($this->aTrimmedUssdValues, false);
    }

    /**
     * Remove extraneous USSD values such as navigation and load more values
     * 
     * @param array $aTrimmedUssdValues
     * @param boolean $removeLoadMoreKeys
     * @return array
     */
    protected function removeExtraneousValues(array $aTrimmedUssdValues, $removeLoadMoreKeys = true) {
        // reset index to ensure we're starting from left to right
        reset($aTrimmedUssdValues);
        // these methods should be called in this order
        // remove load more keys
        if ($removeLoadMoreKeys === true) {
            $aNonLoadMoreValues = $this->removeAllOccurencesOfLoadMoreKey($aTrimmedUssdValues);
        } else {
            $aNonLoadMoreValues = $aTrimmedUssdValues;
        }
        // remove home page reset keys
        $aNonLoadMoreValuesNonHomeKeyValues = $this->resetToHomeMenuOnHomeKeyEncounter($aNonLoadMoreValues);
        // remove goBackKeyAndPreviousKey
        $finalResult = $this->removeAllOccurencesOfGobackKeyOnGoBackKeyEncounter($aNonLoadMoreValuesNonHomeKeyValues);

        return $finalResult;
    }

    /**
     * 
     * @return array
     */
    private function removeAllOccurencesOfLoadMoreKey(array $aTrimmedUssdValues) {
        // remove all occurences of LOAD_MORE_KEY
        foreach ($aTrimmedUssdValues as $key => $value) {
            if ($value == self::LOAD_MORE_KEY) {
                unset($aTrimmedUssdValues[$key]);
            }
        }
        // rindex aText
        return $this->reIndexArray($aTrimmedUssdValues);
    }

    /**
     * 
     * @param array $aNonLoadMoreValuesNonHomeKeyValues
     * @return array
     */
    private function removeAllOccurencesOfGobackKeyOnGoBackKeyEncounter(array $aNonLoadMoreValuesNonHomeKeyValues) {

        // reset index to ensure we're starting from left to right
        reset($aNonLoadMoreValuesNonHomeKeyValues);

        // process the submitted user responses from left to right
        // inspecting one value at a time
        foreach ($aNonLoadMoreValuesNonHomeKeyValues as $key => $value) {
            // check if user sent in a zero
            if ($value === self::GO_BACK_KEY) {
                $previousKey = $key - 1;
                // check the previous key falls within valid indexed array key range
                if ($previousKey >= 0) {
                    // try and find/confirm an existent previous key                    
                    // previous key must be within the array
                    // it's key may not exist, at $key - 1 position, considering the array below
                    // $aNonLoadMoreValuesNonHomeKeyValues = ["a", "0", "0", "c", "d", "0", "0"];
                    while ((
                    // check the lower limit to avoid a race condition due to negative values
                    $previousKey >= 0) &&
                    // only loop while the previous key is still elusive
                    !array_key_exists($previousKey, $aNonLoadMoreValuesNonHomeKeyValues)) {

                        // if key doesn't exist at current position; go one step lower until you find
                        // a previous position or reach -1                    
                        $previousKey = $previousKey - 1;
                    }
                    // remove GO_BACK_KEY
                    unset($aNonLoadMoreValuesNonHomeKeyValues[$key]);
                    // try and remove the previous value (i.e value before GO_BACK_KEY)
                    // you need to still check that exists as we may have index 0 and it's not there
                    if (array_key_exists($previousKey, $aNonLoadMoreValuesNonHomeKeyValues)) {
                        unset($aNonLoadMoreValuesNonHomeKeyValues[$previousKey]);
                    }
                } else {
                    // we have no previously submitted value
                    // remove the current value which is == GO_BACK_KEY
                    unset($aNonLoadMoreValuesNonHomeKeyValues[$key]);
                }
            }
        }
        return $this->reIndexArray($aNonLoadMoreValuesNonHomeKeyValues);
    }

    /**
     * Clears all values that appear prior to self::HOME_KEY
     * 
     * @param array $aNonLoadMoreValues
     * @return array
     */
    private function resetToHomeMenuOnHomeKeyEncounter(array $aNonLoadMoreValues) {

        // reset index to ensure we're starting from left to right
        reset($aNonLoadMoreValues);

        $maxIndex = -1;
        // iterate through all user supplied values
        // processing from left to right
        foreach ($aNonLoadMoreValues as $key => $value) {
            // find the most recent; reset to main menu request
            if ($value === self::HOME_KEY) {
                if ($key > $maxIndex) {
                    $maxIndex = $key;
                }
            }
        }
        // check if the user requested any main menu reset
        if ($maxIndex !== (-1)) {
            // 1. user requested a reset
            // 2. remove all keys upto max index
            foreach ($aNonLoadMoreValues as $key => $value) {
                if ($key <= $maxIndex) {
                    unset($aNonLoadMoreValues[$key]);
                }
            }
            // reindex array
            return $this->reIndexArray($aNonLoadMoreValues);
        }
        // nothing happened - return the original array
        return $aNonLoadMoreValues;
    }

    /**
     * 
     * @return boolean
     */
    public function isFirstRequest() {
        if (strlen(trim($this->ussdText)) === 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Check if user has keyed in triple zero 000 for the current request
     *
     * @return boolean
     */
    public function isExitRequest() {

        // get the last value of array
        $latestResponse = end($this->aTrimmedUssdValues);
        // return to it's initial state
        reset($this->aTrimmedUssdValues);
        // check if it's a system exit
        if ($latestResponse === self::EXIT_KEY) {
            return true;
        }
        return false;
    }

    /**
     * Check if user wants to go back
     * 
     * @return bool
     */
    public function isGoBackRequest() {

        // get the last value of array
        $latestResponse = end($this->aTrimmedUssdValues);
        // return to it's initial state
        reset($this->aTrimmedUssdValues);
        // check if it's a system exit
        if ($latestResponse === self::GO_BACK_KEY) {
            return true;
        }
        return false;
    }

    /**
     * Return an indexed array of values [0 => "Val0", 1 => "Val1", ... , n => "Valn"]
     * 
     * @param array $aTrimmedUssdValues
     * @return array
     */
    public function reIndexArray(array $aTrimmedUssdValues) {
        // returns an indexed array
        return array_values($aTrimmedUssdValues);
    }

    /**
     * Trim incoming user data
     * 
     * @param array $aTrimmedUssdValues
     * @return array
     */
    protected function trimArrayValues(array $aTrimmedUssdValues) {
        foreach ($aTrimmedUssdValues as $key => $value) {
            $aTrimmedUssdValues[$key] = trim($value);
        }
        return $aTrimmedUssdValues;
    }

    /**
     * Get the most recent value sent in from the user
     * 
     * @return mixed string|false
     */
    public function getLatestResponse() {
        $latestResponse = end($this->aTrimmedUssdValues);
        // return array in it's original state
        reset($this->aTrimmedUssdValues);
        return $latestResponse;
    }

    /**
     * Find the very first value the user sent our way
     * 
     * @return mixed string|false
     */
    public function getFirstResponse() {
        return reset($this->aNonExtraneousUssdValues);
    }

    /**
     * Package incoming data in a format InstantUssd understands. Also add trimmed data
     * , non-extraneous data, first_response & last_response
     * 
     * @param array $ussdParams
     * @param array $aTrimmedUssdValues
     * @param array $aNonExtraneousUssdValues
     * @return array
     */
    public function packageUssdData(array $ussdParams) {
        return [
            'phone_number' => $ussdParams['phoneNumber'],
            'session_id' => $ussdParams['sessionId'],
            'service_code' => $ussdParams ['serviceCode'],
            'text' => $ussdParams['text'],
            'a_values_trimmed' => $this->aTrimmedUssdValues,
            'a_values_non_extraneous' => $this->aNonExtraneousUssdValues,
            'a_values_non_extraneous_with_load_more_key' => $this->aNonExtraneousUssdValuesWithLoadMoreKey,
            // extract & attach latest reponse and first reponse
            'latest_response' => $this->getLatestResponse(),
            'first_response' => $this->getFirstResponse()
        ];
    }

    /**
     * 
     * @return boolean
     */
    public function isExplicitHomepageRequest() {
        $latestResponse = $this->getLatestResponse();
        return (count($this->aTrimmedUssdValues) &&
                ($latestResponse === self::HOME_KEY));
    }

}
