{* HEADER *}
<div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="top"}
</div>

<div class="crm-form-block crm-emailqueue-settings-form-block">

  {* System Status *}
  <div class="crm-section">
    <div class="label">{ts}System Status{/ts}</div>
    <div class="content">
      {if $currentClientInfo.current_client_id}
        <div class="crm-info-panel">
          <div class="icon ui-icon-info"></div>
          <strong>{ts}Current Client:{/ts}</strong> {$currentClientInfo.current_client_id}
          {if $currentClientInfo.multi_client_mode}
            <br><strong>{ts}Multi-Client Mode:{/ts}</strong> {ts}Enabled{/ts}
            {if $currentClientInfo.admin_access}
              <br><strong>{ts}Admin Access:{/ts}</strong> {ts}Enabled{/ts}
            {/if}
          {/if}
        </div>
      {/if}
    </div>
  </div>

  {* Basic Settings *}
  <fieldset class="crm-collapsible">
    <legend class="collapsible-title">{ts}Basic Settings{/ts}</legend>
    <div class="crm-section">
      <div class="label">{$form.emailqueue_enabled.label}</div>
      <div class="content">{$form.emailqueue_enabled.html}
        <div class="description">{ts}Enable the Email Queue System to queue emails for delayed processing{/ts}</div>
      </div>
    </div>
  </fieldset>

  {* Multi-Client Configuration *}
  <fieldset class="crm-collapsible">
    <legend class="collapsible-title">{ts}Client Configuration{/ts}</legend>

    <div class="crm-section">
      <div class="label">{$form.emailqueue_client_id.label} <span class="crm-marker">*</span></div>
      <div class="content">
        {$form.emailqueue_client_id.html}
        <div class="description">
          {ts}Unique identifier for this client. Use letters, numbers, underscores, and hyphens only.{/ts}
          <br><em>{ts}Examples: organization_name, domain_1, client_abc{/ts}</em>
        </div>
        <div class="crm-section-buttons">
          <a href="#" class="button" id="generate-client-id" title="{ts}Generate a client ID based on your organization{/ts}">
            <i class="crm-i fa-magic"></i> {ts}Auto-Generate{/ts}
          </a>
          <a href="#" class="button" id="validate-client-id" title="{ts}Check if this client ID is valid{/ts}">
            <i class="crm-i fa-check-circle"></i> {ts}Validate{/ts}
          </a>
        </div>
        <div id="client-id-validation-result" class="crm-section-validation"></div>
      </div>
    </div>

    <div class="crm-section">
      <div class="label">{$form.emailqueue_multi_client_mode.label}</div>
      <div class="content">{$form.emailqueue_multi_client_mode.html}
        <div class="description">{ts}Enable multi-client mode to support multiple isolated email queues in the same database{/ts}</div>
      </div>
    </div>

    <div class="crm-section">
      <div class="label">{$form.emailqueue_admin_client_access.label}</div>
      <div class="content">{$form.emailqueue_admin_client_access.html}
        <div class="description">{ts}Allow administrators to view and manage email queues for all clients{/ts}</div>
      </div>
    </div>
  </fieldset>

  {* Database Settings *}
  <fieldset class="crm-collapsible">
    <legend class="collapsible-title">{ts}Database Settings{/ts}</legend>
    <div class="crm-section">
      <div class="label">{$form.emailqueue_db_host.label} <span class="crm-marker">*</span></div>
      <div class="content">{$form.emailqueue_db_host.html}
        <div class="description">{ts}Database server hostname or IP address{/ts}</div>
      </div>
    </div>

    <div class="crm-section">
      <div class="label">{$form.emailqueue_db_name.label} <span class="crm-marker">*</span></div>
      <div class="content">{$form.emailqueue_db_name.html}
        <div class="description">{ts}Database name for email queue storage{/ts}</div>
      </div>
    </div>

    <div class="crm-section">
      <div class="label">{$form.emailqueue_db_user.label} <span class="crm-marker">*</span></div>
      <div class="content">{$form.emailqueue_db_user.html}
        <div class="description">{ts}Database username{/ts}</div>
      </div>
    </div>

    <div class="crm-section">
      <div class="label">{$form.emailqueue_db_pass.label}</div>
      <div class="content">{$form.emailqueue_db_pass.html}
        <div class="description">{ts}Database password{/ts}</div>
      </div>
    </div>

    <div class="crm-section">
      <div class="content">
        <a href="#" class="button" id="test-connection" title="{ts}Test database connection{/ts}">
          <i class="crm-i fa-plug"></i> {ts}Test Connection{/ts}
        </a>
        <div id="connection-test-result" class="crm-section-validation"></div>
      </div>
    </div>
  </fieldset>

  {* Processing Settings *}
  <fieldset class="crm-collapsible">
    <legend class="collapsible-title">{ts}Processing Settings{/ts}</legend>
    <div class="crm-section">
      <div class="label">{$form.emailqueue_batch_size.label} <span class="crm-marker">*</span></div>
      <div class="content">{$form.emailqueue_batch_size.html}
        <div class="description">{ts}Number of emails to process in each batch (1-1000){/ts}</div>
      </div>
    </div>

    <div class="crm-section">
      <div class="label">{$form.emailqueue_retry_attempts.label} <span class="crm-marker">*</span></div>
      <div class="content">{$form.emailqueue_retry_attempts.html}
        <div class="description">{ts}Maximum number of retry attempts for failed emails{/ts}</div>
      </div>
    </div>
  </fieldset>

  {* Client Statistics (if admin access) *}
  {if $clientStats}
    <fieldset class="crm-collapsible collapsed">
      <legend class="collapsible-title">{ts}Client Statistics{/ts}</legend>
      <div class="crm-section">
        <div class="content">
          <table class="crm-table-striped">
            <thead>
            <tr>
              <th>{ts}Client ID{/ts}</th>
              <th>{ts}Total Emails{/ts}</th>
              <th>{ts}Pending{/ts}</th>
              <th>{ts}Sent{/ts}</th>
              <th>{ts}Failed{/ts}</th>
              <th>{ts}Last Activity{/ts}</th>
            </tr>
            </thead>
            <tbody>
            {foreach from=$clientStats item=client}
              <tr class="{if $client.client_id eq $currentClientInfo.current_client_id}crm-row-selected{/if}">
                <td>
                  <strong>{$client.client_id}</strong>
                  {if $client.client_id eq $currentClientInfo.current_client_id}
                    <span class="crm-marker"> (current)</span>
                  {/if}
                </td>
                <td>{$client.total_emails|number_format}</td>
                <td class="{if $client.pending > 100}crm-error{elseif $client.pending > 50}crm-warning{/if}">
                  {$client.pending|number_format}
                </td>
                <td class="crm-ok">{$client.sent|number_format}</td>
                <td class="{if $client.failed > 0}crm-error{/if}">{$client.failed|number_format}</td>
                <td>{$client.last_activity|crmDate}</td>
              </tr>
            {/foreach}
            </tbody>
          </table>
        </div>
      </div>
    </fieldset>
  {/if}

