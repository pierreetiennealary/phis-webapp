<?php
//******************************************************************************
//                          EventController.php
// SILEX-PHIS
// Copyright © INRA 2018
// Creation date: Jan, 2019
// Contact: andreas.garcia@inra.fr, anne.tireau@inra.fr, pascal.neveu@inra.fr
//******************************************************************************
namespace app\controllers;

use Yii;
use yii\web\Controller;
use app\models\yiiModels\EventSearch;
use app\models\yiiModels\DocumentSearch;
use app\models\yiiModels\YiiEventModel;
use app\models\yiiModels\EventPost;
use app\models\yiiModels\YiiUserModel;
use app\models\yiiModels\InfrastructureSearch;
use app\models\yiiModels\YiiPropertyModel;
use app\models\wsModels\WSConstants;
use app\components\helpers\SiteMessages;

/**
 * Controller for the Events according to YiiEventModel
 * @see yii\web\Controller
 * @see app\models\yiiModels\YiiEventModel
 * @author Andréas Garcia <andreas.garcia@inra.fr>
 */
class EventController extends Controller {
    
    const ANNOTATIONS_DATA = "annotations";
    const ANNOTATIONS_PAGE = "annotations-page";
    const INFRASTRUCTURES_DATA = "infrastructures";
    const INFRASTRUCTURES_DATA_URI = "infrastructureUri";
    const INFRASTRUCTURES_DATA_LABEL = "infrastructureLabel";
    const INFRASTRUCTURES_DATA_TYPE = "infrastructureType";
    const EVENT_TYPES = "eventTypes";
    
    /**
     * Lists the events
     * @return mixed
     */
    public function actionIndex() {
        $searchModel = new EventSearch();
        
        $searchParams = Yii::$app->request->queryParams;
        
        if (isset($searchParams[WSConstants::PAGE])) {
            $searchParams[WSConstants::PAGE] = $searchParams[WSConstants::PAGE] - 1;
        }
        $searchParams[WSConstants::PAGE_SIZE] = Yii::$app->params['indexPageSize'];
        
        $searchResult = $searchModel->search(Yii::$app->session[WSConstants::ACCESS_TOKEN], $searchParams);
        
        if (is_string($searchResult)) {
            if ($searchResult === WSConstants::TOKEN_INVALID) {
                return $this->redirect(Yii::$app->urlManager->createUrl(SiteMessages::SITE_LOGIN_PAGE_ROUTE));
            } else {
                return $this->render(SiteMessages::SITE_ERROR_PAGE_ROUTE, [
                    SiteMessages::SITE_PAGE_NAME => SiteMessages::INTERNAL_ERROR,
                    SiteMessages::SITE_PAGE_MESSAGE => $searchResult]);
            }
        } else {
            return $this->render('index', [
                'searchModel' => $searchModel, 
                'dataProvider' => $searchResult]);
        }
    }

    /**
     * Displays the detail of an event
     * @param $id URI of the event
     * @return mixed redirect in case of error otherwise return the "view" view
     */
    public function actionView($id) {
        // Get request parameters
        $searchParams = Yii::$app->request->queryParams;
        
        // Fill the event model with the information
        $event = (new YiiEventModel())->getEvent(Yii::$app->session[WSConstants::ACCESS_TOKEN], $id);

        // Get documents
        $searchDocumentModel = new DocumentSearch();
        $searchDocumentModel->concernedItemFilter = $id;
        $documentProvider = $searchDocumentModel->search(Yii::$app->session[WSConstants::ACCESS_TOKEN], [YiiEventModel::CONCERNED_ITEMS => $id]);
        
        // Get annotations
        $annotationProvider = $event->getEventAnnotations(Yii::$app->session[WSConstants::ACCESS_TOKEN], $searchParams);
        $annotationProvider->pagination->pageParam = self::ANNOTATIONS_PAGE;

        // Render the view of the event
        if (is_array($event) && isset($event[WSConstants::TOKEN_INVALID])) {
            return $this->redirect(Yii::$app->urlManager->createUrl(SiteMessages::SITE_LOGIN_PAGE_ROUTE));
        } else {
            return $this->render('view', [
                'model' =>  $event,
                'dataDocumentsProvider' => $documentProvider,
                self::ANNOTATIONS_DATA => $annotationProvider   
            ]);
        }
    }
    
