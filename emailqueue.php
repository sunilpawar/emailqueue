<?php

require_once 'emailqueue.civix.php';

use CRM_Emailqueue_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function emailqueue_civicrm_config(&$config): void {
  _emailqueue_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function emailqueue_civicrm_install(): void {
  _emailqueue_civix_civicrm_install();
  // Initialize the email queue settings and database
  // Set default settings
  /*
  Civi::settings()->set('emailqueue_enabled', FALSE);
  Civi::settings()->set('emailqueue_db_host', 'localhost');
  Civi::settings()->set('emailqueue_db_name', 'emailqueue');
  Civi::settings()->set('emailqueue_db_user', '');
  Civi::settings()->set('emailqueue_db_pass', '');
  Civi::settings()->set('emailqueue_batch_size', 50);
  Civi::settings()->set('emailqueue_retry_attempts', 3);

  // Create the email queue database tables
  //CRM_Emailqueue_BAO_Queue::createTables();
  */
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_uninstall
 */
function emailqueue_civicrm_uninstall() {
  //_emailqueue_civix_civicrm_uninstall();

  // Clean up settings
  Civi::settings()->revert('emailqueue_enabled');
  Civi::settings()->revert('emailqueue_db_host');
  Civi::settings()->revert('emailqueue_db_name');
  Civi::settings()->revert('emailqueue_db_user');
  Civi::settings()->revert('emailqueue_db_pass');
  Civi::settings()->revert('emailqueue_batch_size');
  Civi::settings()->revert('emailqueue_retry_attempts');
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function emailqueue_civicrm_enable(): void {
  _emailqueue_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_alterMailer().
 *
 * This hook is called when CiviCRM is about to send an email.
 * We intercept this to use our email queue system instead.
 */
function emailqueue_civicrm_alterMailer(&$mailer, $driver, $params) {
  global $skipAlterMailerHook;
  // Check if email queue is enabled
  $isEnabled = Civi::settings()->get('emailqueue_enabled');
  if ($isEnabled) {
    // $skipAlterMailerHook is used when emails processed by this extension.
    if (isset($skipAlterMailerHook) && $skipAlterMailerHook) {
      // If we are skipping this hook, just return the original mailer
      CRM_Core_Error::debug_log_message('Skipping emailqueue_civicrm_alterMailer hook');
      return;
    }
    // Replace the mailer with our custom email queue mailer
    $mailer = new CRM_Emailqueue_Mailer_QueueMailer($params);
  }
}

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_navigationMenu
 */
function emailqueue_civicrm_navigationMenu(&$menu) {
  _emailqueue_civix_insert_navigation_menu($menu, 'Mailings', [
    'label' => E::ts('Email Queue Settings'),
    'name' => 'emailqueue_settings',
    'url' => 'civicrm/admin/emailqueue/settings',
    'permission' => 'administer CiviCRM',
    'operator' => 'OR',
    'separator' => 0,
  ]);
  _emailqueue_civix_navigationMenu($menu);
}

/**
 * Implements hook_civicrm_cron().
 *
 * Process the email queue when cron runs.
 */
function emailqueue_civicrm_cron($jobManager) {
  $isEnabled = Civi::settings()->get('emailqueue_enabled');

  if ($isEnabled) {
    CRM_Emailqueue_BAO_Queue::processQueue();
  }
}
