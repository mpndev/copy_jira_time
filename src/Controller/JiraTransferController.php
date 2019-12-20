<?php

namespace Drupal\jira_transfer_logged_time\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Unirest\Request;

class JiraTransferController extends  ControllerBase implements ContainerInjectionInterface {

  /**
   * @return array
   */
  public function getJiraProjects(){
    $user_id = \Drupal::currentUser()->id();
    $user_data = \Drupal::service('user.data');
    $jira_email = $user_data->get('jira_transfer_logged_time', $user_id, 'jira_account_email');
    $jira_token = $user_data->get('jira_transfer_logged_time', $user_id, 'jira_account_token');
    $source_jira_namespace = $user_data->get('jira_transfer_logged_time', $user_id, 'jira_source_namespace');
    $target_jira_namespace = $user_data->get('jira_transfer_logged_time', $user_id, 'jira_target_namespace');

    $http_params = \Drupal::request()->query->all();
    $namespaces = ['source' => $source_jira_namespace, 'target' => $target_jira_namespace];
    $namespace = $namespaces[$http_params['prefix']];

    return $this->requestJiraProjects($namespace, $jira_email, $jira_token);
  }

  /**
   * @param $namespace
   * @param $jira_email
   * @param $jira_token
   * @param int $start_at
   * @param array $projects_array
   *
   * @return array
   */
  public function requestJiraProjects($namespace, $jira_email, $jira_token, $start_at = 0, &$projects_array = []){
    $headers = [
      'Accept' => 'application/json',
      'Content-Type' => 'application/json',
    ];
    $response = Request::GET('https://'.$namespace.'.atlassian.net/rest/api/3/project/search?startAt='.$start_at,
      $headers,
      null,
      $jira_email,
      $jira_token
    );
    $total_projects = $response->body->total;
    if ($total_projects > $start_at) {
      foreach ($response->body->values as $project) {
        $projects_array[] = $project;
      }
      $start_at += 50;
      $this->requestJiraProjects($namespace, $jira_email, $jira_token, $start_at,$projects_array);
    }

    return $projects_array;
  }

  /**
   * @return array
   */
  public function getJiraIssues(){
    $user_id = \Drupal::currentUser()->id();
    $user_data = \Drupal::service('user.data');
    $email = $user_data->get('jira_transfer_logged_time', $user_id, 'jira_account_email');
    $token = $user_data->get('jira_transfer_logged_time', $user_id, 'jira_account_token');

    $http_params = \Drupal::request()->query->all();
    $namespace = $http_params['prefix'];
    $project_key = $http_params['project_key'];

    return $this->requestJiraIssues($namespace, $email, $token, $project_key);
  }

  /**
   * @param $namespace
   * @param $jira_email
   * @param $jira_token
   * @param $project_key
   * @param int $start_at
   * @param array $issues_array
   *
   * @return array
   */
  public function requestJiraIssues($namespace, $jira_email, $jira_token, $project_key, $start_at = 0, &$issues_array = []) {
    $headers = [
      'Accept' => 'application/json',
      'Content-Type' => 'application/json',
    ];

    $issueResponse = Request::get('https://'.$namespace.'.atlassian.net/rest/api/2/search?jql=project="'.$project_key.'"&startAt='.$start_at,
      $headers,
      null,
      $jira_email,
      $jira_token
    );
    if($issueResponse->body->total > $start_at) {
      foreach ($issueResponse->body->issues as $issue) {
        $issues_array[] = $issue;
      }
      $start_at += 50;
      $this->requestJiraIssues($namespace, $jira_email, $jira_token, $project_key, $start_at, $issues_array);
    }
    return $issues_array;
  }

  /**
   * @return array
   */
  public function getJiraIssuesWorklogs(){
    $user_id = \Drupal::currentUser()->id();
    $user_data = \Drupal::service('user.data');
    $email = $user_data->get('jira_transfer_logged_time', $user_id, 'jira_account_email');
    $token = $user_data->get('jira_transfer_logged_time', $user_id, 'jira_account_token');

    $http_params = \Drupal::request()->query->all();
    $namespace = $http_params['prefix'];
    $users_accounts_ids = $http_params['users_accounts_ids'];
    $issue_key = $http_params['issue_key'];
    $start_date = \Drupal::request()->query->get('from') ? \Drupal::request()->query->get('from') : date('Y-m-d', strtotime('-1 month'));
    $end_date = \Drupal::request()->query->get('to') ? \Drupal::request()->query->get('to') : date('Y-m-d');

    return $this->requestJiraIssuesWorklogs($namespace, $email, $token, $users_accounts_ids, $issue_key, $start_date, $end_date);
  }

