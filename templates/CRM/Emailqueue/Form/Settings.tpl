<div class="crm-block crm-form-block crm-emailqueue-settings-form-block">

  <div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="top"}
  </div>

  <h3>{ts}Email Queue System Settings{/ts}</h3>

  <div class="crm-section">
    <div class="label">{$form.emailqueue_enabled.label}</div>
    <div class="content">
      {$form.emailqueue_enabled.html}
      <div class="description">
        {ts}Enable the email queue system to queue emails in a separate database instead of sending them immediately.{/ts}
      </div>
    </div>
    <div class="clear"></div>
  </div>

  <fieldset id="emailqueue-db-settings">
    <legend>{ts}Database Settings{/ts}</legend>

    <div class="crm-section">
      <div class="label">{$form.emailqueue_db_host.label} {if $action == 2}{include file='CRM/Core/I18n/Dialog.tpl' table='civicrm_setting' field='emailqueue_db_host' id=$settingID}{/if}</div>
      <div class="content">
        {$form.emailqueue_db_host.html}
        <div class="description">
          {ts}Database host for the email queue (e.g., localhost, 127.0.0.1){/ts}
        </div>
      </div>
      <div class="clear"></div>
    </div>

    <div class="crm-section">
      <div class="label">{$form.emailqueue_db_name.label}</div>
      <div class="content">
        {$form.emailqueue_db_name.html}
        <div class="description">
          {ts}Name of the database that will store the email queue{/ts}
        </div>
      </div>
      <div class="clear"></div>
    </div>

    <div class="crm-section">
      <div class="label">{$form.emailqueue_db_user.label}</div>
      <div class="content">
        {$form.emailqueue_db_user.html}
        <div class="description">
          {ts}Database username with read/write access to the email queue database{/ts}
        </div>
      </div>
      <div class="clear"></div>
    </div>

    <div class="crm-section">
      <div class="label">{$form.emailqueue_db_pass.label}</div>
      <div class="content">
        {$form.emailqueue_db_pass.html}
        <div class="description">
          {ts}Database password{/ts}
        </div>
      </div>
      <div class="clear"></div>
    </div>

    <div class="crm-section">
      <div class="label"></div>
      <div class="content">
        <a href="#" id="test-connection-btn" class="button">{ts}Test Database Connection{/ts}</a>
        <div id="connection-result" style="margin-top: 10px;"></div>
      </div>
      <div class="clear"></div>
    </div>
  </fieldset>

  <fieldset id="emailqueue-processing-settings">
    <legend>{ts}Processing Settings{/ts}</legend>

    <div class="crm-section">
      <div class="label">{$form.emailqueue_batch_size.label}</div>
      <div class="content">
        {$form.emailqueue_batch_size.html}
        <div class="description">
          {ts}Number of emails to process in each batch during cron runs{/ts}
        </div>
      </div>
      <div class="clear"></div>
    </div>

    <div class="crm-section">
      <div class="label">{$form.emailqueue_retry_attempts.label}</div>
      <div class="content">
        {$form.emailqueue_retry_attempts.html}
        <div class="description">
          {ts}Maximum number of retry attempts for failed emails{/ts}
        </div>
      </div>
      <div class="clear"></div>
    </div>
  </fieldset>

  {if $queueStats}
    <fieldset id="emailqueue-stats">
      <legend>{ts}Queue Statistics{/ts}</legend>

      <div class="crm-section">
        <div class="content">
          <table class="display">
            <thead>
            <tr>
              <th>{ts}Status{/ts}</th>
              <th>{ts}Count{/ts}</th>
            </tr>
            </thead>
            <tbody>
            <tr>
              <td>{ts}Pending{/ts}</td>
              <td>{$queueStats.pending}</td>
            </tr>
            <tr>
              <td>{ts}Processing{/ts}</td>
              <td>{$queueStats.processing}</td>
            </tr>
            <tr>
              <td>{ts}Sent{/ts}</td>
              <td>{$queueStats.sent}</td>
            </tr>
            <tr>
              <td>{ts}Failed{/ts}</td>
              <td>{$queueStats.failed}</td>
            </tr>
            <tr>
              <td>{ts}Cancelled{/ts}</td>
              <td>{$queueStats.cancelled}</td>
            </tr>
            </tbody>
          </table>
        </div>
        <div class="clear"></div>
      </div>
    </fieldset>
  {/if}

  <div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>

</div>

{literal}
  <script type="text/javascript">
    CRM.$(function($) {

      // Toggle database settings based on enable checkbox
      function toggleDatabaseSettings() {
        if ($('#emailqueue_enabled').is(':checked')) {
          $('#emailqueue-db-settings').show();
          $('#emailqueue-processing-settings').show();
        } else {
          $('#emailqueue-db-settings').hide();
          $('#emailqueue-processing-settings').hide();
        }
      }

      $('#emailqueue_enabled').change(toggleDatabaseSettings);
      toggleDatabaseSettings(); // Initial state

      // Test database connection
      $('#test-connection-btn').click(function(e) {
        e.preventDefault();

        var $btn = $(this);
        var $result = $('#connection-result');

        $btn.text('Testing...').prop('disabled', true);
        $result.html('');

        var data = {
          host: $('#emailqueue_db_host').val(),
          name: $('#emailqueue_db_name').val(),
          user: $('#emailqueue_db_user').val(),
          pass: $('#emailqueue_db_pass').val()
        };

        CRM.api3('Emailqueue', 'testconnection', data)
          .done(function(result) {
            console.log(result);
            if (result.values.success) {
              $result.html('<div class="crm-success">' + result.values.message + '</div>');
            } else {
              $result.html('<div class="crm-error">' + result.values.message + '</div>');
            }
          })
          .fail(function(xhr) {
            $result.html('<div class="crm-error">Connection test failed</div>');
          })
          .always(function() {
            $btn.text('Test Database Connection').prop('disabled', false);
          });
      });

    });
  </script>
{/literal}
