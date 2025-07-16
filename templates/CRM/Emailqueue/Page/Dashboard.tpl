<div class="crm-block crm-content-block crm-emailqueue-dashboard-block">

  {* Dashboard Header *}
  <div class="dashboard-header">
    <div class="header-content">
      <h1 class="dashboard-title">
        <span class="icon">📊</span>
        {ts}Email Queue Dashboard{/ts}
      </h1>
      <div class="header-meta">
        <span class="timestamp">
          {ts}Last updated:{/ts} {$dashboardData.timestamp|crmDate}
        </span>
        <div class="auto-refresh">
          <span class="refresh-indicator active"></span>
          {ts}Auto-refresh: 30s{/ts}
        </div>
      </div>
    </div>

    <div class="dashboard-actions">
      <button class="btn btn-primary" id="refresh-dashboard">
        <i class="fas fa-sync"></i> {ts}Refresh{/ts}
      </button>
      <a href="{crmURL p='civicrm/admin/emailqueue/monitor'}" class="btn btn-secondary">
        <i class="fas fa-list"></i> {ts}Queue Monitor{/ts}
      </a>
      <a href="{crmURL p='civicrm/admin/emailqueue/settings'}" class="btn btn-outline-secondary">
        <i class="fas fa-cog"></i> {ts}Settings{/ts}
      </a>
    </div>
  </div>

  {* System Alerts *}
  {if $alerts}
    <div class="alerts-section">
      <h3 class="section-title">
        <span class="icon">🚨</span>
        {ts}System Alerts{/ts}
        <span class="badge badge-danger">{$alerts|@count}</span>
      </h3>
      <div class="alerts-grid">
        {foreach from=$alerts item=alert}
          <div class="alert alert-{$alert.type}">
            <div class="alert-header">
              <h4 class="alert-title">{$alert.title}</h4>
              <span class="alert-type badge badge-{$alert.type}">{$alert.type}</span>
            </div>
            <p class="alert-message">{$alert.message}</p>
            <div class="alert-action">
              <small class="text-muted">{ts}Recommended action:{/ts} {$alert.action}</small>
            </div>
          </div>
        {/foreach}
      </div>
    </div>
  {/if}

  {* Key Metrics Cards *}
  <div class="metrics-section">
    <div class="metrics-grid">

      {* Queue Health Card *}
      <div class="metric-card health-card">
        <div class="card-header">
          <h3 class="card-title">{ts}Queue Health{/ts}</h3>
          <span class="health-indicator health-{$dashboardData.queue_health.grade}">
            {$dashboardData.queue_health.grade}
          </span>
        </div>
        <div class="card-body">
          <div class="health-score">
            <div class="score-circle" data-score="{$dashboardData.queue_health.score}">
              <span class="score-value">{$dashboardData.queue_health.score}</span>
            </div>
          </div>
          {if $dashboardData.queue_health.factors}
            <div class="health-factors">
              <h5>{ts}Health Factors:{/ts}</h5>
              <ul>
                {foreach from=$dashboardData.queue_health.factors item=factor}
                  <li>{$factor}</li>
                {/foreach}
              </ul>
            </div>
          {/if}
        </div>
      </div>

      {* System Status Card *}
      <div class="metric-card status-card">
        <div class="card-header">
          <h3 class="card-title">{ts}System Status{/ts}</h3>
          <span class="status-indicator status-{$dashboardData.system_status}">
            {$dashboardData.system_status}
          </span>
        </div>
        <div class="card-body">
          <div class="status-details">
            {if $dashboardData.system_health.checks}
              {foreach from=$dashboardData.system_health.checks item=check}
                <div class="status-item">
                  <span class="status-icon status-{$check.status}"></span>
                  <span class="status-name">{$check.name}</span>
                  <span class="status-message">{$check.message}</span>
                </div>
              {/foreach}
            {/if}
          </div>
        </div>
      </div>

      {* Queue Statistics Card *}
      <div class="metric-card stats-card">
        <div class="card-header">
          <h3 class="card-title">{ts}Queue Statistics{/ts}</h3>
        </div>
        <div class="card-body">
          <div class="stats-grid">
            <div class="stat-item stat-pending">
              <div class="stat-value">{$dashboardData.queue_stats.pending|number_format}</div>
              <div class="stat-label">{ts}Pending{/ts}</div>
            </div>
            <div class="stat-item stat-processing">
              <div class="stat-value">{$dashboardData.queue_stats.processing|number_format}</div>
              <div class="stat-label">{ts}Processing{/ts}</div>
            </div>
            <div class="stat-item stat-sent">
              <div class="stat-value">{$dashboardData.queue_stats.sent|number_format}</div>
              <div class="stat-label">{ts}Sent{/ts}</div>
            </div>
            <div class="stat-item stat-failed">
              <div class="stat-value">{$dashboardData.queue_stats.failed|number_format}</div>
              <div class="stat-label">{ts}Failed{/ts}</div>
            </div>
          </div>
        </div>
      </div>

      {* Performance Metrics Card *}
      <div class="metric-card performance-card">
        <div class="card-header">
          <h3 class="card-title">{ts}Performance{/ts}</h3>
        </div>
        <div class="card-body">
          <div class="performance-metrics">
            {if !empty($dashboardData.processing_metrics.emails_per_hour)}
              <div class="metric-row">
                <span class="metric-label">{ts}Emails/Hour:{/ts}</span>
                <span class="metric-value">{$dashboardData.processing_metrics.emails_per_hour}</span>
              </div>
            {/if}
            {if !empty($dashboardData.processing_metrics.avg_processing_time_formatted)}
              <div class="metric-row">
                <span class="metric-label">{ts}Avg. Time:{/ts}</span>
                <span class="metric-value">{$dashboardData.processing_metrics.avg_processing_time_formatted}</span>
              </div>
            {/if}
            {if $dashboardData.processing_metrics.success_rate}
              <div class="metric-row">
                <span class="metric-label">{ts}Success Rate:{/ts}</span>
                <span class="metric-value">{$dashboardData.processing_metrics.success_rate}%</span>
              </div>
            {/if}
            {if $dashboardData.capacity}
              <div class="metric-row">
                <span class="metric-label">{ts}Capacity:{/ts}</span>
                <span class="metric-value">{$dashboardData.capacity.capacity_utilization}%</span>
              </div>
            {/if}
          </div>
        </div>
      </div>

    </div>
  </div>

  {* Charts Section *}
  <div class="charts-section">
    <div class="charts-grid">

      {* Email Volume Chart *}
      <div class="chart-card">
        <div class="chart-header">
          <h3 class="chart-title">{ts}Email Volume (24 Hours){/ts}</h3>
          <div class="chart-controls">
            <select class="chart-timeframe">
              <option value="24h">{ts}Last 24 Hours{/ts}</option>
              <option value="7d">{ts}Last 7 Days{/ts}</option>
              <option value="30d">{ts}Last 30 Days{/ts}</option>
            </select>
          </div>
        </div>
        <div class="chart-body">
          <canvas id="volumeChart" width="400" height="200"></canvas>
        </div>
      </div>

      {* Status Distribution Chart *}
      <div class="chart-card">
        <div class="chart-header">
          <h3 class="chart-title">{ts}Status Distribution{/ts}</h3>
        </div>
        <div class="chart-body">
          <canvas id="statusChart" width="300" height="300"></canvas>
        </div>
      </div>

      {* Performance Trend Chart *}
      <div class="chart-card">
        <div class="chart-header">
          <h3 class="chart-title">{ts}Performance Trend{/ts}</h3>
        </div>
        <div class="chart-body">
          <canvas id="performanceChart" width="400" height="200"></canvas>
        </div>
      </div>

      {* Error Rate Chart *}
      <div class="chart-card">
        <div class="chart-header">
          <h3 class="chart-title">{ts}Error Rate{/ts}</h3>
        </div>
        <div class="chart-body">
          <canvas id="errorChart" width="400" height="200"></canvas>
        </div>
      </div>

    </div>
  </div>

  {* Recommendations Section *}
  {if $recommendations}
    <div class="recommendations-section">
      <h3 class="section-title">
        <span class="icon">💡</span>
        {ts}Optimization Recommendations{/ts}
        <span class="badge badge-info">{$recommendations|@count}</span>
      </h3>
      <div class="recommendations-grid">
        {foreach from=$recommendations item=rec}
          <div class="recommendation-card priority-{$rec.priority}">
            <div class="recommendation-header">
              <h4 class="recommendation-title">{$rec.issue}</h4>
              <span class="priority-badge priority-{$rec.priority}">{$rec.priority}</span>
            </div>
            <div class="recommendation-body">
              <p class="recommendation-description">{$rec.description}</p>
              <p class="recommendation-suggestion">
                <strong>{ts}Suggestion:{/ts}</strong> {$rec.suggestion}
              </p>
            </div>
            {if $rec.actions}
              <div class="recommendation-actions">
                {foreach from=$rec.actions item=action}
                  <a href="{$action.url}" class="btn btn-{$action.type} {$action.class|default:''}">{$action.label}</a>
                {/foreach}
              </div>
            {/if}
          </div>
        {/foreach}
      </div>
    </div>
  {/if}

  {* Recent Activity Timeline *}
  {if $dashboardData.recent_activity}
    <div class="activity-section">
      <h3 class="section-title">
        <span class="icon">📝</span>
        {ts}Recent Activity{/ts}
      </h3>
      <div class="activity-timeline">
        {foreach from=$dashboardData.recent_activity item=activity}
          <div class="timeline-item">
            <div class="timeline-marker action-{$activity.action|lower}"></div>
            <div class="timeline-content">
              <div class="timeline-header">
                <span class="timeline-action">{$activity.action}</span>
                <span class="timeline-time">{$activity.created_date|crmDate}</span>
              </div>
              <div class="timeline-message">{$activity.message}</div>
              {if $activity.to_email}
                <div class="timeline-email">
                  <small class="text-muted">
                    {ts}Email:{/ts} {$activity.to_email}
                    {if $activity.subject} - {$activity.subject|truncate:50}{/if}
                  </small>
                </div>
              {/if}
            </div>
          </div>
        {/foreach}
      </div>
    </div>
  {/if}

