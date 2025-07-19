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
              <canvas width="120" height="120" id="healthScoreCanvas"></canvas>
              <span class="score-value">{$dashboardData.queue_health.score}%</span>
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
            <select class="chart-timeframe" id="timeRange">
              <option value="24h">{ts}Last 24 Hours{/ts}</option>
              <option value="7d">{ts}Last 7 Days{/ts}</option>
              <option value="30d">{ts}Last 30 Days{/ts}</option>
            </select>
            <button class="btn btn-sm btn-outline-primary" id="refreshCharts">
              <i class="fas fa-sync"></i>
            </button>
          </div>
        </div>
        <div class="chart-body">
          <div class="chart-loading" style="display: none;">
            <div class="loading-spinner"></div>
            <span>{ts}Loading chart data...{/ts}</span>
          </div>
          <div class="chart-container">
            <canvas id="volumeChart" style="max-height: 300px;"></canvas>
          </div>
        </div>
      </div>

      {* Status Distribution Chart *}
      <div class="chart-card">
        <div class="chart-header">
          <h3 class="chart-title">{ts}Status Distribution{/ts}</h3>
          <div class="chart-controls">
            <button class="btn btn-sm btn-outline-secondary" id="exportCharts">
              <i class="fas fa-download"></i> {ts}Export{/ts}
            </button>
          </div>
        </div>
        <div class="chart-body">
          <div class="chart-loading" style="display: none;">
            <div class="loading-spinner"></div>
          </div>
          <div class="chart-container">
            <canvas id="statusChart" style="max-height: 250px;"></canvas>
          </div>
        </div>
      </div>

      {* Performance Trend Chart *}
      <div class="chart-card">
        <div class="chart-header">
          <h3 class="chart-title">{ts}Performance Trend{/ts}</h3>
          <div class="chart-controls">
            <div class="auto-refresh-toggle">
              <label>
                <input type="checkbox" id="autoRefresh" checked>
                {ts}Auto-refresh{/ts}
              </label>
              <select id="refreshInterval">
                <option value="1">1 min</option>
                <option value="5" selected>5 min</option>
                <option value="10">10 min</option>
              </select>
            </div>
          </div>
        </div>
        <div class="chart-body">
          <div class="chart-loading" style="display: none;">
            <div class="loading-spinner"></div>
          </div>
          <div class="chart-container">
            <canvas id="performanceChart" style="max-height: 300px;"></canvas>
          </div>
        </div>
      </div>

      {* Error Rate Chart *}
      <div class="chart-card">
        <div class="chart-header">
          <h3 class="chart-title">{ts}Error Rate{/ts}</h3>
          <div class="chart-controls">
            <span class="chart-info">
              <i class="fas fa-info-circle"></i>
              {ts}Errors per hour{/ts}
            </span>
          </div>
        </div>
        <div class="chart-body">
          <div class="chart-loading" style="display: none;">
            <div class="loading-spinner"></div>
          </div>
          <div class="chart-container">
            <canvas id="errorChart" style="max-height: 250px;"></canvas>
          </div>
        </div>
      </div>

    </div>
  </div>

  {* Action Buttons Section *}
  <div class="actions-section">
    <div class="action-buttons">
      <button class="btn btn-success" id="processQueue">
        <i class="fas fa-play"></i> {ts}Process Queue{/ts}
      </button>
      <button class="btn btn-warning" id="clearFailed">
        <i class="fas fa-trash"></i> {ts}Clear Failed{/ts}
      </button>
      <button class="btn btn-info" id="optimizeDb">
        <i class="fas fa-database"></i> {ts}Optimize DB{/ts}
      </button>
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

    .btn-outline-primary {
      background: transparent;
      color: #2c5aa0;
      border-color: #2c5aa0;
    }

    .btn-success {
      background: #28a745;
      color: white;
      border-color: #28a745;
    }

    .btn-warning {
      background: #ffc107;
      color: #212529;
      border-color: #ffc107;
    }

    .btn-info {
      background: #17a2b8;
      color: white;
      border-color: #17a2b8;
    }

    .btn-sm {
      padding: 4px 8px;
      font-size: 12px;
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

    .health-score {
      text-align: center;
      margin-bottom: 20px;
    }

    .score-circle {
      position: relative;
      display: inline-block;
      margin: 0 auto 15px;
    }

    .score-circle canvas {
      display: block;
    }

    .score-value {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      font-size: 18px;
      font-weight: bold;
      color: #2c5aa0;
      z-index: 1;
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

    .chart-controls {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .chart-body {
      padding: 20px;
      position: relative;
    }

    .chart-container {
      position: relative;
      height: 300px;
    }

    .chart-loading {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 10px;
      z-index: 10;
    }

    .loading-spinner {
      width: 30px;
      height: 30px;
      border: 3px solid #f3f3f3;
      border-top: 3px solid #2c5aa0;
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }

    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }

    .actions-section {
      margin-bottom: 30px;
    }

    .action-buttons {
      display: flex;
      gap: 15px;
      justify-content: center;
      padding: 20px;
      background: white;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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

      .action-buttons {
        flex-direction: column;
        align-items: center;
      }
    }
  </style>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
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
        // Wait for Chart.js to load
        if (typeof Chart !== 'undefined') {
          // Initialize charts with data from PHP
          window.chartData = {/literal}{$chartDataJson}{literal};

          // Initialize health score first
          initializeHealthScore();

          // Then initialize other charts with a delay to ensure DOM is ready
          setTimeout(function() {
            if (window.initializeCharts && !window.chartsInitialized) {
              window.initializeCharts();
            }
          }, 200);
        } else {
          // Retry after a short delay
          setTimeout(initializeDashboard, 500);
        }

        // Bind action buttons
        bindActionButtons();
      }

      function refreshDashboard() {
        // Show loading state
        $('.refresh-indicator').addClass('active');
        $('.chart-loading').show();
        $('.chart-container').css('opacity', '0.5');

        // Reload page data
        setTimeout(function() {
          location.reload();
        }, 1000);
      }

      function initializeHealthScore() {
        var scoreElement = $('.score-circle[data-score]');
        if (scoreElement.length > 0) {
          var score = parseInt(scoreElement.data('score')) || 0;
          var canvas = document.getElementById('healthScoreCanvas');

          if (canvas) {
            var ctx = canvas.getContext('2d');
            drawHealthScore(ctx, score);
          }
        }
      }

      function drawHealthScore(ctx, score) {
        var canvas = ctx.canvas;
        var centerX = canvas.width / 2;
        var centerY = canvas.height / 2;
        var radius = 45;

        // Clear canvas
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        // Background circle
        ctx.beginPath();
        ctx.arc(centerX, centerY, radius, 0, 2 * Math.PI);
        ctx.strokeStyle = '#e9ecef';
        ctx.lineWidth = 8;
        ctx.stroke();

        // Progress circle
        var angle = (score / 100) * 2 * Math.PI;
        ctx.beginPath();
        ctx.arc(centerX, centerY, radius, -Math.PI / 2, angle - Math.PI / 2);
        ctx.strokeStyle = getHealthColor(score);
        ctx.lineWidth = 8;
        ctx.lineCap = 'round';
        ctx.stroke();
      }

      function getHealthColor(score) {
        if (score >= 80) return '#28a745';
        if (score >= 60) return '#ffc107';
        if (score >= 40) return '#fd7e14';
        return '#dc3545';
      }

      function bindActionButtons() {
        $('#processQueue').click(function(e) {
          e.preventDefault();
          processQueue();
        });

        $('#clearFailed').click(function(e) {
          e.preventDefault();
          clearFailedEmails();
        });

        $('#optimizeDb').click(function(e) {
          e.preventDefault();
          optimizeDatabase();
        });
      }

      function processQueue() {
        if (typeof CRM !== 'undefined' && CRM.api3) {
          CRM.api3('EmailQueue', 'process', {})
            .done(function(result) {
              CRM.alert('Queue processing started', 'Success', 'success');
              setTimeout(refreshDashboard, 2000);
            })
            .fail(function() {
              CRM.alert('Failed to process queue', 'Error', 'error');
            });
        }
      }

      function clearFailedEmails() {
        if (confirm('Are you sure you want to clear all failed emails?')) {
          if (typeof CRM !== 'undefined' && CRM.api3) {
            CRM.api3('EmailQueue', 'clearfailed', {})
              .done(function(result) {
                CRM.alert('Failed emails cleared', 'Success', 'success');
                setTimeout(refreshDashboard, 2000);
              })
              .fail(function() {
                CRM.alert('Failed to clear failed emails', 'Error', 'error');
              });
          }
        }
      }

      function optimizeDatabase() {
        if (typeof CRM !== 'undefined' && CRM.api3) {
          CRM.api3('EmailqueueAdmin', 'optimizeperformance', {})
            .done(function(result) {
              CRM.alert('Database optimization completed', 'Success', 'success');
              setTimeout(refreshDashboard, 2000);
            })
            .fail(function() {
              CRM.alert('Database optimization failed', 'Error', 'error');
            });
        }
      }
    });
  </script>
{/literal}
