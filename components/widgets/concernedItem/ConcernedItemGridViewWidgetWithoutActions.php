<?php
//******************************************************************************
//                  ConcernedItemGridViewWidgetWithoutActions.php
// SILEX-PHIS
// Copyright © INRA 2018
// Creation date: 23 Aug, 2018
// Contact: andreas.garcia@inra.fr, anne.tireau@inra.fr, pascal.neveu@inra.fr
//******************************************************************************
namespace app\components\widgets\concernedItem;

use Yii;
use app\components\helpers\Vocabulary;
use app\models\yiiModels\YiiConcernedItemModel;

/**
 * Widget to simply show concerned items.
 * @author Andréas Garcia <andreas.garcia@inra.fr>
 */
class ConcernedItemGridViewWidgetWithoutActions extends ConcernedItemGridViewWidget {
    
    /**
     * Returns the columns of the GridView.
     * @return array
     */
    protected function getColumns(): array {
        return [
            [
                'label' => Yii::t('app', YiiConcernedItemModel::URI_LABEL),
                'attribute' => YiiConcernedItemModel::URI,
                'value' => function ($model) {
                    return Vocabulary::prettyUri($model->uri);
                }
            ],
            YiiConcernedItemModel::RDF_TYPE =>
            [
                'label' => Yii::t('app', YiiConcernedItemModel::RDF_TYPE_LABEL),
                'attribute' => YiiConcernedItemModel::RDF_TYPE,
                'value' => function($model) {
                    return Vocabulary::prettyUri($model->rdfType);
                },
            ],
            YiiConcernedItemModel::LABELS => 
            [
                'label' => Yii::t('app', YiiConcernedItemModel::LABELS_LABEL),
                'attribute' => YiiConcernedItemModel::LABELS,
                'value' => function($model) {
                    return implode((', '), $model->labels);
                }
            ]
        ];
    }
}
