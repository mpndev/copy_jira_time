<?php

namespace Drupal\jira_transfer_logged_time\Form;

use Drupal;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Tableselect;
use Drupal\jira_transfer_logged_time\Controller\JiraTransferController;

class TransferJiraForm extends FormBase {

  /**
   * Returns a unique string identifying the form.
   *
   * The returned ID should be a unique string that can be a valid PHP function
   * name, since it's used in hook implementation names such as
   * hook_form_FORM_ID_alter().
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId()
  {
    return 'jira_transfer_logged_time_form';
  }

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $projectsArray = [];
    $usersArray = [];

    $userID = Drupal::currentUser()->id();
    $userData = Drupal::service('user.data');
    $jira_email = $userData->get('jira_transfer_logged_time', $userID, 'jira_account_email');
    $jira_token = $userData->get('jira_transfer_logged_time', $userID, 'jira_account_token');
    $source_jira_namespace = $userData->get('jira_transfer_logged_time', $userID, 'jira_source_namespace');
    $target_jira_namespace = $userData->get('jira_transfer_logged_time', $userID, 'jira_target_namespace');

    $transfer = new JiraTransferController();

    $form['source_jira_projects'] = [
      '#type' => 'select',
      '#title' => t('Select project from the source JIRA'),
      '#options' => [],
      '#default_value' => $form_state->getValue('source_jira_projects'),
      '#required' => TRUE,
    ];
    $source_projects_array = $transfer->getJiraProjects($source_jira_namespace, $jira_email, $jira_token, 0, $projectsArray);
    foreach ($source_projects_array as $project => $value) {
      $form['source_jira_projects']['#options'][$value->key] = $value->name;
    }
    $form['start_date'] = [
      '#type' => 'date',
      '#title' => t('Select the starting date (included) for the worklogs:'),
      '#required' => TRUE,
      '#default_value' => date('Y-m-d', strtotime('-1 month')),
      '#states' => [
        'invisible' => [
          '#edit-source-jira-projects' => ['value' => '']
        ]
      ],
    ];
    $form['end_date'] = [
      '#type' => 'date',
      '#title' => t('Select the ending date (included) for the worklogs:'),
      '#required' => TRUE,
      '#default_value' => date('Y-m-d'),
      '#states' => [
        'invisible' => [
          '#edit-source-jira-projects' => ['value' => '']
        ]
      ],
    ];
    $form['source_jira_users'] = [
      '#type' => 'select',
      '#title' => t('Select user from the source JIRA'),
      '#options' => [],
      '#validated' => TRUE,
      '#attributes' => [
        'name' => 'field_source_users'
      ],
      '#ajax' => [
        'callback' => '::onChangeSourceUserSelected',
        'event' => 'change',
        'wrapper' => 'tableselect-wrapper', // This element is updated with this AJAX callback.
        'progress' => [
          'type' => 'throbber',
          'message' => t('Please wait...'),
        ],
      ],
      '#description' => t('This may take a few minutes depending on the number of requests'),
      '#required' => TRUE,
      '#states' => [
        'invisible' => [
          '#edit-source-jira-projects' => ['value' => ''],
        ]
      ],
    ];
    $source_users_array = $transfer->getJiraUsers($source_jira_namespace, $jira_email, $jira_token, 0, $usersArray);
    foreach ($source_users_array as $user => $value) {
      $form['source_jira_users']['#options'][$value->accountId] = $value->displayName;
    }
    asort($form['source_jira_users']['#options']);
    $worklogs = $this->populateTable($form, $form_state);
    $form['tableselect_wrapper'] = [
      '#type' => 'container',
      '#id' => 'tableselect-wrapper',
      '#states' => [
        'invisible' => [
          '#edit-source-jira-users' => ['value' => ''],
        ],
      ],
    ];
    $form['tableselect_wrapper']['issues_table'] = [
      '#type' => 'tableselect',
      '#attributes' => [
        'id' => 'issues-table'
      ],
      '#header' => [
        'worklogId' => t('Worklog ID'),
        'taskId' => t('Task ID'),
        'taskDescription' => t('Task Description'),
        'loggedTime' => t('Logged Time'),
      ],
      '#options' => $worklogs['table_data'],
      '#empty' => $this
        ->t('No users found'),

    ];
    $form['target_jira_projects'] = [
      '#type' => 'select',
      '#title' => t('Select project from the target JIRA'),
      '#options' => [],
      '#attributes' => [
        'name' => 'target_jira_projects'
      ],
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::onChangeTargetJiraProjects',
        'event' => 'change',
        'method' => 'replace',
        'wrapper' => 'edit-target-jira-issues-wrapper', // This element is updated with this AJAX callback.
        'progress' => [
          'type' => 'throbber',
          'message' => t('Please wait...'),
        ],
      ],
      '#states' => [
        'invisible' => [
          '#tableselect-wrapper' => ['visible' => FALSE],
        ],
      ],
    ];
    $target_projects_array = $transfer->getJiraProjects($target_jira_namespace, $jira_email, $jira_token, 0);
    foreach ($target_projects_array as $project => $value) {
      $form['target_jira_projects']['#options'][$value->key] = $value->name;
    }
    $form['edit_target_jira_issues_wrapper'] = [
      '#type' => 'container',
      '#id' => 'edit-target-jira-issues-wrapper',
    ];
    $form['edit_target_jira_issues_wrapper']['target_jira_issues'] = [
      '#type' => 'select',
      '#title' => t('Target JIRA Issue'),
      '#attributes' => [
        'id' => 'edit-target-jira-issues'
      ],
      '#required' => TRUE,
      '#validated' => TRUE,
      '#options' => [],
      '#states' => [
        'invisible' => [
          '#edit-target-jira-projects' => ['value' => ''],
        ],
      ],
    ];
    $form['target_jira_users'] = [
      '#validated' => TRUE,
      '#type' => 'select',
      '#title' => 'Target Jira Users',
      '#attributes' => [
        'id' => 'edit-target-jira-users'
      ],
      '#options' => [],
      '#required' => TRUE,
      '#states' => [
        'invisible' =>[
          '#edit-target-jira-issues' => ['value' => '']
        ]
      ],
    ];
    $form['target_jira_users']['#options'] = $this->ajaxTargetJiraUsers($form, $form_state);
    $form['target_jira_user_email'] = [
      '#type' => 'textfield',
      '#title' => 'Email of the user you wish to log time',
      '#required' => TRUE,
      '#default_value' => '',
      '#states' => [
        'invisible' =>[
          '#edit-target-jira-users' => ['value' => '']
        ]
      ],
    ];
    $form['target_jira_user_token'] = [
      '#type' => 'textfield',
      '#title' => 'Token of the user you wish to log time',
      '#required' => TRUE,
      '#default_value' => '',
      '#states' => [
        'invisible' =>[
          '#edit-target-jira-users' => ['value' => '']
        ]
      ],
    ];
    $form['worklogs'] = [
      '#type' => 'hidden',
      '#value' => $worklogs,
    ];
    $form['actions'] = [
      '#type' => 'actions'
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#states' => [
        'invisible' => [
          '#edit-target-jira-user-token' => ['value' => ''],
        ],
      ],
    ];

    return $form;
  }

  public function onChangeTargetJiraProjects($form, $form_state) {
    $transfer = new JiraTransferController();
    $userID = Drupal::currentUser()->id();
    $userData = Drupal::service('user.data');
    $target_jira_namespace = $userData->get('jira_transfer_logged_time', $userID, 'jira_target_namespace');
    $jira_email = $userData->get('jira_transfer_logged_time', $userID, 'jira_account_email');
    $jira_token = $userData->get('jira_transfer_logged_time', $userID, 'jira_account_token');
    $project_key = $form_state->getValue('target_jira_projects');
    $target_issues_array = $transfer->getJiraIssues($target_jira_namespace, $jira_email, $jira_token, $project_key, 0);
    foreach ($target_issues_array as $issue => $value) {
      $form['edit_target_jira_issues_wrapper']['target_jira_issues']['#options'][$value->id] = $value->key;
    }

    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand("#edit-target-jira-issues-wrapper", ($form['edit_target_jira_issues_wrapper'])));
    return $response;
  }

  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    parent::validateForm($form, $form_state);
    $source_jira_projects = $form_state->getValue('source_jira_projects');
    if (!isset($source_jira_projects)) {
      $form_state->setErrorByName('source_jira_projects', $this->t('You must select a source project'));
    }

    $start_date = $form_state->getValue('start_date');
    $end_date = $form_state->getValue('end_date');
    if ($end_date < $start_date) {
      $form_state->setErrorByName('end_date', $this->t('The end date must be after the start date.'));
    }
  }


  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $this->getValues($form, $form_state);
    $worklogId = '';
    $transer = new JiraTransferController();
    $tableOptions = $form_state->getValue('issues_table');
    if(!empty($tableOptions)){
      foreach($tableOptions as $option => $value) {
        if ($value !== 0) {
          $currentIssue = $form['tableselect_wrapper']['issues_table']['#options'][$value];
          $worklogId = $currentIssue['worklogId'];
          $worklogs = $form_state->getValue('worklogs');
          foreach ($worklogs as $worklog => $log) {
            if (isset($log->id) && $worklogId === $log->id) {
              if (!isset($log->comment)) {
                $log->comment->content[0]->content[0]->text = '';
              }
              $transer->addWorklog($values['target_jira_namespace'], $form_state->getValue('target_jira_user_email'),
                $form_state->getValue('target_jira_user_token'), $log, $form_state->getValue('target_jira_issues'));
            }
          }
        }
      }
    }
  }

  public function onChangeSourceUserSelected(array &$form, FormStateInterface $form_state)
  {
    $form['tableselect_wrapper']['issues_table'] = Tableselect::processTableselect($form['tableselect_wrapper']['issues_table'], $form_state, $form);
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand("#tableselect-wrapper", ($form['tableselect_wrapper'])));
    return $response;
  }

  public function ajaxTargetJiraIssues(&$form,FormStateInterface $form_state){
    $form['target_jira_issues']['#options'] = [];
    $values = $this->getValues($form, $form_state);
    $project_key = $form_state->getValue('target_jira_projects');
    $issues = [];

    $transfer = new JiraTransferController();
    $issues = $transfer->getJiraIssues($values['target_jira_namespace'], $values['jira_email'], $values['jira_token'], $project_key, 0, $issues);
    $issue_options = [];

    if(empty($issues)){
      $issue_options = ['none' => 'No issues in the current project'];
    } else {
      foreach ($issues as $issue => $value) {
        $issue_options[$value->id] = $value->key;
      }
    }
    unset($form['target_jira_issues']['#title']);
    $form['target_jira_issues']['#options'] = $issue_options;
    return $form['target_jira_issues'];
  }

  public function ajaxTargetJiraUsers(&$form,FormStateInterface $form_state){
    $values = $this->getValues($form, $form_state);
    $users = [];
    $users_options = [];
    $transfer = new JiraTransferController();
    $users = $transfer->getJiraUsers($values['target_jira_namespace'], $values['jira_email'], $values['jira_token'], 0, $users);
    foreach ($users as $user => $value){
      $users_options[$value->accountId] = $value->displayName;
    }
    return $users_options;
  }

  public function populateTable(&$form,FormStateInterface $form_state){
    $worklogs = [];
    $table_data = [];
    $project_key = $form_state->getUserInput()['source_jira_projects'];
    if (isset($project_key)) {
      $transfer = new JiraTransferController();
      $issues = [];
      $start_date = $form_state->getUserInput()['start_date'];
      $end_date = $form_state->getUserInput()['end_date'];
      $values = $this->getValues($form, $form_state);
      $transfer->getJiraIssues($values['source_jira_namespace'], $values['jira_email'], $values['jira_token'], $project_key, 0, $issues);
      $selected_user = $form['source_jira_users']['#options'][$form_state->getUserInput()['field_source_users']];
      $transfer->getJiraIssuesWorklogs($values['source_jira_namespace'],  $values['jira_email'], $values['jira_token'], $start_date, $end_date, $issues, $worklogs, $selected_user);
      $transfer->getSpecificUserWorklogs($values['jira_user_id'], $worklogs);
      $i = 0;
      foreach ($worklogs as $worklog => $value){
        $issue_key = $transfer->getIssueKeyFromId($value->issueId, $issues);
        if(!isset($value->comment->content[0]->content[0]->text)){
          $taskDescription = '';
        } else {
          $taskDescription = $value->comment->content[0]->content[0]->text;
        }
        $table_data[$i] = [
          'worklogId' => $value->id,
          'taskId' => $issue_key,
          'taskDescription' => $taskDescription,
          'loggedTime' => $value->timeSpent,
        ];
        $i++;
      }
      $worklogs['table_data'] = $table_data;
    }
    return $worklogs;
  }

  public function getValues(array &$form, FormStateInterface $form_state){
    //Jira Authentication form data is stored in the user.data service
    $userData = \Drupal::service('user.data');
    $jira_email = $userData->get('jira_transfer_logged_time', $this->currentUser()->id(), 'jira_account_email');
    $jira_token = $userData->get('jira_transfer_logged_time', $this->currentUser()->id(), 'jira_account_token');
    $source_jira_namespace = $userData->get('jira_transfer_logged_time', $this->currentUser()->id(), 'jira_source_namespace');
    $target_jira_namespace = $userData->get('jira_transfer_logged_time', $this->currentUser()->id(), 'jira_target_namespace');

    $jira_user_id = $form_state->getUserInput();
    if(isset($jira_user_id['field_source_users'])){
      $jira_user_id = $jira_user_id['field_source_users'];
    } else {
      $jira_user_id = '';
    }
    $start_date = $form_state->getValue('start_date');
    //Because of the different states of the form this 'if' is required
    if($start_date instanceof \Drupal\Core\DateTime\DrupalDateTime){
      $start_date = $form_state->getValue('start_date')->format('Y-m-d\TH:i:s.vO');
      $end_date = $form_state->getValue('end_date')->format('Y-m-d\TH:i:s.vO');
    } else {
      $start_date = $start_date['date'] . ' ' . $start_date['time'];
      $start_date = strtotime($start_date);
      $start_date = date('Y-m-d\TH:i:s.vO', $start_date);
      $end_date = $form_state->getValue('end_date');
      $end_date = $end_date['date'] . ' ' . $end_date['time'];
      $end_date = strtotime($end_date);
      $end_date = date('Y-m-d\TH:i:s.vO', $end_date);
    }
    $values = [
      'jira_email' => $jira_email,
      'jira_token' => $jira_token,
      'source_jira_namespace' => $source_jira_namespace,
      'target_jira_namespace' => $target_jira_namespace,
      'jira_user_id' => $jira_user_id,
      'start_date' => $start_date,
      'end_date' => $end_date
    ];
    return $values;
  }
}