</div>

{* FOOTER *}
<div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>

{* JavaScript for enhanced functionality *}
{literal}
  <script type="text/javascript">
    CRM.$(function($) {
      // Test database connection
      $('#test-connection').click(function(e) {
        e.preventDefault();
        var button = $(this);
        var resultDiv = $('#connection-test-result');

        button.prop('disabled', true).html('<i class="crm-i fa-spinner fa-spin"></i> Testing...');
        resultDiv.removeClass('crm-ok crm-error').html('');

        var params = {
          host: $('input[name="emailqueue_db_host"]').val(),
          name: $('input[name="emailqueue_db_name"]').val(),
          user: $('input[name="emailqueue_db_user"]').val(),
          pass: $('input[name="emailqueue_db_pass"]').val()
        };

        CRM.api3('Emailqueue', 'testconnection', params)
          .done(function(result) {
            if (result.success) {
              resultDiv.addClass('crm-ok').html('<i class="crm-i fa-check"></i> ' + result.message);
            } else {
              resultDiv.addClass('crm-error').html('<i class="crm-i fa-times"></i> ' + result.message);
            }
          })
          .fail(function(xhr, status, error) {
            resultDiv.addClass('crm-error').html('<i class="crm-i fa-times"></i> Connection failed: ' + error);
          })
          .always(function() {
            button.prop('disabled', false).html('<i class="crm-i fa-plug"></i> Test Connection');
          });
      });

      // Generate client ID
      $('#generate-client-id').click(function(e) {
        e.preventDefault();
        var button = $(this);
        var input = $('input[name="emailqueue_client_id"]');

        button.prop('disabled', true).html('<i class="crm-i fa-spinner fa-spin"></i> Generating...');

        CRM.api3('Emailqueue', 'generateclientid', {})
          .done(function(result) {
            if (result.success) {
              input.val(result.client_id);
              CRM.alert(result.message, 'Client ID Generated', 'success');
              // Trigger validation
              $('#validate-client-id').click();
            } else {
              CRM.alert(result.message, 'Generation Failed', 'error');
            }
          })
          .fail(function(xhr, status, error) {
            CRM.alert('Failed to generate client ID: ' + error, 'Generation Failed', 'error');
          })
          .always(function() {
            button.prop('disabled', false).html('<i class="crm-i fa-magic"></i> Auto-Generate');
          });
      });

      // Validate client ID
      $('#validate-client-id').click(function(e) {
        e.preventDefault();
        var button = $(this);
        var input = $('input[name="emailqueue_client_id"]');
        var resultDiv = $('#client-id-validation-result');
        var clientId = input.val().trim();

        if (!clientId) {
          resultDiv.addClass('crm-error').html('<i class="crm-i fa-times"></i> Client ID is required');
          return;
        }

        button.prop('disabled', true).html('<i class="crm-i fa-spinner fa-spin"></i> Validating...');
        resultDiv.removeClass('crm-ok crm-error crm-warning').html('');

        CRM.api3('Emailqueue', 'validateclientid', {client_id: clientId})
          .done(function(result) {
            if (result.is_valid) {
              if (result.exists_in_database) {
                resultDiv.addClass('crm-warning').html('<i class="crm-i fa-exclamation-triangle"></i> ' + result.message);
              } else {
                resultDiv.addClass('crm-ok').html('<i class="crm-i fa-check"></i> ' + result.message);
              }
            } else {
              resultDiv.addClass('crm-error').html('<i class="crm-i fa-times"></i> ' + result.message);
            }
          })
          .fail(function(xhr, status, error) {
            resultDiv.addClass('crm-error').html('<i class="crm-i fa-times"></i> Validation failed: ' + error);
          })
          .always(function() {
            button.prop('disabled', false).html('<i class="crm-i fa-check-circle"></i> Validate');
          });
      });

      // Auto-validate client ID on change
      $('input[name="emailqueue_client_id"]').on('blur', function() {
        if ($(this).val().trim()) {
          $('#validate-client-id').click();
        }
      });

      // Show/hide admin options based on multi-client mode
      $('input[name="emailqueue_multi_client_mode"]').change(function() {
        var adminSection = $('input[name="emailqueue_admin_client_access"]').closest('.crm-section');
        if ($(this).is(':checked')) {
          adminSection.show();
        } else {
          adminSection.hide();
          $('input[name="emailqueue_admin_client_access"]').prop('checked', false);
        }
      }).trigger('change');
    });
  </script>
{/literal}

