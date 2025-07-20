<?php

require_once 'emailqueue.civix.php';

use CRM_Emailqueue_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function emailqueue_civicrm_config(&$config) {
  _emailqueue_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function emailqueue_civicrm_install() {
  _emailqueue_civix_civicrm_install();
  // Initialize the email queue settings and database
  // Set default settings

  Civi::settings()->set('emailqueue_enabled', FALSE);
  Civi::settings()->set('emailqueue_db_host', 'localhost');
  Civi::settings()->set('emailqueue_db_name', 'emailqueue');
  Civi::settings()->set('emailqueue_db_user', '');
  Civi::settings()->set('emailqueue_db_pass', '');
  Civi::settings()->set('emailqueue_batch_size', 50);
  Civi::settings()->set('emailqueue_retry_attempts', 3);

  // Log installation with client information
  $clientId = CRM_Emailqueue_Config::getCurrentClientId();
  CRM_Core_Error::debug_log_message("Email Queue Extension installed for client: {$clientId}");
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_postInstall
 */
function emailqueue_civicrm_postInstall() {
  _emailqueue_civix_civicrm_postInstall();

  // Initialize client settings after installation
  try {
    $clientId = CRM_Emailqueue_Config::getCurrentClientId();
    CRM_Emailqueue_Utils_ErrorHandler::info("Email Queue Extension post-install completed for client: {$clientId}");
  }
  catch (Exception $e) {
    CRM_Core_Error::debug_log_message('Email Queue post-install error: ' . $e->getMessage());
  }
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_uninstall
 */
function emailqueue_civicrm_uninstall() {
  $clientId = CRM_Emailqueue_Config::getCurrentClientId();

  // Clean up settings (but preserve DB connection info and client data)
  $settingsToKeep = [
    'emailqueue_db_host',
    'emailqueue_db_name',
    'emailqueue_db_user',
    'emailqueue_db_pass',
    'emailqueue_db_port',
    'emailqueue_client_id'  // Keep client_id for potential reinstall
  ];

  $allSettings = [
    'emailqueue_enabled',
    'emailqueue_multi_client_mode',
    'emailqueue_admin_client_access',
    'emailqueue_batch_size',
    'emailqueue_retry_attempts',
    'emailqueue_cleanup_days',
    'emailqueue_enable_tracking',
    'emailqueue_enable_validation',
    'emailqueue_log_level'
  ];

  foreach ($allSettings as $setting) {
    if (!in_array($setting, $settingsToKeep)) {
      Civi::settings()->revert($setting);
    }
  }

  CRM_Core_Error::debug_log_message("Email Queue Extension uninstalled for client: {$clientId}");
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function emailqueue_civicrm_enable() {
  _emailqueue_civix_civicrm_enable();

  $clientId = CRM_Emailqueue_Config::getCurrentClientId();
  CRM_Core_Error::debug_log_message("Email Queue Extension enabled for client: {$clientId}");
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_disable
 */
function emailqueue_civicrm_disable() {
  _emailqueue_civix_civicrm_disable();

  $clientId = CRM_Emailqueue_Config::getCurrentClientId();
  CRM_Core_Error::debug_log_message("Email Queue Extension disabled for client: {$clientId}");
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_upgrade
 *
function emailqueue_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _emailqueue_civix_civicrm_upgrade($op, $queue);
}
*/
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
  if (!$isEnabled) {
    return;
  }

  // $skipAlterMailerHook is used when emails are processed by this extension.
  if (isset($skipAlterMailerHook) && $skipAlterMailerHook) {
    // If we are skipping this hook, just return the original mailer
    if (CRM_Emailqueue_Config::isDebugMode()) {
      $clientId = CRM_Emailqueue_Config::getCurrentClientId();
      CRM_Core_Error::debug_log_message("Skipping emailqueue_civicrm_alterMailer hook for client: {$clientId}");
    }
    return;
  }

  // Get current client context for logging
  $clientId = CRM_Emailqueue_Config::getCurrentClientId();

  if (CRM_Emailqueue_Config::isDebugMode()) {
    CRM_Core_Error::debug_log_message("Email Queue intercepting mailer for client: {$clientId}");
  }

  // Replace the mailer with our custom email queue mailer
  $queueParams = array_merge($params, ['client_id' => $clientId]);
  $mailer = new CRM_Emailqueue_Mailer_QueueMailer($queueParams);
}

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_navigationMenu
 */
function emailqueue_civicrm_navigationMenu(&$menu) {
  $hasAdminAccess = CRM_Emailqueue_Config::hasAdminClientAccess();
  $isMultiClientMode = CRM_Emailqueue_Config::isMultiClientMode();
  $currentClientId = CRM_Emailqueue_Config::getCurrentClientId();

  // Main dashboard
  _emailqueue_civix_insert_navigation_menu($menu, 'Mailings', [
    'label' => E::ts('Email Queue Dashboard') . ($isMultiClientMode ? " ({$currentClientId})" : ''),
    'name' => 'emailqueue_dashboard_new',
    'url' => 'civicrm/emailqueue/dashboard-new',
    'permission' => 'administer CiviCRM',
    'operator' => 'OR',
    'separator' => 0,
  ]);

  // Email queue monitor
  _emailqueue_civix_insert_navigation_menu($menu, 'Mailings', [
    'label' => E::ts('Email Queue Monitor') . ($isMultiClientMode ? " ({$currentClientId})" : ''),
    'name' => 'emailqueue_monitor_adv',
    'url' => 'civicrm/emailqueue/monitoradv',
    'permission' => 'administer CiviCRM',
    'operator' => 'OR',
    'separator' => 0,
  ]);

  // Settings
  _emailqueue_civix_insert_navigation_menu($menu, 'Mailings', [
    'label' => E::ts('Email Queue Settings'),
    'name' => 'emailqueue_settings',
    'url' => 'civicrm/admin/emailqueue/settings',
    'permission' => 'administer CiviCRM',
    'operator' => 'OR',
    'separator' => 0,
  ]);

  // Multi-client management (admin only)
  if ($hasAdminAccess && $isMultiClientMode) {
    _emailqueue_civix_insert_navigation_menu($menu, 'Mailings', [
      'label' => E::ts('Multi-Client Management'),
      'name' => 'emailqueue_multiclient',
      'url' => 'civicrm/admin/emailqueue/multiclient',
      'permission' => 'administer CiviCRM',
      'operator' => 'OR',
      'separator' => 1,
    ]);

    // Client overview
    _emailqueue_civix_insert_navigation_menu($menu, 'Mailings', [
      'label' => E::ts('Client Overview'),
      'name' => 'emailqueue_client_overview',
      'url' => 'civicrm/admin/emailqueue/client-overview',
      'permission' => 'administer CiviCRM',
      'operator' => 'OR',
      'separator' => 0,
    ]);
  }

  _emailqueue_civix_navigationMenu($menu);
}

/**
 * Implements hook_civicrm_cron().
 *
 * Process the email queue when cron runs - respects client context.
 */
function emailqueue_civicrm_cron($jobManager) {
  $isEnabled = Civi::settings()->get('emailqueue_enabled');

  if ($isEnabled) {
    $clientId = CRM_Emailqueue_Config::getCurrentClientId();

    if (CRM_Emailqueue_Config::isDebugMode()) {
      CRM_Core_Error::debug_log_message("Email Queue cron processing for client: {$clientId}");
    }

    try {
      CRM_Emailqueue_BAO_Queue::processQueue();

      if (CRM_Emailqueue_Config::isDebugMode()) {
        $stats = CRM_Emailqueue_BAO_Queue::getQueueStats();
        CRM_Core_Error::debug_log_message("Email Queue cron completed for client {$clientId}. Pending: {$stats['pending']}, Sent: {$stats['sent']}, Failed: {$stats['failed']}");
      }
    }
    catch (Exception $e) {
      CRM_Emailqueue_Utils_ErrorHandler::handleException($e, [
        'operation' => 'cron_processing',
        'client_id' => $clientId
      ]);
    }
  }
}

/**
 * Implements hook_civicrm_permission().
 *
 * Define custom permissions for multi-client access.
 */
function emailqueue_civicrm_permission(&$permissions) {
  $permissions['access email queue'] = [
    E::ts('Email Queue: Access email queue for current client'),
    E::ts('Allows viewing and managing email queue for the current client only'),
  ];

  $permissions['administer email queue'] = [
    E::ts('Email Queue: Administer email queue system'),
    E::ts('Allows full administration of the email queue system including settings and multi-client access'),
  ];

  $permissions['access all clients email queue'] = [
    E::ts('Email Queue: Access all clients'),
    E::ts('Allows viewing and managing email queues for all clients (admin access)'),
  ];
}

/**
 * Implements hook_civicrm_entityTypes().
 *
 * Declare entity types provided by this module.
 */
function emailqueue_civicrm_entityTypes(&$entityTypes) {
  // We could potentially add EmailQueue as a custom entity type in the future
  // This would allow for more advanced API operations and relationships
}

/**
 * Implements hook_civicrm_alterAPIPermissions().
 *
 * Set permissions for Email Queue APIs based on client context.
 */
function emailqueue_civicrm_alterAPIPermissions($entity, $action, &$params, &$permissions) {
  if (strtolower($entity) === 'emailqueue') {
    // Default permission for most email queue operations
    $defaultPermission = ['access email queue'];

    switch (strtolower($action)) {
      case 'getclientstats':
      case 'switchclient':
      case 'resetclientcontext':
        // Admin-only operations
        $permissions[$entity][$action] = ['access all clients email queue'];
        break;

      case 'search':
      case 'preview':
      case 'getfilteroptions':
      case 'export':
      case 'bulkaction':
      case 'addtoqueue':
      case 'getstats':
      case 'processqueue':
        // Regular operations (client-specific)
        $permissions[$entity][$action] = $defaultPermission;
        break;

      default:
        // Fallback to default permission
        $permissions[$entity][$action] = $defaultPermission;
        break;
    }
  }

  // Admin APIs
  if (strtolower($entity) === 'emailqueueadmin') {
    $permissions[$entity] = ['administer email queue'];
  }
}

/**
 * Implements hook_civicrm_buildForm().
 *
 * Add client context information to relevant forms.
 */
function emailqueue_civicrm_buildForm($formName, &$form) {
  // Add client information to Email Queue forms
  $emailQueueForms = [
    'CRM_Emailqueue_Form_Settings',
    'CRM_Emailqueue_Page_DashboardNew',
    'CRM_Emailqueue_Page_Monitoradv'
  ];

  if (in_array($formName, $emailQueueForms)) {
    $clientInfo = CRM_Emailqueue_Config::getClientInfo();
    $form->assign('emailQueueClientInfo', $clientInfo);

    // Add JavaScript variables for client context
    CRM_Core_Resources::singleton()->addVars('emailqueue', [
      'currentClientId' => $clientInfo['current_client_id'],
      'multiClientMode' => $clientInfo['multi_client_mode'],
      'adminAccess' => $clientInfo['admin_access']
    ]);
  }
}

/**
 * Implements hook_civicrm_validateForm().
 *
 * Validate forms with client-specific rules.
 */
function emailqueue_civicrm_validateForm($formName, &$fields, &$files, &$form, &$errors) {
  if ($formName === 'CRM_Emailqueue_Form_Settings') {
    // Additional validation for client_id
    if (!empty($fields['emailqueue_client_id'])) {
      $clientId = trim($fields['emailqueue_client_id']);

      // Check for reserved client IDs
      $reservedIds = ['admin', 'system', 'default', 'test', 'demo'];
      if (in_array(strtolower($clientId), $reservedIds)) {
        $errors['emailqueue_client_id'] = E::ts('This client ID is reserved. Please choose a different one.');
      }

      // Check if client ID already exists for a different domain (if we have that info)
      // This could be implemented if we track domain-client relationships
    }
  }
}

/**
 * Implements hook_civicrm_pre().
 *
 * Add logging for significant Email Queue operations.
 */
function emailqueue_civicrm_pre($op, $objectName, $id, &$params) {
  // Log significant operations if debug mode is enabled
  if (CRM_Emailqueue_Config::isDebugMode()) {
    $clientId = CRM_Emailqueue_Config::getCurrentClientId();

    // Log operations that might affect the email queue
    $relevantObjects = ['Email', 'Mailing', 'MessageTemplate'];
    if (in_array($objectName, $relevantObjects) && in_array($op, ['create', 'edit', 'delete'])) {
      CRM_Emailqueue_Utils_ErrorHandler::debug("CiviCRM {$op} operation on {$objectName} for client {$clientId}", [
        'object_id' => $id,
        'operation' => $op,
        'object_name' => $objectName,
        'client_id' => $clientId
      ]);
    }
  }
}

/**
 * Implements hook_civicrm_post().
 *
 * Handle post-operation tasks for Email Queue.
 */
function emailqueue_civicrm_post($op, $objectName, $objectId, &$objectRef) {
  // Handle cleanup or additional processing after certain operations
  if (CRM_Emailqueue_Config::isEnabled() && CRM_Emailqueue_Config::isDebugMode()) {
    $clientId = CRM_Emailqueue_Config::getCurrentClientId();

    // Log completion of significant operations
    $relevantObjects = ['Email', 'Mailing'];
    if (in_array($objectName, $relevantObjects) && $op === 'create') {
      CRM_Emailqueue_Utils_ErrorHandler::debug("CiviCRM {$objectName} created for client {$clientId}", [
        'object_id' => $objectId,
        'client_id' => $clientId
      ]);
    }
  }
}

/**
 * Helper function to get client-aware cache keys.
 */
function emailqueue_get_cache_key($baseKey) {
  $clientId = CRM_Emailqueue_Config::getCurrentClientId();
  return "emailqueue_{$clientId}_{$baseKey}";
}

/**
 * Helper function to clear client-specific caches.
 */
function emailqueue_clear_client_cache($clientId = null) {
  if ($clientId === NULL) {
    $clientId = CRM_Emailqueue_Config::getCurrentClientId();
  }

  $cacheKeys = [
    CRM_Emailqueue_Config::CACHE_KEY_STATS,
    CRM_Emailqueue_Config::CACHE_KEY_FILTER_OPTIONS
  ];

  foreach ($cacheKeys as $key) {
    $clientKey = "emailqueue_{$clientId}_{$key}";
    Civi::cache()->delete($clientKey);
  }
}

/**
 * Utility function to validate client access.
 */
function emailqueue_validate_client_access($requestedClientId) {
  $currentClientId = CRM_Emailqueue_Config::getCurrentClientId();
  $hasAdminAccess = CRM_Emailqueue_Config::hasAdminClientAccess();

  // Allow access if:
  // 1. It's the current client
  // 2. User has admin access to all clients
  return ($requestedClientId === $currentClientId) || $hasAdminAccess;
}

/**
 * Get client-specific configuration for JavaScript.
 */
function emailqueue_get_js_config() {
  return [
    'currentClientId' => CRM_Emailqueue_Config::getCurrentClientId(),
    'multiClientMode' => CRM_Emailqueue_Config::isMultiClientMode(),
    'adminAccess' => CRM_Emailqueue_Config::hasAdminClientAccess(),
    'debugMode' => CRM_Emailqueue_Config::isDebugMode(),
    'extensionEnabled' => CRM_Emailqueue_Config::isEnabled()
  ];
}
