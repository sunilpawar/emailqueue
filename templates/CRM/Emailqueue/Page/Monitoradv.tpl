<div class="crm-block crm-content-block crm-emailqueue-monitor-block">

  <div class="crm-submit-buttons">
    {if $isEnabled}
      <a href="#" id="process-queue-btn" class="button button-primary">{ts}Process Queue Now{/ts}</a>
      <a href="#" id="retry-failed-btn" class="button button-warning">{ts}Retry Failed Emails{/ts}</a>
      <a href="#" id="export-btn" class="button button-info">{ts}Export Results{/ts}</a>
    {/if}
    <a href="{crmURL p='civicrm/admin/emailqueue/settings'}" class="button button-secondary">{ts}Settings{/ts}</a>
  </div>

  <h3>{ts}Email Queue Monitor{/ts}</h3>

  {if not $isEnabled}
    <div class="messages warning no-popup">
      <div class="icon inform-icon"></div>
      {ts}Email Queue System is disabled. <a href="{crmURL p='civicrm/admin/emailqueue/settings'}">Enable it in settings</a> to start using the queue.{/ts}
    </div>
  {/if}

  {* Queue Statistics *}
  {if $queueStats}
    <div class="crm-section">
      <h4>{ts}Queue Statistics{/ts}</h4>
      <div class="stats-grid">
        <div class="stat-card stat-pending">
          <div class="stat-number">{$queueStats.pending}</div>
          <div class="stat-label">{ts}Pending{/ts}</div>
        </div>
        <div class="stat-card stat-processing">
          <div class="stat-number">{$queueStats.processing}</div>
          <div class="stat-label">{ts}Processing{/ts}</div>
        </div>
        <div class="stat-card stat-sent">
          <div class="stat-number">{$queueStats.sent}</div>
          <div class="stat-label">{ts}Sent{/ts}</div>
        </div>
        <div class="stat-card stat-failed">
          <div class="stat-number">{$queueStats.failed}</div>
          <div class="stat-label">{ts}Failed{/ts}</div>
        </div>
        <div class="stat-card stat-cancelled">
          <div class="stat-number">{$queueStats.cancelled}</div>
          <div class="stat-label">{ts}Cancelled{/ts}</div>
        </div>
      </div>
    </div>
  {/if}

  {* Search Panel *}
  <div class="search-panel">
    <div class="search-toggle" onclick="toggleSearch()">
      <span class="search-icon">🔍</span>
      <span>{ts}Advanced Search & Filters{/ts}</span>
      <span class="search-arrow">▼</span>
    </div>

    <form id="search-form" class="search-form" method="get" action="{crmURL p='civicrm/emailqueue/monitoradv'}">
      <div class="form-group">
        <label class="form-label">{ts}To Email{/ts}</label>
        <input type="text" name="to_email" class="form-control" value="{if !empty($searchParams.to_email)}{$searchParams.to_email}{/if}" placeholder="{ts}Recipient email{/ts}">
      </div>

      <div class="form-group">
        <label class="form-label">{ts}From Email{/ts}</label>
        <select name="from_email" class="form-control">
          <option value="">{ts}Any sender{/ts}</option>
          {if !empty($filterOptions.from_emails)}
          {foreach from=$filterOptions.from_emails item=email}
            <option value="{$email}" {if !empty($searchParams.from_email) && $searchParams.from_email eq $email}selected{/if}>{$email}</option>
          {/foreach}
          {/if}
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">{ts}Subject{/ts}</label>
        <input type="text" name="subject" class="form-control" value="{if !empty($searchParams.subject)}{$searchParams.subject}{/if}" placeholder="{ts}Email subject{/ts}">
      </div>

      <div class="form-group">
        <label class="form-label">{ts}Status{/ts}</label>
        <select name="status" class="form-control" multiple>
          {foreach from=$filterOptions.statuses item=status}
            <option value="{$status}" {if $searchParams.status and in_array($status, $searchParams.status)}selected{/if}>
              {if $status eq 'pending'}{ts}Pending{/ts}
              {elseif $status eq 'processing'}{ts}Processing{/ts}
              {elseif $status eq 'sent'}{ts}Sent{/ts}
              {elseif $status eq 'failed'}{ts}Failed{/ts}
              {elseif $status eq 'cancelled'}{ts}Cancelled{/ts}
              {else}{$status}{/if}
            </option>
          {/foreach}
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">{ts}Priority{/ts}</label>
        <select name="priority" class="form-control">
          <option value="">{ts}Any priority{/ts}</option>
          {foreach from=$filterOptions.priorities item=priority}
            <option value="{$priority}" {if $searchParams.priority eq $priority}selected{/if}>
              {$priority} - {if $priority eq 1}{ts}Highest{/ts}
              {elseif $priority eq 2}{ts}High{/ts}
              {elseif $priority eq 3}{ts}Normal{/ts}
              {elseif $priority eq 4}{ts}Low{/ts}
              {elseif $priority eq 5}{ts}Lowest{/ts}
              {/if}
            </option>
          {/foreach}
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">{ts}Created From{/ts}</label>
        <input type="date" name="date_from" class="form-control" value="{$searchParams.date_from}">
      </div>

      <div class="form-group">
        <label class="form-label">{ts}Created To{/ts}</label>
        <input type="date" name="date_to" class="form-control" value="{$searchParams.date_to}">
      </div>

      <div class="form-group">
        <label class="form-label">{ts}Has Error{/ts}</label>
        <select name="has_error" class="form-control">
          <option value="">{ts}Any{/ts}</option>
          <option value="yes" {if $searchParams.has_error eq 'yes'}selected{/if}>{ts}With Errors{/ts}</option>
          <option value="no" {if $searchParams.has_error eq 'no'}selected{/if}>{ts}No Errors{/ts}</option>
        </select>
      </div>

      <div class="search-actions">
        <button type="submit" class="button button-primary">{ts}Search{/ts}</button>
        <a href="{crmURL p='civicrm/emailqueue/monitoradv'}" class="button button-secondary">{ts}Clear Filters{/ts}</a>
        <button type="button" id="export-filtered-btn" class="button button-info">{ts}Export Filtered{/ts}</button>
      </div>
    </form>

    {* Active Filters Display *}
    {if $searchParams}
      <div class="active-filters">
        {if $searchParams.to_email}
          <div class="filter-tag">
            {ts}To:{/ts} {$searchParams.to_email} <span class="remove" onclick="removeFilter('to_email')">×</span>
          </div>
        {/if}
        {if $searchParams.status}
          <div class="filter-tag">
            {ts}Status:{/ts} {$searchParams.status|@implode:', '} <span class="remove" onclick="removeFilter('status')">×</span>
          </div>
        {/if}
        {if $searchParams.date_from or $searchParams.date_to}
          <div class="filter-tag">
            {ts}Date:{/ts} {$searchParams.date_from} - {$searchParams.date_to} <span class="remove" onclick="removeFilter('date')">×</span>
          </div>
        {/if}
      </div>
    {/if}
  </div>

  {* Email Table *}
  {if $emails}
    <div class="table-container">
      <div class="table-header">
        <div class="table-title">
          {ts}Email Queue{/ts}
          {if $pagination}
            ({ts 1=$pagination.limit 2=$pagination.total_count}Showing %1 of %2 emails{/ts})
          {/if}
        </div>
        <div class="table-actions">
          <label>
            <input type="checkbox" id="select-all" class="checkbox"> {ts}Select All{/ts}
          </label>
        </div>
      </div>

      <div class="bulk-actions" id="bulk-actions">
        <span><strong>0</strong> {ts}emails selected{/ts}</span>
        <button class="button button-warning" onclick="bulkAction('cancel')">{ts}Cancel Selected{/ts}</button>
        <button class="button button-primary" onclick="bulkAction('retry')">{ts}Retry Selected{/ts}</button>
        <button class="button button-secondary" onclick="bulkAction('delete')">{ts}Delete Selected{/ts}</button>
      </div>

      <table class="display dataTable">
        <thead>
        <tr>
          <th width="40">
            <input type="checkbox" class="checkbox" id="master-checkbox">
          </th>
          <th width="60" class="sortable" data-sort="id">
            {ts}ID{/ts} <span class="sort-indicator"></span>
          </th>
          <th class="sortable" data-sort="to_email">{ts}To Email{/ts}</th>
          <th class="sortable" data-sort="subject">{ts}Subject{/ts}</th>
          <th class="sortable" data-sort="status">{ts}Status{/ts}</th>
          <th width="80" class="sortable" data-sort="priority">{ts}Priority{/ts}</th>
          <th class="sortable" data-sort="created_date">{ts}Created{/ts}</th>
          <th class="sortable" data-sort="sent_date">{ts}Sent{/ts}</th>
          <th width="80">{ts}Retries{/ts}</th>
          <th width="150">{ts}Actions{/ts}</th>
        </tr>
        </thead>
        <tbody>
        {foreach from=$emails item=email}
          <tr>
            <td>
              <input type="checkbox" class="checkbox email-checkbox" value="{$email.id}">
            </td>
            <td>{$email.id}</td>
            <td class="email-cell" title="{$email.to_email|escape}">{$email.to_email|truncate:30}</td>
            <td class="email-cell" title="{$email.subject|escape}">{$email.subject|truncate:40}</td>
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
            <td>
              <span class="priority-indicator priority-{$email.priority}"></span>{$email.priority}
            </td>
            <td>{$email.created_date|crmDate}</td>
            <td>
              {if $email.sent_date}{$email.sent_date|crmDate}{else}-{/if}
            </td>
            <td>{$email.retry_count}</td>
            <td>
              <a href="#" class="action-link" onclick="previewEmail({$email.id})">{ts}Preview{/ts}</a>
              {if $email.status == 'pending' or $email.status == 'failed'}
                <a href="#" class="action-link cancel-email-btn" data-email-id="{$email.id}">{ts}Cancel{/ts}</a>
              {/if}
              {if $email.status == 'failed'}
                <a href="#" class="action-link retry-email-btn" data-email-id="{$email.id}">{ts}Retry{/ts}</a>
              {/if}
            </td>
          </tr>
        {/foreach}
        </tbody>
      </table>

      {* Pagination *}
      {if $pagination}
        <div class="pagination">
          <div class="pagination-info">
            {ts 1=$pagination.current_page 2=$pagination.total_pages 3=$pagination.total_count}Page %1 of %2 (%3 total emails){/ts}
          </div>
          <div class="pagination-nav">
            {if $pagination.current_page > 1}
              <a href="{crmURL p='civicrm/emailqueue/monitoradv' q="page=`$pagination.current_page-1`"}" class="page-btn">{ts}Previous{/ts}</a>
            {/if}

            {* Page numbers logic here *}
            {for $i=1 to $pagination.total_pages}
              {if $i <= 3 or $i > $pagination.total_pages-3 or ($i >= $pagination.current_page-2 and $i <= $pagination.current_page+2)}
                <a href="{crmURL p='civicrm/emailqueue/monitoradv' q="page=$i"}"
                   class="page-btn {if $i == $pagination.current_page}active{/if}">{$i}</a>
              {elseif $i == 4 or $i == $pagination.total_pages-3}
                <span class="page-btn">...</span>
              {/if}
            {/for}

            {if $pagination.current_page < $pagination.total_pages}
              <a href="{crmURL p='civicrm/emailqueue/monitoradv' q="page=`$pagination.current_page+1`"}" class="page-btn">{ts}Next{/ts}</a>
            {/if}
          </div>
        </div>
      {/if}
    </div>
  {/if}

  <div id="action-result" style="margin-top: 20px;"></div>

