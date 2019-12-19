<?php

namespace Drupal\jira_transfer_logged_time\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Controller\ControllerBase;

class JiraController extends ControllerBase {

  /**
   * @return array
   */
  public function index() {
    $user_id = \Drupal::currentUser()->id();
    $user_data = \Drupal::service('user.data');
    $initial_data = json_encode([
      'host' => \Drupal::request()->getHost(),
      'jira_email' => $user_data->get('jira_transfer_logged_time', $user_id, 'jira_account_email'),
      'jira_token' => $user_data->get('jira_transfer_logged_time', $user_id, 'jira_account_token'),
      'source_jira_namespace' => $user_data->get('jira_transfer_logged_time', $user_id, 'jira_source_namespace'),
      'target_jira_namespace' => $user_data->get('jira_transfer_logged_time', $user_id, 'jira_target_namespace'),
    ]);

    return [
      '#theme' => 'jira',
      '#initial_data' => $initial_data,
    ];
  }

  /**
   * @return \Drupal\Core\Ajax\AjaxResponse
   */
  public function projectsFromTo() {
    $projects = (new JiraTransferController())->getJiraProjects();
    return new AjaxResponse($projects);
  }
}