</div>

{* Dashboard Styles *}
{literal}
  <style>
    .crm-emailqueue-dashboard-block {
      padding: 20px;
      background: #f8f9fa;
      min-height: 100vh;
    }

    .dashboard-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 30px;
      background: white;
      padding: 20px;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .dashboard-title {
      font-size: 24px;
      font-weight: 600;
      color: #2c5aa0;
      margin: 0;
    }

    .dashboard-title .icon {
      margin-right: 10px;
    }

    .header-meta {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      gap: 5px;
    }

    .timestamp {
      color: #6c757d;
      font-size: 14px;
    }

    .auto-refresh {
      display: flex;
      align-items: center;
      gap: 5px;
      color: #28a745;
      font-size: 12px;
    }

    .refresh-indicator {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: #28a745;
    }

    .refresh-indicator.active {
      animation: pulse 2s infinite;
    }

    @keyframes pulse {
      0% { opacity: 1; }
      50% { opacity: 0.5; }
      100% { opacity: 1; }
    }

    .dashboard-actions {
      display: flex;
      gap: 10px;
    }

    .btn {
      padding: 8px 16px;
      border-radius: 4px;
      text-decoration: none;
      font-size: 14px;
      border: 1px solid transparent;
      cursor: pointer;
      transition: all 0.2s;
    }

    .btn-primary {
      background: #2c5aa0;
      color: white;
      border-color: #2c5aa0;
    }

    .btn-secondary {
      background: #6c757d;
      color: white;
      border-color: #6c757d;
    }

    .btn-outline-secondary {
      background: transparent;
      color: #6c757d;
      border-color: #6c757d;
    }

    .section-title {
      font-size: 18px;
      font-weight: 600;
      color: #2c5aa0;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .alerts-section {
      margin-bottom: 30px;
    }

    .alerts-grid {
      display: grid;
      gap: 15px;
    }

    .alert {
      padding: 15px;
      border-radius: 6px;
      border-left: 4px solid;
    }

    .alert-warning {
      background: #fff3cd;
      border-left-color: #ffc107;
      color: #856404;
    }

    .alert-error {
      background: #f8d7da;
      border-left-color: #dc3545;
      color: #721c24;
    }

    .alert-info {
      background: #d1ecf1;
      border-left-color: #17a2b8;
      color: #0c5460;
    }

    .alert-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 10px;
    }

    .alert-title {
      margin: 0;
      font-size: 16px;
      font-weight: 600;
    }

    .metrics-section {
      margin-bottom: 30px;
    }

    .metrics-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 20px;
    }

    .metric-card {
      background: white;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      overflow: hidden;
    }

    .card-header {
      padding: 15px 20px;
      background: #f8f9fa;
      border-bottom: 1px solid #e9ecef;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .card-title {
      margin: 0;
      font-size: 16px;
      font-weight: 600;
      color: #495057;
    }

    .card-body {
      padding: 20px;
    }

    .health-card .score-circle {
      position: relative;
      width: 100px;
      height: 100px;
      border-radius: 50%;
      background: conic-gradient(#28a745 0deg, #28a745 var(--score-angle), #e9ecef var(--score-angle));
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 15px;
    }

    .score-circle::before {
      content: '';
      position: absolute;
      width: 70px;
      height: 70px;
      background: white;
      border-radius: 50%;
    }

    .score-value {
      position: relative;
      z-index: 1;
      font-size: 24px;
      font-weight: bold;
      color: #2c5aa0;
    }

    .health-indicator {
      padding: 4px 8px;
      border-radius: 12px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
    }

    .health-excellent {
      background: #d4edda;
      color: #155724;
    }

    .health-good {
      background: #d1ecf1;
      color: #0c5460;
    }

    .health-fair {
      background: #fff3cd;
      color: #856404;
    }

    .health-poor {
      background: #f8d7da;
      color: #721c24;
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 15px;
    }

    .stat-item {
      text-align: center;
      padding: 15px;
      border-radius: 6px;
      background: #f8f9fa;
    }

    .stat-value {
      font-size: 24px;
      font-weight: bold;
      margin-bottom: 5px;
    }

    .stat-label {
      font-size: 12px;
      color: #6c757d;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .stat-pending .stat-value { color: #ffc107; }
    .stat-processing .stat-value { color: #17a2b8; }
    .stat-sent .stat-value { color: #28a745; }
    .stat-failed .stat-value { color: #dc3545; }

    .charts-section {
      margin-bottom: 30px;
    }

    .charts-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
      gap: 20px;
    }

    .chart-card {
      background: white;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      overflow: hidden;
    }

    .chart-header {
      padding: 15px 20px;
      background: #f8f9fa;
      border-bottom: 1px solid #e9ecef;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .chart-title {
      margin: 0;
      font-size: 16px;
      font-weight: 600;
      color: #495057;
    }

    .chart-body {
      padding: 20px;
    }

    .recommendations-grid {
      display: grid;
      gap: 15px;
    }

    .recommendation-card {
      background: white;
      border-radius: 6px;
      padding: 20px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      border-left: 4px solid #17a2b8;
    }

    .recommendation-card.priority-high {
      border-left-color: #dc3545;
    }

    .recommendation-card.priority-medium {
      border-left-color: #ffc107;
    }

    .recommendation-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 10px;
    }

    .recommendation-title {
      margin: 0;
      font-size: 16px;
      font-weight: 600;
      color: #495057;
    }

    .priority-badge {
      padding: 2px 6px;
      border-radius: 3px;
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
    }

    .priority-high {
      background: #f8d7da;
      color: #721c24;
    }

    .priority-medium {
      background: #fff3cd;
      color: #856404;
    }

    .priority-low {
      background: #d1ecf1;
      color: #0c5460;
    }

    .recommendation-actions {
      margin-top: 15px;
      display: flex;
      gap: 10px;
    }

    .activity-timeline {
      background: white;
      border-radius: 8px;
      padding: 20px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .timeline-item {
      display: flex;
      margin-bottom: 20px;
      position: relative;
    }

    .timeline-item:not(:last-child)::after {
      content: '';
      position: absolute;
      left: 7px;
      top: 20px;
      width: 2px;
      height: calc(100% + 20px);
      background: #e9ecef;
    }

    .timeline-marker {
      width: 16px;
      height: 16px;
      border-radius: 50%;
      margin-right: 15px;
      margin-top: 2px;
      flex-shrink: 0;
      background: #6c757d;
    }

    .timeline-marker.action-sent {
      background: #28a745;
    }

    .timeline-marker.action-failed {
      background: #dc3545;
    }

    .timeline-marker.action-queued {
      background: #ffc107;
    }

    .timeline-content {
      flex: 1;
    }

    .timeline-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 5px;
    }

    .timeline-action {
      font-weight: 600;
      color: #495057;
    }

    .timeline-time {
      font-size: 12px;
      color: #6c757d;
    }

    .timeline-message {
      color: #6c757d;
      margin-bottom: 5px;
    }

    @media (max-width: 768px) {
      .dashboard-header {
        flex-direction: column;
        gap: 15px;
        text-align: center;
      }

      .metrics-grid,
      .charts-grid {
        grid-template-columns: 1fr;
      }

      .stats-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>

  <script>
    CRM.$(function($) {
      // Initialize dashboard
      initializeDashboard();

      // Auto-refresh every 30 seconds
      setInterval(refreshDashboard, 30000);

      // Manual refresh button
      $('#refresh-dashboard').click(function() {
        refreshDashboard();
      });

      function initializeDashboard() {
        // Initialize charts
        if (window.Chart) {
          initializeCharts();
        }

        // Initialize health score circle
        initializeHealthScore();

        // Bind action buttons
        bindActionButtons();
      }

      function refreshDashboard() {
        // Show loading state
        $('.refresh-indicator').addClass('active');

        // Reload page data
        location.reload();
      }

      function initializeHealthScore() {
        $('.score-circle').each(function() {
          var score = $(this).data('score');
          var angle = (score / 100) * 360;
          $(this).css('--score-angle', angle + 'deg');
        });
      }

      function bindActionButtons() {
        $('.process-queue-btn').click(function(e) {
          e.preventDefault();
          processQueue();
        });

        $('.cleanup-btn').click(function(e) {
          e.preventDefault();
          runCleanup();
        });

        $('.optimize-db-btn').click(function(e) {
          e.preventDefault();
          optimizeDatabase();
        });
      }

      function processQueue() {
        CRM.api3('Emailqueue', 'processqueue')
          .done(function(result) {
            CRM.alert('Queue processed successfully', 'Success', 'success');
            setTimeout(refreshDashboard, 2000);
          })
          .fail(function() {
            CRM.alert('Failed to process queue', 'Error', 'error');
          });
      }

      function runCleanup() {
        if (confirm('Are you sure you want to run cleanup? This will remove old emails.')) {
          CRM.api3('EmailqueueAdmin', 'cleanup')
            .done(function(result) {
              CRM.alert('Cleanup completed', 'Success', 'success');
              setTimeout(refreshDashboard, 2000);
            })
            .fail(function() {
              CRM.alert('Cleanup failed', 'Error', 'error');
            });
        }
      }

      function optimizeDatabase() {
        CRM.api3('EmailqueueAdmin', 'optimizeperformance')
          .done(function(result) {
            CRM.alert('Database optimization completed', 'Success', 'success');
            setTimeout(refreshDashboard, 2000);
          })
          .fail(function() {
            CRM.alert('Database optimization failed', 'Error', 'error');
          });
      }
    });
  </script>
{/literal}
