<div class="crm-block crm-content-block crm-emailqueue-monitor-block">

  <div class="crm-submit-buttons">
    {if $isEnabled}
      <a href="#" id="process-queue-btn" class="button">{ts}Process Queue Now{/ts}</a>
      <a href="#" id="retry-failed-btn" class="button">{ts}Retry Failed Emails{/ts}</a>
    {/if}
    <a href="{crmURL p='civicrm/admin/emailqueue/settings'}" class="button">{ts}Settings{/ts}</a>
  </div>

  <h3>
    {ts}Email Queue Monitor{/ts}
    {if $currentClientId}
      <span class="client-badge">Client: {$currentClientId}</span>
    {/if}
  </h3>

  {if not $isEnabled}
    <div class="messages warning no-popup">
      <div class="icon inform-icon"></div>
      {ts}Email Queue System is disabled. <a href="{crmURL p='civicrm/admin/emailqueue/settings'}">Enable it in settings</a> to start using the queue.{/ts}
    </div>
  {/if}

  {if $queueStats}
    <div class="crm-section">
      <h4>{ts}Queue Statistics{/ts}</h4>
      <div class="crm-container">
        <table class="display dataTable">
          <thead>
          <tr>
            <th>{ts}Status{/ts}</th>
            <th>{ts}Count{/ts}</th>
            <th>{ts}Description{/ts}</th>
          </tr>
          </thead>
          <tbody>
          <tr class="{if $queueStats.pending > 0}highlight{/if}">
            <td><span class="badge badge-warning">{ts}Pending{/ts}</span></td>
            <td>{$queueStats.pending}</td>
            <td>{ts}Emails waiting to be sent{/ts}</td>
          </tr>
          <tr class="{if $queueStats.processing > 0}highlight{/if}">
            <td><span class="badge badge-info">{ts}Processing{/ts}</span></td>
            <td>{$queueStats.processing}</td>
            <td>{ts}Emails currently being processed{/ts}</td>
          </tr>
          <tr class="{if $queueStats.sent > 0}highlight{/if}">
            <td><span class="badge badge-success">{ts}Sent{/ts}</span></td>
            <td>{$queueStats.sent}</td>
            <td>{ts}Successfully sent emails{/ts}</td>
          </tr>
          <tr class="{if $queueStats.failed > 0}highlight{/if}">
            <td><span class="badge badge-danger">{ts}Failed{/ts}</span></td>
            <td>{$queueStats.failed}</td>
            <td>{ts}Failed emails (max retries reached){/ts}</td>
          </tr>
          <tr>
            <td><span class="badge badge-secondary">{ts}Cancelled{/ts}</span></td>
            <td>{$queueStats.cancelled}</td>
            <td>{ts}Manually cancelled emails{/ts}</td>
          </tr>
          </tbody>
        </table>
      </div>
    </div>
  {/if}

  {if $recentEmails}
    <div class="crm-section">
      <h4>{ts}Recent Emails{/ts}</h4>
      <div class="crm-container">
        <table class="display dataTable">
          <thead>
          <tr>
            <th>{ts}ID{/ts}</th>
            <th>{ts}To{/ts}</th>
            <th>{ts}Subject{/ts}</th>
            <th>{ts}Status{/ts}</th>
            <th>{ts}Priority{/ts}</th>
            <th>{ts}Created{/ts}</th>
            <th>{ts}Sent{/ts}</th>
            <th>{ts}Retries{/ts}</th>
            {if $isMultiClientMode && $hasAdminAccess}
              <th>{ts}Client{/ts}</th>
            {/if}
            <th>{ts}Actions{/ts}</th>
          </tr>
          </thead>
          <tbody>
          {foreach from=$recentEmails item=email}
            <tr>
              <td>{$email.id}</td>
              <td>{$email.to_email}</td>
              <td title="{$email.subject|escape}">{$email.subject|truncate:50}</td>
              <td>
                {if $email.status == 'pending'}
                  <span class="badge badge-warning">{ts}Pending{/ts}</span>
                {elseif $email.status == 'processing'}
                  <span class="badge badge-info">{ts}Processing{/ts}</span>
                {elseif $email.status == 'sent'}
                  <span class="badge badge-success">{ts}Sent{/ts}</span>
                {elseif $email.status == 'failed'}
                  <span class="badge badge-danger">{ts}Failed{/ts}</span>
                {elseif $email.status == 'cancelled'}
                  <span class="badge badge-secondary">{ts}Cancelled{/ts}</span>
                {/if}
              </td>
              <td>{$email.priority}</td>
              <td>{$email.created_date|crmDate}</td>
              <td>{if $email.sent_date}{$email.sent_date|crmDate}{else}-{/if}</td>
              <td>{$email.retry_count}</td>
              {if $isMultiClientMode && $hasAdminAccess}
                <td><span class="client-tag">{$email.client_id|default:'-'}</span></td>
              {/if}
              <td>
                {if $email.status == 'pending' or $email.status == 'failed'}
                  <a href="#" class="cancel-email-btn" data-email-id="{$email.id}">{ts}Cancel{/ts}</a>
                {/if}
              </td>
            </tr>
          {/foreach}
          </tbody>
        </table>
      </div>
    </div>
  {/if}

  {if $failedEmails}
    <div class="crm-section">
      <h4>{ts}Failed Emails{/ts}</h4>
      <div class="crm-container">
        <table class="display dataTable">
          <thead>
          <tr>
            <th>{ts}ID{/ts}</th>
            <th>{ts}To{/ts}</th>
            <th>{ts}Subject{/ts}</th>
            <th>{ts}Error{/ts}</th>
            <th>{ts}Created{/ts}</th>
            <th>{ts}Retries{/ts}</th>
          </tr>
          </thead>
          <tbody>
          {foreach from=$failedEmails item=email}
            <tr>
              <td>{$email.id}</td>
              <td>{$email.to_email}</td>
              <td title="{$email.subject|escape}">{$email.subject|truncate:50}</td>
              <td title="{$email.error_message|escape}">{$email.error_message|truncate:100}</td>
              <td>{$email.created_date|crmDate}</td>
              <td>{$email.retry_count}</td>
            </tr>
          {/foreach}
          </tbody>
        </table>
      </div>
    </div>
  {/if}

  <div id="action-result" style="margin-top: 20px;"></div>