  /**
   * @param $namespace
   * @param $jira_email
   * @param $jira_token
   * @param $users_accounts_ids
   * @param $issue_key
   * @param $start_date
   * @param $end_date
   *
   * @return array
   */
  public function requestJiraIssuesWorklogs($namespace, $jira_email, $jira_token, $users_accounts_ids, $issue_key, $start_date, $end_date) {
    $worklogs = [];
    $headers = [
      'Accept' => 'application/json',
      'Content-Type' => 'application/json',
    ];
    $response = Request::get('https://' . $namespace . '.atlassian.net/rest/api/3/issue/' . $issue_key . '/worklog/',
      $headers,
      null,
      $jira_email,
      $jira_token
    );

    foreach ($response->body->worklogs as $worklog) {
      $created = date("Y-m-d", strtotime($worklog->created));
      if ($created >= $start_date && $created <= $end_date) {
        foreach (explode(',', $users_accounts_ids) as $account_id) {
          if (!empty($worklog->author->accountId) && $worklog->author->accountId === $account_id) {
            $worklog->issueKey = $issue_key;
            $worklog->accountId = $account_id;
            $worklogs[] = $worklog;
          }
        }
      }
    }
    return $worklogs;
  }

  /**
   * @return array
   */
  public function getJiraUsers(){
    $user_id = \Drupal::currentUser()->id();
    $user_data = \Drupal::service('user.data');
    $email = $user_data->get('jira_transfer_logged_time', $user_id, 'jira_account_email');
    $token = $user_data->get('jira_transfer_logged_time', $user_id, 'jira_account_token');

    $http_params = \Drupal::request()->query->all();
    $namespace = $http_params['prefix'];

    return $this->requestJiraUsers($namespace, $email, $token);
  }

  /**
   * @param $namespace
   * @param $jira_email
   * @param $jira_token
   * @param int $start_at
   * @param array $users_array
   *
   * @return array
   */
  public function requestJiraUsers($namespace, $jira_email, $jira_token, $start_at = 0, &$users_array = []){
    $headers = [
      'Accept' => 'application/json',
      'Content-Type' => 'application/json',
    ];
    $response = Request::get('https://'.$namespace.'.atlassian.net/rest/api/3/users/search?startAt='.$start_at,
      $headers,
      null,
      $jira_email,
      $jira_token
    );
    if(!empty($response->body)) {
      foreach ($response->body as $user) {
        if ($user->accountType !== "app") { //If the account is an app account, it should not be added to the array
          if ($user->active == true) {
            $users_array[] = $user;
          }
        }
      }
      $start_at += 50;
      $this->requestJiraUsers($namespace, $jira_email, $jira_token, $start_at, $users_array);
    }

    return $users_array;
  }

  /**
   * @return \Drupal\Core\Ajax\AjaxResponse
   */
  public function submit() {
    $http_params = json_decode(\Drupal::request()->getContent(), true);
    $namespace = $http_params['namespace'];
    $project = $http_params['project'];
    $issue = $http_params['issue'];
    $user = $http_params['user'];
    $email = $http_params['email'];
    $token = $http_params['token'];
    $worklogs = $http_params['worklogs'];
    foreach ($worklogs as $worklog) {
      $this->addWorklog($namespace, $email, $token, $worklog, $issue);
    }

    return new AjaxResponse("successful logs.");
  }

  /**
   * @param $namespace
   * @param $jira_email
   * @param $jira_token
   * @param $worklog
   * @param $issue_id
   */
  public function addWorklog($namespace, $jira_email, $jira_token, $worklog, $issue_id){
    $headers = [
      'Accept' => 'application/json',
      'Content-Type' => 'application/json',
    ];
    $body =
      '{
         "timeSpentSeconds":'. $worklog['timeSpentSeconds'] .',
         "comment": {
           "type": "doc",
           "version": 1,
           "content": [
             {
               "type": "paragraph",
               "content": [
                 {
                   "text": "'. $worklog['comment']['content'][0]['content'][0]['text'] . '",
                   "type": "text"
                 }
               ]
             }
           ]
         },
        "started": "'. $worklog['created'] .'"
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
