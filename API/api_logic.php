<?php
/** * Sample API logic *
 * This file is part of  a bigger system and is called by a routing system
 * * API endpoint  *
 * Context:
 * In a local network, without internet acess,  a RFID tag reader system sent data to a local server.
 *  this page handle POST and GET request.
 *
 * Security Token wasn't part of the requierements.
 * */

$apiPostKeys = array(
    'tag_id' => 'text',
    'check_time' => 'int',
    'leaving' => 'int',
);

$rulesPost = array(
    'tag_id' =>array('type' => 'text'),
    'check_time' =>array('type' => 'int'),
    'leaving' =>array('type' => 'text'),
);

$apiGetKeys = array(
//    'rqid' => 'integer',
    'tag_id' => 'integer',
);

$rulesGet = array(
    'tag_id' =>array('type' => 'int'),
);

//In order to check if param
$isDebugRequested = isset($_POST['dev'])  ? true : false;

$this->API_RESULT = array( 'Result' =>'Connected');

//added 2018-02-19: accept only data while the site is open:
if( 
    strtotime(    date("Y-m-d H:i:s") ) <=  strtotime( $this->OPEN ) ||
    strtotime(    date("Y-m-d H:i:s") ) >= strtotime( $this->CLOSE )
) {
    $this->API_RESULT = array( 'error' => '終了です');
} else {
    switch($_SERVER['REQUEST_METHOD'] ){
        case 'POST':
                $this->API_RESULT =  checkAPI($apiPostKeys);
                if($this->API_RESULT  === true || $isDebugRequested){
                    $this->API_RESULT  = array();
                    InputValidation::validPost($_POST, $rulesPost);
                    $data =InputValidation::prepare($_SESSION, $apiPostKeys, $rulesPost);
                    $dataBK = $data;
		    //find current tag_id owner
                    $tagOwner =getUserIdByTag($_SESSION['tag_id']);
                    //lest add the user id :
                    $data[] = array('key' => 'user_id', 'value' =>$tagOwner, 'type' => 1);
                    
                    //for debug and check purpose:
                    if($isDebugRequested){
                        $this->API_RESULT['Mode'] = '* DEV MODE ACTIVED * DATA wont  be recorded';
                        $this->API_RESULT[ 'Post'] = $_POST;
                        $this->API_RESULT[ 'query'] =$data;
                    } else {
                        $result = $this->dbInsert($data );
                        $this->API_RESULT = array( 'Result' => ($result ? 'Sucess: id '.$result : 'Error 0X59310'));
                    }
                    if($tagOwner == 0){
                        $this->API_RESULT['warning'] = 'ユーザーのTAGIDを確認してください。';
                    }
                }         
                    
        break;
        case 'GET':
            //allow direct id or tag id
                $tagID =isset($_GET['tag_id'])? $_GET['tag_id'] : false;
                $rqid = $tagID ? true : false;
            
            //  check if is valid user
            if($rqid){
                $rqid =getUserIdByTag($_GET['tag_id']);
                $user_name = getUserName($rqid);
                
                if($user_name){
                    $user = $rqid ;

                    //retrieve all the user according to the  time settings: Databse record time or the checktime value
                    if(DISPLAY_TIME_ACQUISITION){
                        $wherePeriod = "  AND  CAST(`record_time`  AS DATETIME) >= '{$this->RANKING_START}' ";
                        $where = " `user_id`!=1  $wherePeriod ORDER BY record_time ASC";
                    } else {

                        $wherePeriod = "  AND  `check_time` >= '". date("ymdHis",strtotime($this->RANKING_START))."' ";
                        $where = " `user_id`!=1  $wherePeriod ORDER BY check_time ASC";
                    }
                    $tableData = $this->getMainData($where);

                    //request all data for user
                    $where_single_user = " `user_id`= '$user'  ORDER BY check_time ASC";
                    $table_data_single_user = $this->getMainData($where_single_user);

                     //user data from database
                    $metersDays = setUserDaillyMeters($table_data_single_user);
                    $today = date("Ymd");

                    //retrieve resulst for current day:
                    $todayResults = isset($metersDays[$today])? $metersDays[$today]: array('day'=>0,'up'=>0, 'down'=>0, 'meters' =>0);
                    $rankingUser = getRanking( $tableData, $rqid);
		    
                    $this->API_RESULT = array(
                        'user' =>$user_name['user_name'],
                        'daily' =>$todayResults['meters'],
                        'ranking' => (isset($rankingUser['rank'])? $rankingUser['rank'] :0) ,
                        );
                } else {
                    $this->API_RESULT = array('Error' => 'TAGID ['.$tagID.']  not found');
                }
            } else {
                $this->API_RESULT = array('Error' => 'parameter [tag_id] is missing');
            }
        break;
    }
}

echo json_encode ($this->API_RESULT);