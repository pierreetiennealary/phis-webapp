<?php
//******************************************************************************
//                               EventSearch.php
// PHIS-SILEX
// Copyright © INRA 2018
// Creation date: 02 jan. 2019
// Contact: andreas.garcia@inra.fr, anne.tireau@inra.fr, pascal.neveu@inra.fr
//******************************************************************************
namespace app\models\yiiModels;

use Yii;
use DateTime;

use yii\data\ArrayDataProvider;
use app\models\wsModels\WSConstants;
use app\models\yiiModels\YiiEventModel;

/**
 * Search action for the events
 * @author Andréas Garcia <andreas.garcia@inra.fr>
 */
class EventSearch extends YiiEventModel {
    
    /**
     * Concerned item's label filter
     * @example Plot 445
     * @var string
     */
    public $concernedItemLabel;
    const CONCERNED_ITEM_LABEL = 'concernedItemLabel';
    
    /**
     * Concerned item's URI filter
     * @example Plot 445
     * @var string
     */
    public $concernedItemUri;
    const CONCERNED_ITEM_URI = 'concernedItemUri';
    
    /**
     * Date range filter
     * @example 2019-01-02T00:00:00+01:00 - 2019-01-03T23:00:00+01:00
     * @var string
     */
    public $dateRange;
    const DATE_RANGE = 'dateRange';
    
    /**
     * Date range start filter
     * @example 2019-01-02T00:00:00+01:00
     * @var string
     */
    public $dateRangeStart;
    const DATE_RANGE_START = 'startDate';
    
    /**
     * Date range end filter
     * @example 2019-01-02T00:00:00+01:00
     * @var string
     */
    public $dateRangeEnd;
    const DATE_RANGE_END = 'endDate';
    
    /**
     * @inheritdoc
     */
    public function rules() {
        return [[
            [
                self::TYPE,
                self::CONCERNED_ITEM_LABEL,
                self::CONCERNED_ITEM_URI,
                self::DATE_RANGE,
                self::DATE_RANGE_START,
                self::DATE_RANGE_END
            ],  'safe']]; 
    }
    
    /**
     * @return array the labels of the attributes
     */
    public function attributeLabels() {
        return array_merge(
            parent::attributeLabels(),
            [
                self::CONCERNED_ITEM_LABEL => Yii::t('app', 'Concerned Items'),
                self::CONCERNED_ITEM_URI => Yii::t('app', 'Concerned Items'),
                self::DATE_RANGE => Yii::t('app', 'Date')
            ]
        );
    }
    
    /**
     * @param array $sessionToken
     * @param string $searchParams
     * @return mixed DataProvider of the result or string 
     * \app\models\wsModels\WSConstants::TOKEN if the user needs to log in
     */
    public function search($sessionToken, $searchParams) {
        $this->load($searchParams);
        
        if (!$this->validate()) {
            return new ArrayDataProvider();
        }
                
        return $this->getEventProvider($sessionToken, $searchParams);
    }
    
    /**
     * Requests to WS and return result
     * @param $sessionToken
     * @return request result
     */
    private function getEventProvider($sessionToken, $searchParams) {
        $results = $this->find($sessionToken, array_merge ($this->attributesToArray(), $searchParams));
        
        if (is_string($results)) {
            return $results;
        }  else if (isset($results->{WSConstants::DATA}->{WSConstants::STATUS}[0]->{WSConstants::EXCEPTION}->{WSConstants::DETAILS}) 
            && $results->{WSConstants::METADATA}->{WSConstants::STATUS}[0]->{WSConstants::EXCEPTION}->{WSConstants::DETAILS} === WSConstants::TOKEN_INVALID) {
            return WSConstants::TOKEN_INVALID;
        } else {
            
            $events = $this->jsonListOfArraysToArray($results);
            return new ArrayDataProvider([
                'models' => $events,
                'pagination' => [
                    'pageSize' => $searchParams[WSConstants::PAGE_SIZE],
                    'totalCount' => $this->totalCount
                ],
                'totalCount' => $this->totalCount
            ]);
        }
    }
    
