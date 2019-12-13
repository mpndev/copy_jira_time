<?php

namespace Drupal\jira_transfer_logged_time\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Unirest\Request;

class JiraTransferController extends  ControllerBase implements ContainerInjectionInterface
{

  /***
   * @param $namespace
   *  A parameter of type string that defines the JIRA namespace of the request
   * @param $jira_email
   *  A parameter of type string that is used to access the JIRA namespace (Token is required too)
   * @param $jira_token
   *  A parameter of type string that is used to access the JIRA namespace (Email is required too)
   * @param $startAt
   *  Integer that is used as an index to the starting position of the request
   * @param $projectsArray
   *  An array that is later filled with the information of the request
   * @return mixed
   *  Returns the array with the projects of the current JIRA
   */
  public function getJiraProjects($namespace, $jira_email, $jira_token, $startAt, &$projectsArray = []){
    $headers = [
      'Accept' => 'application/json',
      'Content-Type' => 'application/json',
    ];
    $response = Request::GET('https://'.$namespace.'.atlassian.net/rest/api/3/project/search?startAt='.$startAt,
      $headers,
      null,
      $jira_email,
      $jira_token
    );
    $total_projects = $response->body->total;
    if($total_projects > $startAt){
      foreach ($response->body->values as $project) {
        $projectsArray[] = $project;
      }
      $startAt += 50;
      $this->getJiraProjects($namespace, $jira_email, $jira_token, $startAt,$projectsArray);
    }

    return $projectsArray;
  }

  /***
   * @param $namespace
   *  A parameter of type string that defines the JIRA namespace of the request
   * @param $jira_email
   *  A parameter of type string that is used to access the JIRA namespace (Token is required too)
   * @param $jira_token
   *  A parameter of type string that is used to access the JIRA namespace (Email is required too)
   * @param $startAt
   *  Integer that is used as an index to the starting position of the request
   * @param $usersArray
   *  An array that is later filled with the information of the request
   * @return mixed
   *  Returns the array with the users of the current JIRA
   */
  public function getJiraUsers($namespace, $jira_email, $jira_token, $startAt, &$usersArray){
    $headers = [
      'Accept' => 'application/json',
      'Content-Type' => 'application/json',
    ];
    $response = Request::get('https://'.$namespace.'.atlassian.net/rest/api/3/users/search?startAt='.$startAt,
      $headers,
      null,
      $jira_email,
      $jira_token
    );
    if(!empty($response->body)) {
      foreach ($response->body as $user) {
        if ($user->accountType !== "app") { //If the account is an app account, it should not be added to the array
          if ($user->active == true) {
            $usersArray[] = $user;
          }
        }
      }
      $startAt += 50;
      $this->getJiraUsers($namespace, $jira_email, $jira_token, $startAt, $usersArray);
    }

    return $usersArray;
  }

  /***
   * @param $namespace
   *  A parameter of type string that defines the JIRA namespace of the request
   * @param $jira_email
   *  A parameter of type string that is used to access the JIRA namespace (Token is required too)
   * @param $jira_token
   *  A parameter of type string that is used to access the JIRA namespace (Email is required too)
   * @param $startAt
   *  Integer that is used as an index to the starting position of the request
   * @param $projectKey
   *  String that is required to get all issues from the project
   * @param $issueArray
   *  An array that is later filled with the information of the request
   * @return mixed
   *  Returns the array with the issues of the current JIRA project (selected with $projectKey)
   */
  public function getJiraIssues($namespace, $jira_email, $jira_token,$projectKey, $startAt, &$issueArray = []) {

    $headers = [
      'Accept' => 'application/json',
      'Content-Type' => 'application/json',
    ];

    $issueResponse = Request::get('https://'.$namespace.'.atlassian.net/rest/api/2/search?jql=project="'. $projectKey .
      '"&startAt='.$startAt ,
      $headers,
      null,
      $jira_email,
      $jira_token
    );
    if($issueResponse->body->total > $startAt) {
      foreach ($issueResponse->body->issues as $issue) {
        array_push($issueArray, $issue);
      }
      $startAt += 50;
      $this->getJiraIssues($namespace, $jira_email, $jira_token,$projectKey, $startAt, $issueArray);
    }
    return $issueArray;
  }

