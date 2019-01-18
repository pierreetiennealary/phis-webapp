<?php

//******************************************************************************
//                          RadiometricTargetController.php
// SILEX-PHIS
// Copyright © INRA 2018
// Creation date: 27 Sept, 2018
// Contact: vincent.migot@inra.fr, anne.tireau@inra.fr, pascal.neveu@inra.fr
//******************************************************************************

namespace app\controllers;

use app\models\wsModels\WSConstants;
use app\models\yiiModels\AnnotationSearch;
use app\models\yiiModels\DocumentSearch;
use app\models\yiiModels\RadiometricTargetSearch;
use app\models\yiiModels\UserSearch;
use app\models\yiiModels\YiiDocumentModel;
use app\models\yiiModels\YiiRadiometricTargetModel;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\UploadedFile;
use app\components\helpers\SiteMessages;

require_once '../config/config.php';

/**
 * CRUD actions for YiiRadiometricTargetModel
 * 
 * @see yii\web\Controller
 * @see app\models\yiiModels\YiiRadiometricTargetModel
 * @author Migot Vincent <vincent.migot@inra.fr>
 */
class RadiometricTargetController extends Controller {
    CONST ANNOTATIONS_DATA = "radiometricTargetAnnotations";
    /**
     * Define the behaviors
     * 
     * @return array
     */
    public function behaviors() {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * List all radiometric targets
     * 
     * @return mixed redirect in case of error otherwise return the "index" view
     */
    public function actionIndex() {
        $searchModel = new RadiometricTargetSearch();
        
        //Get the search params and update pagination
        $searchParams = Yii::$app->request->queryParams;        
        if (isset($searchParams[\app\models\yiiModels\YiiModelsConstants::PAGE])) {
            $searchParams[\app\models\yiiModels\YiiModelsConstants::PAGE]--;
        }

        $searchResult = $searchModel->search(Yii::$app->session['access_token'], $searchParams);

        if (is_string($searchResult)) {
            if ($searchResult === \app\models\wsModels\WSConstants::TOKEN) {
                return $this->redirect(Yii::$app->urlManager->createUrl("site/login"));
            } else {
                return $this->render(SiteMessages::SITE_ERROR_PAGE_ROUTE, [
                            SiteMessages::SITE_PAGE_NAME => Yii::t('app/messages', SiteMessages::INTERNAL_ERROR),
                            SiteMessages::SITE_PAGE_MESSAGE => $searchResult]);
            }
        } else {
            return $this->render('index', [
                        'searchModel' => $searchModel,
                        'dataProvider' => $searchResult
            ]);
        }
    }

    /**
     * Display the detail of a radiometric target
     * 
     * @param $id Uri of the radiometric target to display
     * @return mixed redirect in case of error otherwise return the "view" view
     */
    public function actionView($id) {
        //1. Fill the radiometric target model with the information.
        $model = new YiiRadiometricTargetModel();
         $radiometricTargetDetail = $model->getDetails(Yii::$app->session['access_token'], $id);

        //2. Get documents.
        $searchDocumentModel = new DocumentSearch();
        $searchDocumentModel->concernedItemFilter = $id;
        $documents = $searchDocumentModel->search(Yii::$app->session['access_token'], ["concernedItem" => $id]);

        //3. get project annotations
        $searchAnnotationModel = new AnnotationSearch();
        $searchAnnotationModel->targets[0] = $id;
        $infrastructureAnnotations = $searchAnnotationModel->search(Yii::$app->session[WSConstants::ACCESS_TOKEN], [AnnotationSearch::TARGET_SEARCH_LABEL => $id]);

        //4. Render the view of the infrastructure.
        if (is_array( $radiometricTargetDetail) && isset( $radiometricTargetDetail["token"])) {
            return $this->redirect(Yii::$app->urlManager->createUrl("site/login"));
        } else {
            return $this->render('view', [
                        'model' =>  $radiometricTargetDetail,
                        'dataDocumentsProvider' => $documents,
                        self::ANNOTATIONS_DATA => $infrastructureAnnotations
            ]);
        }
    }
    
    /**
     * Display the form to create radiometric target or create it in case of form submit
     * 
     * @return mixed redirect in case of error or after successfully create 
     * the radiometric target otherwise return the "create" view 
     */
    public function actionCreate() {
        $sessionToken = Yii::$app->session['access_token'];

         $radiometricTargetModel = new YiiRadiometricTargetModel();
         $radiometricTargetModel->isNewRecord = true;
        
        if ( $radiometricTargetModel->load(Yii::$app->request->post())) {
            // 1. If post data, insert the submitted form
            $dataToSend[] =  $radiometricTargetModel->mapToProperties();
            $requestRes =  $radiometricTargetModel->insert($sessionToken, $dataToSend);

            if (is_string($requestRes) && $requestRes === "token") { //L'utilisateur doit se connecter
                return $this->redirect(Yii::$app->urlManager->createUrl("site/login"));
            } else {
                // 2. Send file associated to the radiometric target
                 $radiometricTargetModel->uri = $requestRes->metadata->datafiles[0];
                $fileResponse = $this->sendFile($sessionToken,  $radiometricTargetModel);

                if ($fileResponse == false) {
                    return $this->render(SiteMessages::SITE_ERROR_PAGE_ROUTE, [
                        SiteMessages::SITE_PAGE_NAME => Yii::t('app/messages', SiteMessages::INTERNAL_ERROR),
                        SiteMessages::SITE_PAGE_MESSAGE => $searchResult]);
                } elseif (is_string($fileResponse) && $fileResponse === "token") { //L'utilisateur doit se connecter
                    return $this->redirect(Yii::$app->urlManager->createUrl("site/login"));
                } else {
                    // 3. Display the view page of the inserted radiometric target
                    return $this->redirect(['view', 'id' =>  $radiometricTargetModel->uri]);
                }
            }
        } else {
            // If no post data display the create form
            $userModel = new \app\models\yiiModels\YiiUserModel();        
            $this->view->params['listContacts'] = $userModel->getPersonsMailsAndName($sessionToken);

            return $this->render('create', [
                'model' =>  $radiometricTargetModel
            ]);
        }
    }

    /**
     * Display the form to update a radiometric target or update it in case of form submit
     * 
     * @return mixed redirect in case of error or after successfully create 
     * the radiometric target otherwise return the "create" view 
     */
    public function actionUpdate($id) {
        $sessionToken = Yii::$app->session['access_token'];

         $radiometricTargetModel = new YiiRadiometricTargetModel();
        
        if ( $radiometricTargetModel->load(Yii::$app->request->post())) {
            // 1. If post data, update the submitted form for the radiometric target
            $dataToSend[] =  $radiometricTargetModel->mapToProperties();
            $requestRes =  $radiometricTargetModel->update($sessionToken, $dataToSend);

            if (is_string($requestRes) && $requestRes === "token") { //L'utilisateur doit se connecter
                return $this->redirect(Yii::$app->urlManager->createUrl("site/login"));
            } else {
                return $this->redirect(['view', 'id' =>  $radiometricTargetModel->uri]);
            }
        } else {
            //1. Fill the radiometric target model with the information.
             $radiometricTargetDetail =  $radiometricTargetModel->getDetails($sessionToken, $id);
        
            //2. Load user list for update form
            $userModel = new \app\models\yiiModels\YiiUserModel();
            $this->view->params['listContacts'] = $userModel->getPersonsMailsAndName($sessionToken);

            return $this->render('update', [
                'model' =>  $radiometricTargetModel
            ]);
        }
    }
    
    /**
     * Send the attached reflectance file to the webservices
     * 
     * @param string $sessionToken current session token
     * @param app\models\yiiModels\YiiRadiometricTargetModel  $radiometricTargetModel
     * @return false if the file is not correctly uploaded
     *          or the result of the webservice request to post document
     */
    private function sendFile($sessionToken,  $radiometricTargetModel) {
        $file = UploadedFile::getInstance( $radiometricTargetModel, 'reflectanceFile');

        // 1. check if the file is correctly uploaded
        if (is_uploaded_file($file->tempName)) {
            
            // 2. initialize the document model
            $documentModel = new YiiDocumentModel();
            $format = explode(".", $file->name);
            $documentModel->format = $format[count($format) - 1];
            $serverFilePath = \config::path()['documentsUrl'] . $file->name;
            $file->saveAs($serverFilePath);
            $documentModel->md5 = md5_file($serverFilePath);
            $documentModel->status = "linked";
            $documentModel->language = "";
            $documentModel->title = $format[0];
            $documentModel->creator = Yii::$app->session['email'];
            $documentModel->documentType =  Yii::$app->params["SpectralHemisphericDirectionalReflectanceFile"];
            $documentModel->creationDate = date("Y-m-d");

            // 3. Affect the concerned item
            $item = new \app\models\yiiModels\YiiInstanceDefinitionModel();
            $item->uri =  $radiometricTargetModel->uri;
            $wsUriModel = new \app\models\wsModels\WSUriModel();
            $rdfType = $wsUriModel->getUriType($sessionToken,  $radiometricTargetModel->uri, null);
            $item->rdfType = $rdfType;
            $documentModel->concernedItems = [$item];

            // 4. Send the request to insert the document
            $dataToSend[] = $documentModel->attributesToArray();
            $response = $documentModel->insert($sessionToken, $dataToSend);
            $requestURL = isset($response->metadata->datafiles) ? $response->metadata->datafiles[0] : null;

            if ($requestURL !== null) {
                // 5. Post file associated to the document
                $filePointer = fopen($serverFilePath, 'r');
                $requestRes = $documentModel->postDocument($sessionToken, $filePointer, $requestURL);
                unlink($serverFilePath);

                return $requestRes;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

}
