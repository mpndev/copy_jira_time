<?php

namespace Drupal\jira_transfer_logged_time\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class JiraAuthForm extends FormBase {


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
    return 'jira_authentication_form';
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
    $userData = \Drupal::service('user.data');

    $form['jira_account_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Jira Account Email'),
      '#default_value' => $userData->get('jira_transfer_logged_time', $this->currentUser()->id(), 'jira_account_email'),
      '#description' => $this->t('Enter your JIRA account email.'),
      '#required' => TRUE,
    ];

    $form['jira_account_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Jira Account Token'),
      '#default_value' => $userData->get('jira_transfer_logged_time', $this->currentUser()->id(), 'jira_account_token'),
      '#description' => $this->t('Enter your JIRA account token. You can generate it from <a href="https://id.atlassian.com/manage/api-tokens" target="_blank">here</a>'),
      '#required' => TRUE,
    ];

    $form['jira_source_namespace'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Source Jira Namespace'),
      '#default_value' => $userData->get('jira_transfer_logged_time', $this->currentUser()->id(), 'jira_source_namespace'),
      '#description' => $this->t('Enter the <b>SOURCE</b> JIRA namespace you wish to extract the logs from. Example: https://www.<i><b>namespace</b></i>.atlassian.net'),
      '#required' => TRUE,
    ];

    $form['jira_target_namespace'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Target Jira Namespace'),
      '#default_value' => $userData->get('jira_transfer_logged_time', $this->currentUser()->id(), 'jira_target_namespace'),
      '#description' => $this->t('Enter the <b>TARGET</b> JIRA namespace you wish to extract the logs to. Example: https://www.<i><b>namespace</b></i>.atlassian.net'),
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions'
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    parent::validateForm($form, $form_state);

    $jira_token = $form_state->getValue('jira_account_token');

    if(strlen($jira_token) < 5){
      $form_state->setErrorByName('jira_account_token', $this->t('The token length is too short.'));
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
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $jira_email = $form_state->getValue('jira_account_email');
    $jira_token = $form_state->getValue('jira_account_token');
    $source_jira_namespace = $form_state->getValue('jira_source_namespace');
    $target_jira_namespace = $form_state->getValue('jira_target_namespace');


    $userData = \Drupal::service('user.data');
    $userData->set('jira_transfer_logged_time', $this->currentUser()->id(), 'jira_account_email', $jira_email);
    $userData->set('jira_transfer_logged_time', $this->currentUser()->id(), 'jira_account_token', $jira_token);
    $userData->set('jira_transfer_logged_time', $this->currentUser()->id(), 'jira_source_namespace', $source_jira_namespace);
    $userData->set('jira_transfer_logged_time', $this->currentUser()->id(), 'jira_target_namespace', $target_jira_namespace);

    $form_state->setRedirect('jira_transfer_logged_time.transfer_form');
    return;
  }
}