    /**
     * Gets the event types URIs
     * @return event types URIs 
     */
    public function getEventsTypes() {
        $model = new YiiEventModel();
        
        $eventsTypes = [];
        $model->page = 0;
        $model->pageSize = Yii::$app->params['webServicePageSizeMax'];
        $eventsTypesConcepts = $model->getEventsTypes(Yii::$app->session[WSConstants::ACCESS_TOKEN]);
        if ($eventsTypesConcepts === WSConstants::TOKEN_INVALID) {
            return WSConstants::TOKEN_INVALID;
        } else {
            foreach ($eventsTypesConcepts[WSConstants::DATA] as $eventType) {
                $eventsTypes[$eventType->uri] = $eventType->uri;
            }
        }
        
        return $eventsTypes;
    }
    
    /**
     * Get all infrastructures
     * @return experiments 
     */
    public function getInfrastructuresUrisTypesLabels() {
        $model = new InfrastructureSearch();
        $model->page = 0;
        $infrastructuresUrisTypesLabels = [];
        $infrastructures = $model->search(Yii::$app->session['access_token'], null);
        if ($infrastructures === WSConstants::TOKEN_INVALID) {
            return WSConstants::TOKEN_INVALID;
        } else {
            foreach ($infrastructures->models as $infrastructure) {
                $infrastructuresUrisTypesLabels[] =
                    [
                        self::INFRASTRUCTURES_DATA_URI => $infrastructure->uri,
                        self::INFRASTRUCTURES_DATA_LABEL => $infrastructure->label,
                        self::INFRASTRUCTURES_DATA_TYPE => $infrastructure->rdfType
                    ];
            }
        }
        
        return $infrastructuresUrisTypesLabels;
    }
    
    /**
     * Display the form to create an event or create it in case of form submission
     * @return mixed redirect in case of error or after successfully create 
     * the event otherwise return the "create" view 
     */
    public function actionCreate() {
        $sessionToken = Yii::$app->session[WSConstants::ACCESS_TOKEN];

        $eventModel = new EventPost();
        $eventModel->load(Yii::$app->request->get(), '');
        $eventModel->isNewRecord = true;
        
        if ($eventModel->load(Yii::$app->request->post())) {
            // Set date
            $eventModel->dateWithoutTimezone = str_replace(" ", "T", $eventModel->dateWithoutTimezone);
            
            // Set model creator 
            $userModel = new YiiUserModel();
            $userModel->findByEmail($sessionToken, Yii::$app->session['email']);
            $eventModel->creator = $userModel->uri;
            $eventModel->isNewRecord = true;
            
            // Set properties
            $property = new YiiPropertyModel();
            switch ($eventModel->rdfType) {
                case $eventConceptUri = Yii::$app->params['moveFrom']:
                    $property->value = $eventModel->propertyFrom;
                    $property->rdfType = $eventModel->propertyType;
                    $property->relation = Yii::$app->params['from'];
                    break;
                case $eventConceptUri = Yii::$app->params['moveTo']:
                    $property->value = $eventModel->propertyTo;
                    $property->rdfType = $eventModel->propertyType;
                    $property->relation = Yii::$app->params['to'];
                    break;
                default : 
                    $property = null;
                    break;
            }
            $eventModel->properties = [$property];
            
            // If post data, insert the submitted form
            $dataToSend[] =  $eventModel->attributesToArray();
            error_log("dataToSend ".print_r($dataToSend, true));
            $requestRes =  $eventModel->insert($sessionToken, $dataToSend);
            
            if (is_string($requestRes) && $requestRes === WSConstants::TOKEN) {
                return $this->redirect(Yii::$app->urlManager->createUrl(SiteMessages::SITE_LOGIN_PAGE_ROUTE));
            } else {
                if (isset($requestRes->{WSConstants::METADATA}->{WSConstants::DATA_FILES}[0])) { //event created
                    if ($eventModel->returnUrl) {
                        $this->redirect($eventModel->returnUrl);
                    } else {
                        return $this->redirect(['view', 'id' => $requestRes->{WSConstants::METADATA}->{WSConstants::DATA_FILES}[0]]);
                    }                    
                } else { //an error occurred
                    return $this->render(SiteMessages::SITE_ERROR_PAGE_ROUTE, [
                        'name' => Yii::t('app/messages','Internal error'),
                        'message' => $requestRes->{WSConstants::METADATA}->{WSConstants::STATUS}[0]->{WSConstants::EXCEPTION}->{WSConstants::DETAILS}]);
                }
            }
        } else {
            // If no post data display the create form
           
            $this->view->params[self::EVENT_TYPES] = $this->getEventsTypes();
            $this->view->params[self::INFRASTRUCTURES_DATA] = $this->getInfrastructuresUrisTypesLabels();

            return $this->render('create', ['model' =>  $eventModel]);
        }
    }
}
