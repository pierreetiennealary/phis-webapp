<?php

//******************************************************************************
//                                 iframe-view.php
// PHIS-SILEX
// Copyright © INRA 2018
// Creation date: 21 feb. 2019
// Contact: arnaud.charleroy@inra.fr, anne.tireau@inra.fr, pascal.neveu@inra.fr
//******************************************************************************

/* @var $this yii\web\View */

$this->title = Yii::t('app', '{n, plural, =1{Stat/Vizu Application} other{Stat/Vizu Applications}}', ['n' => 2]);
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="embed-responsive embed-responsive-4by3">
    <iframe class="embed-responsive-item" src="<?= $appUrl ?>" allowfullscreen></iframe>
</div>