  /***
   * @param $namespace
   *  A parameter of type string that defines the JIRA namespace of the request
   * @param $jira_email
   *  A parameter of type string that is used to access the JIRA namespace (Token is required too)
   * @param $jira_token
   *  A parameter of type string that is used to access the JIRA namespace (Email is required too)
   * @param $start_date
   *  Parameter from the form that is used to determine the starting date to sort the worklogs
   * @param $end_date
   *  Parameter from the form that is used to determine the ending date to sort the worklogs
   * @param $issueArray
   *  Array that contains the issues from the current JIRA project
   * @param $worklogsArray
   *  Array that is to be filled with worklogs from the current JIRA issues
   * @return mixed
   *  Returns an array with the information for all the worklogs made between the start and end date
   */
  public function getJiraIssuesWorklogs($namespace, $jira_email, $jira_token, $start_date, $end_date, &$issueArray, &$worklogsArray, $selected_user){
    $headers = [
      'Accept' => 'application/json',
      'Content-Type' => 'application/json',
    ];
    foreach ($issueArray as $issue) {
      $issue_key = $issue->key;
      $response = Request::get('https://' . $namespace . '.atlassian.net/rest/api/3/issue/' . $issue_key . '/worklog/',
        $headers,
        null,
        $jira_email,
        $jira_token
      );

      foreach ($response->body->worklogs as $worklog) {
        $created = date("Y-m-d", strtotime($worklog->created));
        if ($created >= $start_date && $created <= $end_date && !empty($worklog->author->displayName) && $worklog->author->displayName === $selected_user) {
          array_push($worklogsArray, $worklog);
        }
      }
    }
    return $worklogsArray;
  }

  /***
   * @param $userId
   *  The user id from the current JIRA
   * @param $worklogArray
   *  The array with the worklogs
   * @return mixed
   *  A new array that includes the worklogs that the user with $userID has made
   */
  public function getSpecificUserWorklogs($userId, &$worklogArray){
    foreach ($worklogArray as $worklog => $value){
      if(!($userId === $value->author->accountId)){
        unset($worklogArray[$worklog]);
      }
    }
    return $worklogArray;
  }

  /***
   * @param $issue_id
   *  The id of the current issue, used to get the issue key
   * @param $issueArray
   *  The array with all issues
   * @return mixed
   *  returns the specific issue key
   */
  public function getIssueKeyFromId($issue_id, &$issueArray){
    foreach ($issueArray as $issue => $value){
      if($issue_id === $value->id){
        return $value->key;
        break;
      }
    }
  }

  /***
  @param $namespace
   *  A parameter of type string that defines the JIRA namespace of the request
   * @param $jira_email
   *  A parameter of type string that is used to access the JIRA namespace (Token is required too)
   * @param $jira_token
   *  A parameter of type string that is used to access the JIRA namespace (Email is required too)
   * @param $worklog
   *  Current worklog array, containing all the information for the current worklog
   * @param $issue_id
   *  Id of the issue that you need to log the worklog
   */
  public function addWorklog($namespace, $jira_email, $jira_token, $worklog, $issue_id){
    $headers = [
      'Accept' => 'application/json',
      'Content-Type' => 'application/json',
    ];
    $body =
      '{
         "timeSpentSeconds":'. $worklog->timeSpentSeconds .',
         "comment": {
           "type": "doc",
           "version": 1,
           "content": [
             {
               "type": "paragraph",
               "content": [
                 {
                   "text": "'. $worklog->comment->content[0]->content[0]->text . '",
                   "type": "text"
                 }
               ]
             }
           ]
         },
     "started": "'. $worklog->created .'"
   }';
    $response = Request::post(
      'https://'.$namespace.'.atlassian.net/rest/api/3/issue/'. $issue_id.'/worklog',
      $headers,
      $body,
      $jira_email,
      $jira_token
    );
  }

}