</div>

{literal}
  <style>
    .client-badge {
      background: #e7f3ff;
      color: #2c5aa0;
      padding: 4px 8px;
      border-radius: 12px;
      font-size: 12px;
      font-weight: 500;
      margin-left: 10px;
    }
    
    .client-tag {
      background: #f8f9fa;
      color: #495057;
      padding: 2px 6px;
      border-radius: 3px;
      font-size: 11px;
      font-family: monospace;
    }
  </style>

  <script type="text/javascript">
    CRM.$(function($) {

      // Process queue now
      $('#process-queue-btn').click(function(e) {
        e.preventDefault();
        performAction('process_queue', 'Processing queue...', $(this));
      });

      // Retry failed emails
      $('#retry-failed-btn').click(function(e) {
        e.preventDefault();
        if (confirm('Are you sure you want to retry all failed emails?')) {
          performAction('retry_failed', 'Retrying failed emails...', $(this));
        }
      });

      // Cancel email
      $('.cancel-email-btn').click(function(e) {
        e.preventDefault();
        var emailId = $(this).data('email-id');
        if (confirm('Are you sure you want to cancel this email?')) {
          performAction('cancel_email', 'Cancelling email...', $(this), {email_id: emailId});
        }
      });

      function performAction(action, loadingText, $btn, extraParams) {
        var originalText = $btn.text();
        var $result = $('#action-result');

        $btn.text(loadingText).prop('disabled', true);
        $result.html('');

        var params = {action: action};
        if (extraParams) {
          $.extend(params, extraParams);
        }

        $.ajax({
          url: CRM.url('civicrm/ajax/emailqueue/action'),
          type: 'POST',
          data: params,
          dataType: 'json'
        })
          .done(function(result) {
            if (result.success) {
              $result.html('<div class="crm-success">' + result.message + '</div>');
              // Refresh page after successful action
              setTimeout(function() {
                location.reload();
              }, 2000);
            } else {
              $result.html('<div class="crm-error">' + result.message + '</div>');
            }
          })
          .fail(function() {
            $result.html('<div class="crm-error">Action failed</div>');
          })
          .always(function() {
            $btn.text(originalText).prop('disabled', false);
          });
      }

      // Auto-refresh every 30 seconds
      setInterval(function() {
        location.reload();
      }, 30000);

    });
  </script>
{/literal}
