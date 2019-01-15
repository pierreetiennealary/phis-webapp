<?php

//**********************************************************************************************
//                                       createCSV.php 
//
// Author(s): Morgane VIDAL
// PHIS-SILEX version 1.0
// Copyright © - INRA - 2017
// Creation date: August 2017
// Contact: morgane.vidal@inra.fr, anne.tireau@inra.fr, pascal.neveu@inra.fr
// Last modification date:  August, 31 2017
// Subject: creation scientific object by CSV view
//***********************************************************************************************

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model app\models\YiiScientificObjectModel */

$this->title = Yii::t('yii', 'Create') . ' ' . Yii::t('app', '{n, plural, =1{Scientific Object} other{Scientific Objects}}', ['n' => 1]);
$this->params['breadcrumbs'][] = ['label' => Yii::t('app', '{n, plural, =1{Scientific Object} other{Scientific Objects}}', ['n' => 2]), 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="scientific-object-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
        'objectsTypes' => $objectsTypes,
        'experiments' => $experiments,
        'species' => $species
    ]) ?>
</div>