</div>

{* Email Preview Modal *}
<div class="modal-overlay" id="preview-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">{ts}Email Preview{/ts} - <span id="preview-email-id"></span></div>
      <button class="modal-close" onclick="closePreview()">×</button>
    </div>
    <div class="modal-body">
      <div class="preview-tabs">
        <button class="preview-tab active" onclick="showTab('details')">{ts}Details{/ts}</button>
        <button class="preview-tab" onclick="showTab('html')">{ts}HTML View{/ts}</button>
        <button class="preview-tab" onclick="showTab('text')">{ts}Text View{/ts}</button>
        <button class="preview-tab" onclick="showTab('logs')">{ts}Logs{/ts}</button>
      </div>

      <div class="preview-content active" id="details-tab">
        <div id="email-details-content">
          <div class="loading-spinner">{ts}Loading email details...{/ts}</div>
        </div>
      </div>

      <div class="preview-content" id="html-tab">
        <div id="email-html-content" style="max-height: 500px; overflow-y: auto;">
          <div class="loading-spinner">{ts}Loading HTML content...{/ts}</div>
        </div>
      </div>

      <div class="preview-content" id="text-tab">
        <div id="email-text-content">
          <div class="loading-spinner">{ts}Loading text content...{/ts}</div>
        </div>
      </div>

      <div class="preview-content" id="logs-tab">
        <div id="email-logs-content">
          <div class="loading-spinner">{ts}Loading email logs...{/ts}</div>
        </div>
      </div>
    </div>
  </div>