{* CSS for enhanced styling *}
{literal}
  <style type="text/css">
    .crm-emailqueue-settings-form-block .crm-info-panel {
      background-color: #d1ecf1;
      border: 1px solid #bee5eb;
      border-radius: 4px;
      padding: 10px;
      margin-bottom: 15px;
    }

    .crm-emailqueue-settings-form-block .crm-info-panel .icon {
      float: left;
      margin-right: 8px;
      margin-top: 2px;
    }

    .crm-section-buttons {
      margin-top: 8px;
    }

    .crm-section-buttons .button {
      margin-right: 10px;
      font-size: 11px;
      padding: 4px 8px;
    }

    .crm-section-validation {
      margin-top: 8px;
      padding: 6px 10px;
      border-radius: 3px;
      font-size: 12px;
    }

    .crm-section-validation.crm-ok {
      background-color: #d4edda;
      border: 1px solid #c3e6cb;
      color: #155724;
    }

    .crm-section-validation.crm-error {
      background-color: #f8d7da;
      border: 1px solid #f5c6cb;
      color: #721c24;
    }

    .crm-section-validation.crm-warning {
      background-color: #fff3cd;
      border: 1px solid #ffeaa7;
      color: #856404;
    }

    .crm-table-striped {
      width: 100%;
      margin-top: 10px;
    }

    .crm-table-striped th {
      background-color: #f8f9fa;
      padding: 8px;
      font-weight: bold;
      border-bottom: 1px solid #dee2e6;
    }

    .crm-table-striped td {
      padding: 8px;
      border-bottom: 1px solid #dee2e6;
    }

    .crm-row-selected {
      background-color: #fff3cd;
    }

    .crm-marker {
      color: #d32f2f;
      font-weight: bold;
    }
  </style>
{/literal}