    /**
     * @inheritdoc
     * @param type $values
     * @param type $safeOnly
     */
    public function setAttributes($values, $safeOnly = true) {
        parent::setAttributes($values, $safeOnly);
            
        if (is_array($values)) {
            if (isset($values[self::DATE_RANGE])) {
                $dateRange = $values[self::DATE_RANGE];
                
                //SILEX:info
                // We shouldn't control the date range format because the WS 
                // already implements it but as the webapp doesn't handle WS
                // error responses yet, we have to control the format of the 
                // submitted date range at the moment.
                //\SILEX:info
                if (!empty($dateRange)) {
                    $this->validateDateRangeFormatAndSetDatesAttributes($dateRange);
                }
            }
        }
    }
    
    /**
     * Validates the date range format and set the dates attributes. The accepted 
     * date range format is defined in the application parameter 
     * standardDateTimeFormatPhp.
     * @param type $dateRangeString
     */
    private function validateDateRangeFormatAndSetDatesAttributes($dateRangeString) {        
        $dateRangeArray = explode(Yii::$app->params['dateRangeSeparator'], $dateRangeString);
        
        $isSubmittedStartDateFormatValid = true;
        $isSubmittedEndDateFormatValid = true;

        // validate start date
        $submittedStartDateString = $dateRangeArray[0];
        if (!empty($submittedStartDateString)) {
            $isSubmittedStartDateFormatValid = $this->validateSubmittedDateFormat($submittedStartDateString);
            if ($isSubmittedStartDateFormatValid) {
                $this->dateRangeStart = $submittedStartDateString;
                // validate end date
                if (isset($dateRangeArray[1])) {   
                    $submittedEndDateString = $dateRangeArray[1];
                    $isSubmittedEndDateFormatValid = $this->validateSubmittedDateFormat($submittedEndDateString);
                    if ($isSubmittedEndDateFormatValid) {
                        $this->dateRangeEnd = $submittedEndDateString;
                    }
                } 
            }
        }
        
        if (!$isSubmittedStartDateFormatValid || !$isSubmittedEndDateFormatValid) {
            $this->resetDateRangeFilterValues();
        }
    }
    
    /**
     * Validates the submitted date format. The accepted date format is defined 
     * in the application parameter standardDateTimeFormatPhp
     * @param type $dateString
     * @return boolean
     */
    private function validateSubmittedDateFormat($dateString) {
        /* //SILEX:info
         * Steps to validate a date format of a date string:
         *  - create a DateTime object from this date string and its format
         *  - transform this DateTime into a string using this format
         *  if there is no parsing error and if the final date string and the 
         *  first one are equal, then the date format is valid
         * //\SILEX:info
         */
        try {
            /* //SILEX:info
             * the standard date format provide a 'T' between the date and the 
             * time but the PHP date format parser interprets the 'T' as the
             * timezone part of the date. (See http://php.net/manual/en/function.date.php)
             * So, before analysing that a date has
             * a valid date format, we have to replace the 'T' by a neutral char
             * (like a space) in order to be able to use the PHP parser 
             * thereafter.
             * //\SILEX:info
             */
            $dateStringWithoutT = str_replace("T", " ", $dateString);
            $date = DateTime::createFromFormat(Yii::$app->params['dateTimeFormatPhp'], $dateStringWithoutT);
            $dateRangeStartParseErrorCount = DateTime::getLastErrors()['error_count']; 
            if ($dateRangeStartParseErrorCount >= 1) {
                error_log("dateRangeStartParseErrorMessages ".print_r(DateTime::getLastErrors()['errors'], true)); 
                return false;
            }
            else if ($date->format(Yii::$app->params['dateTimeFormatPhp']) == $dateStringWithoutT) {
                return true;
            }
            else {
                return false;
            }
        } catch (Exception $exception) {                
            error_log($exception->getMessage());
            return false;
        }
    }
    
    /**
     * Resets the date range filter values
     */
    private function resetDateRangeFilterValues(){
        $this->dateRangeStart = null;
        $this->dateRangeEnd = null;
        $this->dateRange = null;
    }
    
    /**
     * @inheritdoc
     */
    public function attributesToArray() {
        return [
            YiiModelsConstants::PAGE => $this->page,
            YiiModelsConstants::PAGE_SIZE => $this->pageSize,
            self::TYPE => $this->rdfType,
            self::CONCERNED_ITEM_LABEL => $this->concernedItemLabel,
            self::CONCERNED_ITEM_URI => $this->concernedItemUri,
            self::DATE_RANGE_START => $this->dateRangeStart,
            self::DATE_RANGE_END => $this->dateRangeEnd
        ];
    }
}