</div>

{literal}
  <style>
    /* Enhanced styles for search and preview functionality */
    .email-preview-container {
      background: #fff;
      border: 1px solid #ddd;
      border-radius: 4px;
      padding: 20px;
      margin: 15px 0;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .email-preview-header {
      border-bottom: 2px solid #f5f5f5;
      padding-bottom: 15px;
      margin-bottom: 20px;
    }

    .email-preview-title {
      font-size: 18px;
      font-weight: 600;
      color: #333;
      margin: 0 0 10px 0;
    }

    .email-preview-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 20px;
      color: #666;
      font-size: 14px;
    }

    /* Detail Value Styles */
    .detail-value {
      display: inline-block;
      padding: 4px 8px;
      background-color: #f8f9fa;
      border: 1px solid #e9ecef;
      border-radius: 3px;
      font-family: 'Courier New', monospace;
      font-size: 13px;
      color: #495057;
      word-break: break-all;
    }

    .detail-value.email {
      color: #0066cc;
      background-color: #e7f3ff;
      border-color: #b3d9ff;
    }

    .detail-value.status {
      font-weight: bold;
      text-transform: uppercase;
      font-size: 11px;
      padding: 3px 6px;
    }

    .detail-value.status.pending {
      background-color: #fff3cd;
      color: #856404;
      border-color: #ffeaa7;
    }

    .detail-value.status.sent {
      background-color: #d4edda;
      color: #155724;
      border-color: #c3e6cb;
    }

    .detail-value.status.failed {
      background-color: #f8d7da;
      color: #721c24;
      border-color: #f5c6cb;
    }

    .detail-value.status.cancelled {
      background-color: #e2e3e5;
      color: #383d41;
      border-color: #d6d8db;
    }

    .detail-value.date {
      color: #28a745;
      font-family: inherit;
    }

    .detail-value.number {
      text-align: right;
      font-weight: 600;
      color: #007bff;
    }

    /* Email Details Grid */
    .email-details {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 15px;
      margin-bottom: 20px;
    }

    .detail-item {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      padding: 8px 0;
      border-bottom: 1px solid #f0f0f0;
    }

    .detail-item:last-child {
      border-bottom: none;
    }

    .detail-label {
      font-weight: 600;
      color: #333;
      min-width: 100px;
      flex-shrink: 0;
      font-size: 14px;
    }

    .detail-content {
      flex: 1;
      color: #555;
    }

    /* Email Content Preview */
    .email-content-preview {
      border: 1px solid #ddd;
      border-radius: 4px;
      overflow: hidden;
      margin-top: 20px;
    }

    .email-content-header {
      background: #f8f9fa;
      padding: 10px 15px;
      border-bottom: 1px solid #ddd;
      font-weight: 600;
      color: #333;
    }

    .email-content-body {
      padding: 15px;
      max-height: 400px;
      overflow-y: auto;
      background: #fff;
    }

    .email-content-iframe {
      width: 100%;
      min-height: 300px;
      border: none;
      background: #fff;
    }

    /* HTML/Text Toggle */
    .content-toggle {
      display: flex;
      background: #f8f9fa;
      border-radius: 4px;
      padding: 2px;
      margin-bottom: 15px;
    }

    .content-toggle button {
      flex: 1;
      padding: 8px 12px;
      border: none;
      background: transparent;
      color: #666;
      cursor: pointer;
      border-radius: 2px;
      transition: all 0.2s;
    }

    .content-toggle button.active {
      background: #007bff;
      color: white;
    }

    .content-toggle button:hover:not(.active) {
      background: #e9ecef;
    }

    /* Email Attachments */
    .email-attachments {
      margin-top: 15px;
      padding: 15px;
      background: #f8f9fa;
      border-radius: 4px;
    }

    .attachment-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 8px 0;
      border-bottom: 1px solid #e9ecef;
    }

    .attachment-item:last-child {
      border-bottom: none;
    }

    .attachment-icon {
      width: 20px;
      height: 20px;
      color: #666;
    }

    .attachment-name {
      flex: 1;
      color: #333;
      text-decoration: none;
    }

    .attachment-name:hover {
      color: #007bff;
      text-decoration: underline;
    }

    .attachment-size {
      color: #666;
      font-size: 12px;
    }

    /* Email Recipients */
    .email-recipients {
      margin: 15px 0;
    }

    .recipient-group {
      margin-bottom: 10px;
    }

    .recipient-label {
      font-weight: 600;
      color: #333;
      margin-bottom: 5px;
      display: block;
    }

    .recipient-list {
      display: flex;
      flex-wrap: wrap;
      gap: 5px;
    }

    .recipient-email {
      display: inline-block;
      padding: 2px 6px;
      background: #e7f3ff;
      color: #0066cc;
      border-radius: 3px;
      font-size: 12px;
      text-decoration: none;
    }

    .recipient-email:hover {
      background: #cce7ff;
    }

    /* Action Buttons */
    .email-preview-actions {
      display: flex;
      gap: 10px;
      padding-top: 15px;
      border-top: 1px solid #e9ecef;
      margin-top: 20px;
    }

    .preview-action-btn {
      padding: 8px 16px;
      border: 1px solid #ddd;
      background: #fff;
      color: #333;
      border-radius: 4px;
      cursor: pointer;
      font-size: 14px;
      transition: all 0.2s;
    }

    .preview-action-btn:hover {
      background: #f8f9fa;
      border-color: #adb5bd;
    }

    .preview-action-btn.primary {
      background: #007bff;
      color: white;
      border-color: #007bff;
    }

    .preview-action-btn.primary:hover {
      background: #0056b3;
      border-color: #0056b3;
    }

    .preview-action-btn.danger {
      background: #dc3545;
      color: white;
      border-color: #dc3545;
    }

    .preview-action-btn.danger:hover {
      background: #c82333;
      border-color: #c82333;
    }

    /* Email Status Indicators */
    .email-status-indicator {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 4px 8px;
      border-radius: 12px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
    }

    .email-status-indicator.pending {
      background: #fff3cd;
      color: #856404;
    }

    .email-status-indicator.sent {
      background: #d4edda;
      color: #155724;
    }

    .email-status-indicator.failed {
      background: #f8d7da;
      color: #721c24;
    }

    .email-status-indicator.cancelled {
      background: #e2e3e5;
      color: #383d41;
    }

    .status-dot {
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: currentColor;
    }

    /* Error Messages */
    .email-error-details {
      background: #f8d7da;
      border: 1px solid #f5c6cb;
      border-radius: 4px;
      padding: 12px;
      margin-top: 15px;
      color: #721c24;
    }

    .error-title {
      font-weight: 600;
      margin-bottom: 5px;
    }

    .error-message {
      font-family: 'Courier New', monospace;
      font-size: 13px;
      white-space: pre-wrap;
    }

    /* Email Statistics */
    .email-stats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 15px;
      margin: 15px 0;
      padding: 15px;
      background: #f8f9fa;
      border-radius: 4px;
    }

    .stat-item {
      text-align: center;
    }

    .stat-value {
      display: block;
      font-size: 24px;
      font-weight: 700;
      color: #007bff;
      margin-bottom: 5px;
    }

    .stat-label {
      font-size: 12px;
      color: #666;
      text-transform: uppercase;
      font-weight: 600;
    }

    /* Loading States */
    .email-preview-loading {
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px;
      color: #666;
    }

    .loading-spinner {
      width: 20px;
      height: 20px;
      border: 2px solid #f3f3f3;
      border-top: 2px solid #007bff;
      border-radius: 50%;
      animation: spin 1s linear infinite;
      margin-right: 10px;
    }

    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }

    /* Responsive Design */
    @media (max-width: 768px) {
      .email-details {
        grid-template-columns: 1fr;
      }

      .email-preview-meta {
        flex-direction: column;
        gap: 10px;
      }

      .email-preview-actions {
        flex-direction: column;
      }

      .preview-action-btn {
        width: 100%;
      }

      .detail-item {
        flex-direction: column;
        gap: 5px;
      }

      .detail-label {
        min-width: auto;
      }
    }

    /* Print Styles */
    @media print {
      .email-preview-actions,
      .content-toggle {
        display: none;
      }

      .email-preview-container {
        box-shadow: none;
        border: 1px solid #ccc;
      }

      .email-content-body {
        max-height: none;
        overflow: visible;
      }
    }

    /* Dark Mode Support */
    @media (prefers-color-scheme: dark) {
      .email-preview-container {
        background: #2d3748;
        border-color: #4a5568;
        color: #e2e8f0;
      }

      .detail-value {
        background-color: #4a5568;
        border-color: #718096;
        color: #e2e8f0;
      }

      .email-content-preview {
        border-color: #4a5568;
      }

      .email-content-header {
        background: #4a5568;
        border-color: #718096;
        color: #e2e8f0;
      }
    }

    .search-panel {
      background: #f8f9fa;
      border: 1px solid #e9ecef;
      border-radius: 6px;
      padding: 20px;
      margin-bottom: 30px;
    }

    .search-toggle {
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 10px;
      font-weight: 600;
      color: #2c5aa0;
      margin-bottom: 15px;
    }

    .search-form {
      display: none;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      margin-bottom: 20px;
    }

    .search-form.open {
      display: grid;
    }

    .form-group {
      display: flex;
      flex-direction: column;
      gap: 5px;
    }

    .form-label {
      font-weight: 600;
      color: #495057;
      font-size: 13px;
    }

    .search-actions {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }

    .active-filters {
      margin-top: 15px;
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }

    .filter-tag {
      background: #e7f1ff;
      color: #2c5aa0;
      padding: 4px 8px;
      border-radius: 12px;
      font-size: 12px;
      display: flex;
      align-items: center;
      gap: 5px;
    }

    .filter-tag .remove {
      cursor: pointer;
      color: #dc3545;
      font-weight: bold;
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }

    .stat-card {
      background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%);
      border: 1px solid #e9ecef;
      border-radius: 8px;
      padding: 20px;
      text-align: center;
      box-shadow: 0 2px 4px rgba(0,0,0,0.05);
      transition: transform 0.2s, box-shadow 0.2s;
    }

    .stat-number {
      font-size: 28px;
      font-weight: bold;
      margin-bottom: 5px;
    }

    .stat-label {
      color: #6c757d;
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .stat-pending .stat-number { color: #ffc107; }
    .stat-processing .stat-number { color: #17a2b8; }
    .stat-sent .stat-number { color: #28a745; }
    .stat-failed .stat-number { color: #dc3545; }
    .stat-cancelled .stat-number { color: #6c757d; }

    .table-container {
      background: white;
      border-radius: 6px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.05);
      overflow: hidden;
    }

    .table-header {
      background: #f8f9fa;
      padding: 15px 20px;
      border-bottom: 1px solid #e9ecef;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .bulk-actions {
      display: none;
      background: #fff3cd;
      border: 1px solid #ffeaa7;
      padding: 10px 15px;
      align-items: center;
      gap: 10px;
    }

    .bulk-actions.show {
      display: flex;
    }

    .priority-indicator {
      display: inline-block;
      width: 8px;
      height: 8px;
      border-radius: 50%;
      margin-right: 5px;
    }

    .priority-1 { background: #dc3545; }
    .priority-2 { background: #fd7e14; }
    .priority-3 { background: #ffc107; }
    .priority-4 { background: #20c997; }
    .priority-5 { background: #6c757d; }

    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      display: none;
      justify-content: center;
      align-items: center;
      z-index: 1000;
    }

    .modal-overlay.show {
      display: flex;
    }

    .modal {
      background: white;
      border-radius: 8px;
      max-width: 90vw;
      max-height: 90vh;
      width: 800px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
      overflow: hidden;
      display: flex;
      flex-direction: column;
    }

    .modal-header {
      background: #2c5aa0;
      color: white;
      padding: 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .modal-close {
      background: none;
      border: none;
      color: white;
      font-size: 24px;
      cursor: pointer;
      padding: 0;
      width: 30px;
      height: 30px;
    }

    .modal-body {
      padding: 20px;
      overflow-y: auto;
      flex: 1;
    }

    .preview-tabs {
      display: flex;
      border-bottom: 1px solid #e9ecef;
      margin-bottom: 20px;
    }

    .preview-tab {
      padding: 10px 20px;
      border: none;
      background: none;
      cursor: pointer;
      font-size: 14px;
      color: #6c757d;
      border-bottom: 2px solid transparent;
    }

    .preview-tab.active {
      color: #2c5aa0;
      border-bottom-color: #2c5aa0;
    }

    .preview-content {
      display: none;
    }

    .preview-content.active {
      display: block;
    }

    .pagination {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 20px;
      border-top: 1px solid #e9ecef;
    }

    .pagination-nav {
      display: flex;
      gap: 5px;
    }

    .page-btn {
      padding: 8px 12px;
      border: 1px solid #e9ecef;
      background: white;
      cursor: pointer;
      border-radius: 4px;
      text-decoration: none;
      color: #333;
    }

    .page-btn.active {
      background: #2c5aa0;
      color: white;
      border-color: #2c5aa0;
    }
  </style>

  <script type="text/javascript">
    CRM.$(function($) {

      // Toggle search form
      window.toggleSearch = function() {
        var form = $('#search-form');
        var arrow = $('.search-arrow');

        if (form.hasClass('open')) {
          form.removeClass('open');
          arrow.text('▼');
        } else {
          form.addClass('open');
          arrow.text('▲');
        }
      };

      // Preview email functionality
      window.previewEmail = function(emailId) {
        $('#preview-modal').addClass('show');
        $('#preview-email-id').text('ID: ' + emailId);

        // Load email details via AJAX
        CRM.api3('Emailqueue', 'preview', {id: emailId})
          .done(function(result) {
            if (result.is_error) {
              showError('Failed to load email preview: ' + result.error_message);
              return;
            }

            var email = result.values;
            populateEmailDetails(email);
            populateEmailContent(email);
            populateEmailLogs(email);
          })
          .fail(function() {
            showError('Failed to load email preview');
          });
      };

      // Close preview modal
      window.closePreview = function() {
        $('#preview-modal').removeClass('show');
      };

      // Show preview tab
      window.showTab = function(tabName) {
        $('.preview-tab').removeClass('active');
        $('.preview-content').removeClass('active');

        event.target.classList.add('active');
        $('#' + tabName + '-tab').addClass('active');
      };
      function escapeHtml(str) {
        return str
          .replace(/&/g, "&amp;")
          .replace(/</g, "&lt;")
          .replace(/>/g, "&gt;")
          .replace(/"/g, "&quot;")
          .replace(/'/g, "&#039;");
      }
      function capitalizeFirstLetter(str) {
        return str[0].toUpperCase() + str.slice(1);
      }

      // Populate email details
      function populateEmailDetails(email) {
        var html = '<div class="email-preview-container"><div class="email-details">';
        html += '<div class="detail-item"><div class="detail-label">To:</div><div class="detail-value">' + email.to_email + '</div></div>';
        html += '<div class="detail-item"><div class="detail-label">From:</div><div class="detail-value">' + (escapeHtml(email.from_email) || '-') + '</div></div>';
        html += '<div class="detail-item"><div class="detail-label">Subject:</div><div class="detail-value">' + (email.subject || '-') + '</div></div>';
        html += '<div class="detail-item"><div class="detail-label">Status:</div><div class="detail-value"><span class="badge badge-' + getStatusClass(email.status) + '" style="">' + capitalizeFirstLetter(email.status) + '</span></div></div>';
        html += '<div class="detail-item"><div class="detail-label">Priority:</div><div class="detail-value">' + email.priority + '</div></div>';
        html += '<div class="detail-item"><div class="detail-label">Created:</div><div class="detail-value">' + email.created_date + '</div></div>';
        html += '<div class="detail-item"><div class="detail-label">Sent:</div><div class="detail-value">' + (email.sent_date || '-') + '</div></div>';
        html += '<div class="detail-item"><div class="detail-label">Retry Count:</div><div class="detail-value">' + email.retry_count + ' / ' + email.max_retries + '</div></div>';

        if (email.cc) {
          html += '<div class="detail-item"><div class="detail-label">CC:</div><div class="detail-value">' + email.cc + '</div></div>';
        }
        if (email.bcc) {
          html += '<div class="detail-item"><div class="detail-label">BCC:</div><div class="detail-value">' + email.bcc + '</div></div>';
        }
        if (email.reply_to) {
          html += '<div class="detail-item"><div class="detail-label">Reply-To:</div><div class="detail-value">' + escapeHtml(email.reply_to) + '</div></div>';
        }
        if (email.error_message) {
          html += '<div class="detail-item"><div class="detail-label">Error:</div><div class="detail-value" style="color: #dc3545;">' + email.error_message + '</div></div>';
        }

        html += '</div></div>';
        $('#email-details-content').html(html);
      }

      // Populate email content
      function populateEmailContent(email) {
        if (email.body_html) {
          $('#email-html-content').html('<iframe style="text-align:center;width: 100%;height: 100%; " id="myframe"></iframe');
          var myFrame = $("#myframe").contents().find('body');
          myFrame.html('<div class="email-body">' + email.body_html + '</div>');
          // <div class="email-body">' + email.body_html + '</div>
        } else {
          $('#email-html-content').html('<div class="email-body"><em>No HTML content</em></div>');
        }

        if (email.body_text) {
          $('#email-text-content').html('<div class="email-body"><pre>' + email.body_text + '</pre></div>');
        } else {
          $('#email-text-content').html('<div class="email-body"><em>No text content</em></div>');
        }
      }

      // Populate email logs
      function populateEmailLogs(email) {
        var html = '<div class="email-logs">';

        if (email.logs && email.logs.length > 0) {
          email.logs.forEach(function(log) {
            var logClass = getLogClass(log.action);
            html += '<div class="detail-item"><div class="log-entry ' + logClass + '">';
            html += '<div class="log-header">' + log.action.toUpperCase() + ' - ' + log.created_date + '</div>';
            html += '<div class="log-message">' + log.message + '</div>';
            html += '</div></div>';
          });
        } else {
          html += '<div class="log-entry info"><div class="log-message">No logs available</div></div>';
        }

        html += '</div>';
        $('#email-logs-content').html(html);
      }

      // Helper functions
      function getStatusClass(status) {
        switch(status) {
          case 'pending': return 'warning';
          case 'processing': return 'info';
          case 'sent': return 'success';
          case 'failed': return 'danger';
          case 'cancelled': return 'secondary';
          default: return 'secondary';
        }
      }

      function getLogClass(action) {
        if (action.includes('sent') || action.includes('success')) return 'success';
        if (action.includes('failed') || action.includes('error')) return 'error';
        return 'info';
      }

      function showError(message) {
        $('#action-result').html('<div class="crm-error">' + message + '</div>');
      }

      // Bulk selection functionality
      var selectedEmails = [];

      $('#select-all').change(function() {
        $('.email-checkbox').prop('checked', this.checked);
        updateBulkActions();
      });

      $('.email-checkbox').change(function() {
        updateBulkActions();
      });

      function updateBulkActions() {
        selectedEmails = $('.email-checkbox:checked').map(function() {
          return $(this).val();
        }).get();

        if (selectedEmails.length > 0) {
          $('#bulk-actions').addClass('show');
          $('#bulk-actions span').html('<strong>' + selectedEmails.length + '</strong> emails selected');
        } else {
          $('#bulk-actions').removeClass('show');
        }
      }

      // Bulk actions
      window.bulkAction = function(action) {
        if (selectedEmails.length === 0) {
          showError('No emails selected');
          return;
        }

        var confirmMessage = 'Are you sure you want to ' + action + ' ' + selectedEmails.length + ' selected emails?';
        if (!confirm(confirmMessage)) {
          return;
        }

        CRM.api3('Emailqueue', 'bulkaction', {
          action: action,
          email_ids: selectedEmails.join(',')
        })
          .done(function(result) {
            if (result.is_error) {
              showError('Bulk action failed: ' + result.error_message);
            } else {
              location.reload(); // Refresh page to show updated data
            }
          })
          .fail(function() {
            showError('Bulk action failed');
          });
      };

      // Export functionality
      $('#export-btn, #export-filtered-btn').click(function(e) {
        e.preventDefault();

        // Get current search parameters
        var searchParams = new URLSearchParams(window.location.search);
        var exportUrl = CRM.url('civicrm/ajax/emailqueue/action', {toDoAction: 'export'});

        // Add search parameters to export URL
        searchParams.forEach(function(value, key) {
          exportUrl += '&' + key + '=' + encodeURIComponent(value);
        });

        // Trigger download
        window.location.href = exportUrl;
      });

      // Close modal when clicking outside
      $('#preview-modal').click(function(e) {
        if (e.target === this) {
          closePreview();
        }
      });

      // Other existing functionality...
      $('#process-queue-btn').click(function(e) {
        e.preventDefault();
        performAction('process_queue', 'Processing queue...', $(this));
      });

      $('#retry-failed-btn').click(function(e) {
        e.preventDefault();
        if (confirm('Are you sure you want to retry all failed emails?')) {
          performAction('retry_failed', 'Retrying failed emails...', $(this));
        }
      });

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

        var params = {toDoAction: action};
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
    });
  </script>
{/literal}
