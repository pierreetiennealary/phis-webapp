<?php

//******************************************************************************
//                           view.php
// SILEX-PHIS
// Copyright © INRA 2018
// Creation date: 6 Apr, 2017
// Contact: morgane.vidal@inra.fr, arnaud.charleroy@inra.fr, anne.tireau@inra.fr, pascal.neveu@inra.fr
//******************************************************************************

use yii\helpers\Html;
use yii\widgets\DetailView;
use app\components\widgets\AnnotationButtonWidget;
use app\components\widgets\AnnotationGridViewWidget;
use app\controllers\SensorController;
use yii\grid\GridView;
use \kartik\select2\Select2;
use \yii\web\JsExpression;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $model app\models\YiiSensorModel */
/* @var $dataSearchModel app\models\yiiModels\SensorDataSearch */
/* @var $variables array */
/* Implements the view page for a sensor */
/* @update [Arnaud Charleroy] 22 august, 2018 (add annotation functionality) */

$this->title = $model->label;
$this->params['breadcrumbs'][] = ['label' => Yii::t('app', '{n, plural, =1{Sensor} other{Sensors}}', ['n' => 2]), 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

$sensorProfilePropertiesCount = 0;
foreach ($model->properties as $property) {
    $propertyLabel = explode("#", $property->relation)[1];
    
    if ($propertyLabel !== "type" 
            && $propertyLabel !== "label" 
            && $propertyLabel !== "inServiceDate" 
            && $propertyLabel !== "personInCharge" 
            && $propertyLabel !== "serialNumber" 
            && $propertyLabel !== "dateOfPurchase" 
            && $propertyLabel !== "dateOfLastCalibration" 
            && $propertyLabel !== "hasBrand" 
            && $propertyLabel !== "hasLens" 
            && $propertyLabel !== "measures"
    ) {
        $sensorProfilePropertiesCount++;
    }
}
?>

<div class="sensor-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <!-- Add annotation button-->
        <?= AnnotationButtonWidget::widget([AnnotationButtonWidget::TARGETS => [$model->uri]]); ?>
        <?= Html::a(Yii::t('app', 'Add Document'), ['document/create', 'concernUri' => $model->uri, 'concernLabel' => $model->label], ['class' => $dataDocumentsProvider->getCount() > 0 ? 'btn btn-success' : 'btn btn-warning']) ?>
        <?php
            if (Yii::$app->session['isAdmin'] && $sensorProfilePropertiesCount == 0) {
                echo Html::a(Yii::t('app', 'Characterize Sensor'), ['characterize', 'sensorUri' => $model->uri], ['class' => 'btn btn-success']);
            }
        ?>
    </p>

    <?php if (Yii::$app->session['isAdmin']): ?>
    <!-- Script used to do Ajax call when the update variable button is clicked -->
    <script>
        $(document).ready(function() {
            
            var originalVariablesList = $(".variables-selector select").val();
            
            // On list change enable update button
            $(".variables-selector select").change(function() {
                var currentVariablesList = $(".variables-selector select").val();
                
                // If current and original variables list have same length, they may be equals
                if (currentVariablesList.length === originalVariablesList.length) {
                    var isEqual = true;
                    // Check if every variable in current list exists in original list
                    for (var i in currentVariablesList) {
                        var variable = currentVariablesList[i];
                        
                        // As soon as a different variable is found, set isEqual to false and exit loop
                        if (originalVariablesList.indexOf(variable) === -1) {
                            isEqual = false;
                            break;
                        }
                    }
                    
                    // If both list are equals, disable update button
                    if (isEqual) {
                        $(".update-variables").addClass("disabled");                    
                    } else {
                    // Otherwise, enable update button
                        $(".update-variables").removeClass("disabled");                    
                    }
                } else {
                    // If current and original variables list doesn't have same length, 
                    // they must be different, disable update button
                    $(".update-variables").removeClass("disabled");                    
                }
            });
            
            // On click
            $(".update-variables").click(function() {
                // If button is disabled exit from function
                if ($(this).hasClass("disabled")) {
                    return;
                }
                
                // Build ajax parameter with the sensor uri and the list of selected variables
                var ajaxParameters = {
                    sensor: "<?= $model->uri ?>",
                    variables: $(".variables-selector select").val()
                };
                
                // Do the Ajax call
                $.post(
                    "<?= Url::to(['sensor/update-variables']) ?>",
                    ajaxParameters,
                    function(statusString) {
                        var statusArray = JSON.parse(statusString);
                        
                        // Toastr options generated by @see https://codeseven.github.io/toastr/demo.html
                        toastr.options = {
                            "closeButton": false,
                            "debug": false,
                            "newestOnTop": false,
                            "progressBar": false,
                            "positionClass": "toast-top-right",
                            "preventDuplicates": false,
                            "onclick": null,
                            "showDuration": "300",
                            "hideDuration": "1000",
                            "timeOut": "2000",
                            "extendedTimeOut": "1000",
                            "showEasing": "swing",
                            "hideEasing": "linear",
                            "showMethod": "fadeIn",
                            "hideMethod": "fadeOut"
                        }
                        
                        for(var i in statusArray) {
                            var status = statusArray[i];
                            if (status.exception.type === "Error") {
                                toastr["error"](status.exception.details);
                            } else {
                                toastr["success"](status.message);
                                originalVariablesList = $(".variables-selector select").val();
                                $(".update-variables").addClass("disabled");   
                            }
                        }
                    }
                )
            })
        })
    </script>
    <?php endif; ?>
    <?=
    DetailView::widget([
        'model' => $model,
        'attributes' => [
            'label',
            'uri',
            [
                'attribute' => 'rdfType',
                'format' => 'raw',
                'value' => function ($model) {
                    return explode("#", $model->rdfType)[1];
                }
            ],
            'brand',
            'serialNumber',
            'inServiceDate',
            'dateOfPurchase',
            'dateOfLastCalibration',
            [
                'attribute' => 'personInCharge',
                'format' => 'raw',
                'value' => function ($model) {
                    return Html::a($model->personInCharge, ['user/view', 'id' => $model->personInCharge]);
                },
            ],
            [
                'attribute' => 'variables',
                'format' => 'raw',
                'value' => function ($model) use ($variables) {
                    // Generate the javascript function to add the "eye" ling to the varaible view
                    $templateSelectionFunction[] = "function (obj) {";
                    // Generate a map of variableUri => htmlLink
                    $templateSelectionFunction[] = "var variablesLinks = {";
                    foreach ($variables as $uri => $label) {
                        $templateSelectionFunction[] = "'" . $uri . "':'" . Html::a(
                            "", 
                            ['variable/view', 'uri' => $uri],
                            [
                                "class" => "fa fa-eye variables-select-link",
                                "alt" => $label
                            ]
                        ) . "',";
                    }
                    $templateSelectionFunction[] = "};";
                    // If the item id is present in the generated map return the text with the "eye" link to the corresponding view
                    $templateSelectionFunction[] = "if (variablesLinks.hasOwnProperty(obj.id)) {";
                    $templateSelectionFunction[] = "return obj.text + '&nbsp;' + variablesLinks[obj.id] + '&nbsp;'";
                    $templateSelectionFunction[] = "} else {";
                    // Otherwise return only the text (classic rendering)
                    $templateSelectionFunction[] = "return obj.text";
                    $templateSelectionFunction[] = "}";
                    // close the function body
                    $templateSelectionFunction[] = "}";
                    // Join all lines in the constructed array to generate the function as a readable string
                    $templateSelectionFunction = join("\n", $templateSelectionFunction);
                    
                    // Define the selection widget options with the formating javascript function created
                    $widgetOptions = [
                        'name' => 'variables-selector',
                        'options' => [
                            'multiple' => true
                        ],
                        'data' => $variables,
                        'value' => array_keys($model->variables),
                        'pluginOptions' => [
                            'templateSelection' => new JsExpression($templateSelectionFunction),
                            'escapeMarkup' => new JsExpression('function (markup) { return markup; }'),
                            'allowClear' => true
                        ],
                        'addon' => [
                            'groupOptions' => [
                                'class' => "variables-selector"
                            ]
                        ]
                    ];
                    
                    // Define specific options either user is admin or not
                    if (Yii::$app->session['isAdmin']) {
                        $widgetOptions['addon']['append'] = [
                            'content' => Html::button('<i class="fa fa-check"></i>', [
                                'class' => 'btn btn-primary disabled update-variables',
                                'title' => Yii::t('app/messages', 'Update measured variable'),
                                'data-toogle' => 'tooltip',
                            ]),
                            'asButton' => true
                        ];
                    } else {
                        $widgetOptions['disabled'] = true;
                    }
                            
                    // Create widget HTML
                    $toReturn = Select2::widget($widgetOptions);

                    // Add the info box
                    if (Yii::$app->session['isAdmin']) {
                        $toReturn .= '<p class="info-box">' . Yii::t('app/messages', 'When you change variables measured by sensor in the list, click on the check button to update them.') . '</p>';
                    }
                    
                    return $toReturn;
                },
            ],
            [
                'attribute' => 'properties',
                'format' => 'raw',
                'value' => function ($model) {
                    $toReturn = "<ul>";
                    foreach ($model->properties as $property) {
                        $propertyLabel = explode("#", $property->relation)[1];

                        if ($propertyLabel !== "type" 
                                && $propertyLabel !== "label" 
                                && $propertyLabel !== "inServiceDate" 
                                && $propertyLabel !== "personInCharge" 
                                && $propertyLabel !== "serialNumber" 
                                && $propertyLabel !== "dateOfPurchase" 
                                && $propertyLabel !== "dateOfLastCalibration" 
                                && $propertyLabel !== "hasBrand" 
                                && $propertyLabel !== "hasLens" 
                                && $propertyLabel !== "measures"
                        ) {
                            $toReturn .= "<li>"
                                    . "<b>" . explode("#", $property->relation)[1] . "</b>"
                                    . " : "
                                    . $property->value
                                    . "</li>";
                        } else if ($propertyLabel === "hasLens") {
                            $toReturn .= "<li>"
                                    . "<b>" . explode("#", $property->relation)[1] . "</b>"
                                    . " : "
                                    . Html::a($property->value, ['view', 'id' => $property->value])
                                    . "</li>";
                        }
                    }
                    $toReturn .= "</ul>";
                    return $toReturn;
                },
            ]
        ]
    ]); ?>

    <!-- Sensor data -->
    <?= $this->render('_form_sensor_graph', [
        'model' => $dataSearchModel,
        'variables' => $model->variables
    ]) ?>
    
    <!-- Sensor linked Annotation-->
    <?= AnnotationGridViewWidget::widget(
            [
                AnnotationGridViewWidget::ANNOTATIONS => ${SensorController::ANNOTATIONS_DATA}
            ]
    );
    ?>

    <?php if ($dataDocumentsProvider->getCount() > 0) {
        echo "<h3>" . Yii::t('app', 'Linked Documents') . "</h3>";
        echo GridView::widget([
            'dataProvider' => $dataDocumentsProvider,
            'columns' => [
                ['class' => 'yii\grid\SerialColumn'],
                'title',
                'creator',
                'creationDate',
                'language',
                ['class' => 'yii\grid\ActionColumn',
                    'template' => '{view}',
                    'buttons' => [
                        'view' => function($url, $model, $key) {
                                return Html::a('<span class="glyphicon glyphicon-eye-open"></span>', 
                                                ['document/view', 'id' => $model->uri]); 
                        },
                    ]
                ],
            ]
        ]);
    }
    ?>
</div>
