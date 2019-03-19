<?php
//******************************************************************************
//                           PropertyFormatter.php
// SILEX-PHIS
// Copyright © INRA 2018
// Creation date: 20 September, 2018
// Contact: vincent.migot@inra.fr, anne.tireau@inra.fr, pascal.neveu@inra.fr
//******************************************************************************
namespace app\components\helpers;

use yii\helpers\Html;

/**
 * Helper to regroup all formatters used by the PropertyWidget
 * New formatters should be added here for any new type necessary.
 * Actually only Infrastructures are supported.
 * @see app\components\widgets\PropertyWidget
 * @author Migot Vincent <vincent.migot@inra.fr>
 */
class PropertyFormatter {

    // Formatter const used to render a link based on a property
    const EXTERNAL_LINK = "externalLink";
    /**
     * Render an external link based on a property uri, it will use the
     * property label as title if exist or the uri itself.
     * @param array $value
     * @return string Html rendering
     */
    static function externalLink($value) {
        $title = $value['uri'];

        if ($value['label']) {
            $title = $value['label'];
        }

        return Html::a($title, $value['uri'], ["target" => "_blank"]);
    }

    // Formatter const used to render a link to the infrastructure details view
    const INFRASTRUCTURE = "infrastructure";
    
    /**
     * Render a link to an infrastructure based on a property uri, 
     * it will render a pattern "value (type)" as link title.
     * @param array $value
     * @return string Html rendering
     */
    static function infrastructure($value) {
        $strValue = $value['label'];

        if ($value['typeLabel']) {
            $strValue .= " (" . $value['typeLabel'] . ")";
        }
        
        return Html::a($strValue, ['infrastructure/view', 'id' => $value['uri']]);

    }
    
    /**
     * Default formatter for any property
     * if the label exist, it will use it as title, otherwise it will use the uri
     * if the type label exist, it will add it to the title between parenthesis. 
     * @param array $value
     * @return string Html rendering
     */
    static function defaultFormat($value) {
        $strValue = "";
        
        if ($value['label']) {
            $strValue = $value['label'];
        } else {
            $strValue = $value['uri'];
        }
        
        if ($value['typeLabel']) {
            $strValue .= " (" . $value['typeLabel'] . ")";
        }
        
        return $strValue;
    }
}
