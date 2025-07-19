/**
 * Email Queue Dashboard Charts
 * Handles all chart rendering and interactions
 */

// Global variables
var chartData = window.chartData || {};
var chartInstances = {};
var autoRefreshInterval;
var chartsInitialized = false;

// Main initialization function
function initializeCharts() {
  // Prevent multiple initializations
  if (chartsInitialized) {
    console.log('Charts already initialized, cleaning up first...');
    cleanupCharts();
  }

  // Check if Chart.js is loaded
  if (typeof Chart === 'undefined') {
    console.error('Chart.js library not loaded');
    loadChartJS().then(function() {
      initializeAllCharts();
    }).catch(function(error) {
      console.error('Failed to load Chart.js:', error);
      showNotification('Failed to load charting library', 'error');
    });
    return;
  }

  initializeAllCharts();
}

function initializeAllCharts() {
  try {
    // First, destroy any existing charts
    destroyAllCharts();

    // Wait a moment for cleanup to complete
    setTimeout(function() {
      // Initialize individual charts
      initializeVolumeChart();
      initializeStatusChart();
      initializePerformanceChart();
      initializeErrorChart();

      // Bind event handlers only once
      if (!chartsInitialized) {
        bindChartEvents();
      }

      // Start auto-refresh if enabled
      var autoRefreshCheckbox = document.getElementById('autoRefresh');
      if (autoRefreshCheckbox && autoRefreshCheckbox.checked) {
        startAutoRefresh();
      }

      chartsInitialized = true;
      window.chartsInitialized = true;
      console.log('Charts initialized successfully');
    }, 100);

  } catch (error) {
    console.error('Error initializing charts:', error);
    showNotification('Error initializing charts: ' + error.message, 'error');
  }
}

// Destroy all chart instances safely
function destroyAllCharts() {
  Object.keys(chartInstances).forEach(function(key) {
    if (chartInstances[key]) {
      try {
        chartInstances[key].destroy();
        console.log('Destroyed chart:', key);
      } catch (error) {
        console.warn('Error destroying chart ' + key + ':', error);
      }
      delete chartInstances[key];
    }
  });
  chartInstances = {};
}

// Safely destroy a specific chart
function destroyChart(chartKey) {
  if (chartInstances[chartKey]) {
    try {
      chartInstances[chartKey].destroy();
      console.log('Destroyed chart:', chartKey);
    } catch (error) {
      console.warn('Error destroying chart ' + chartKey + ':', error);
    }
    delete chartInstances[chartKey];
  }
}

function initializeVolumeChart() {
  var ctx = document.getElementById('volumeChart');
  if (!ctx) {
    console.warn('Volume chart canvas not found');
    return;
  }

  // Destroy existing chart first
  destroyChart('volumeChart');

  var volumeData = chartData.volume_24h || {};

  if (Object.keys(volumeData).length === 0) {
    showEmptyChart(ctx, 'No volume data available');
    return;
  }

  var labels = Object.keys(volumeData).sort();
  var datasets = [];

  // Define statuses and colors
  var statuses = ['pending', 'sent', 'failed', 'cancelled'];
  var colors = {
    'pending': '#ffc107',
    'sent': '#28a745',
    'failed': '#dc3545',
    'cancelled': '#6c757d'
  };

  // Create datasets for each status
  statuses.forEach(function(status) {
    var data = labels.map(function(label) {
      return volumeData[label] && volumeData[label][status] ? parseInt(volumeData[label][status]) : 0;
    });

    datasets.push({
      label: status.charAt(0).toUpperCase() + status.slice(1),
      data: data,
      backgroundColor: colors[status] + '20',
      borderColor: colors[status],
      borderWidth: 2,
      fill: false,
      tension: 0.4
    });
  });

  // Destroy existing chart
  if (chartInstances.volumeChart) {
    chartInstances.volumeChart.destroy();
  }

  // Create new chart
  chartInstances.volumeChart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: labels.map(formatTimeLabel),
      datasets: datasets
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        title: {
          display: true,
          text: 'Email Volume Over Time'
        },
        legend: {
          position: 'top'
        },
        tooltip: {
          mode: 'index',
          intersect: false
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          title: {
            display: true,
            text: 'Number of Emails'
          }
        },
        x: {
          title: {
            display: true,
            text: 'Time'
          }
        }
      },
      interaction: {
        mode: 'nearest',
        axis: 'x',
        intersect: false
      }
    }
  });
}

function initializeStatusChart() {
  var ctx = document.getElementById('statusChart');
  if (!ctx) {
    console.warn('Status chart canvas not found');
    return;
  }

  // Destroy existing chart first
  destroyChart('statusChart');

  var statusData = chartData.status_distribution || [];

  if (statusData.length === 0) {
    showEmptyChart(ctx, 'No status data available');
    return;
  }

  var labels = statusData.map(function(item) { return item.label; });
  var data = statusData.map(function(item) { return parseInt(item.value); });
  var colors = statusData.map(function(item) { return item.color; });

  // Destroy existing chart
  if (chartInstances.statusChart) {
    chartInstances.statusChart.destroy();
  }

  // Create new chart
  chartInstances.statusChart = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: labels,
      datasets: [{
        data: data,
        backgroundColor: colors,
        borderWidth: 2,
        borderColor: '#ffffff'
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        title: {
          display: true,
          text: 'Email Status Distribution'
        },
        legend: {
          position: 'bottom'
        },
        tooltip: {
          callbacks: {
            label: function(context) {
              var label = context.label || '';
              var value = context.parsed;
              var total = context.dataset.data.reduce(function(a, b) { return a + b; }, 0);
              var percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
              return label + ': ' + value + ' (' + percentage + '%)';
            }
          }
        }
      }
    }
  });
}

function initializePerformanceChart() {
  var ctx = document.getElementById('performanceChart');
  if (!ctx) {
    console.warn('Performance chart canvas not found');
    return;
  }

  // Destroy existing chart first
  destroyChart('performanceChart');

  var performanceData = chartData.performance_trend || [];

  if (performanceData.length === 0) {
    showEmptyChart(ctx, 'No performance data available');
    return;
  }

  var labels = performanceData.map(function(item) {
    return formatTimeLabel(item.time);
  });

  // Destroy existing chart
  if (chartInstances.performanceChart) {
    chartInstances.performanceChart.destroy();
  }

  // Create new chart
  chartInstances.performanceChart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [{
        label: 'Emails Processed',
        data: performanceData.map(function(item) { return parseInt(item.throughput) || 0; }),
        backgroundColor: 'rgba(44, 90, 160, 0.1)',
        borderColor: '#2c5aa0',
        borderWidth: 2,
        fill: true,
        yAxisID: 'y',
        tension: 0.4
      }, {
        label: 'Avg Processing Time (seconds)',
        data: performanceData.map(function(item) { return parseFloat(item.avg_time) || 0; }),
        backgroundColor: 'rgba(255, 193, 7, 0.1)',
        borderColor: '#ffc107',
        borderWidth: 2,
        fill: false,
        yAxisID: 'y1',
        tension: 0.4
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: {
        mode: 'index',
        intersect: false,
      },
      plugins: {
        title: {
          display: true,
          text: 'Processing Performance Trend'
        },
        legend: {
          position: 'top'
        }
      },
      scales: {
        x: {
          title: {
            display: true,
            text: 'Time'
          }
        },
        y: {
          type: 'linear',
          display: true,
          position: 'left',
          title: {
            display: true,
            text: 'Emails Processed'
          },
          beginAtZero: true
        },
        y1: {
          type: 'linear',
          display: true,
          position: 'right',
          title: {
            display: true,
            text: 'Processing Time (seconds)'
          },
          beginAtZero: true,
          grid: {
            drawOnChartArea: false,
          },
        }
      }
    }
  });
}

function initializeErrorChart() {
  var ctx = document.getElementById('errorChart');
  if (!ctx) {
    console.warn('Error chart canvas not found');
    return;
  }

  // Destroy existing chart first
  destroyChart('errorChart');

  var errorData = chartData.error_trend || [];

  if (errorData.length === 0) {
    showEmptyChart(ctx, 'No error data available');
    return;
  }

  var labels = errorData.map(function(item) {
    return formatTimeLabel(item.hour);
  });
  var data = errorData.map(function(item) { return parseInt(item.error_count) || 0; });

  // Destroy existing chart
  if (chartInstances.errorChart) {
    chartInstances.errorChart.destroy();
  }

  // Create new chart
  chartInstances.errorChart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [{
        label: 'Error Count',
        data: data,
        backgroundColor: 'rgba(220, 53, 69, 0.6)',
        borderColor: '#dc3545',
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        title: {
          display: true,
          text: 'Error Rate Trend'
        },
        legend: {
          display: false
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          title: {
            display: true,
            text: 'Number of Errors'
          }
        },
        x: {
          title: {
            display: true,
            text: 'Time'
          }
        }
      }
    }
  });
}

function showEmptyChart(ctx, message) {
  var chart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: ['No Data'],
      datasets: [{
        data: [0],
        backgroundColor: '#e9ecef'
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: false
        },
        title: {
          display: true,
          text: message
        }
      },
      scales: {
        y: {
          display: false
        },
        x: {
          display: false
        }
      }
    }
  });

  return chart;
}

function bindChartEvents() {
  // Refresh charts button
  var refreshBtn = document.getElementById('refreshCharts');
  if (refreshBtn) {
    refreshBtn.addEventListener('click', function(e) {
      e.preventDefault();
      refreshCharts();
    });
  }

  // Export charts button
  var exportBtn = document.getElementById('exportCharts');
  if (exportBtn) {
    exportBtn.addEventListener('click', function(e) {
      e.preventDefault();
      exportChartsAsPNG();
    });
  }

  // Auto-refresh toggle
  var autoRefreshToggle = document.getElementById('autoRefresh');
  if (autoRefreshToggle) {
    autoRefreshToggle.addEventListener('change', function() {
      if (this.checked) {
        startAutoRefresh();
      } else {
        stopAutoRefresh();
      }
    });
  }

  // Time range selector
  var timeRangeSelect = document.getElementById('timeRange');
  if (timeRangeSelect) {
    timeRangeSelect.addEventListener('change', function() {
      var range = this.value;
      refreshChartsWithTimeRange(range);
    });
  }

  // Window resize handler
  window.addEventListener('resize', debounce(handleWindowResize, 250));
}

// Auto-refresh functionality
function startAutoRefresh() {
  var intervalSelect = document.getElementById('refreshInterval');
  var minutes = intervalSelect ? parseInt(intervalSelect.value) : 5;

  if (autoRefreshInterval) {
    clearInterval(autoRefreshInterval);
  }

  autoRefreshInterval = setInterval(function() {
    refreshCharts();
  }, minutes * 60 * 1000);

  showNotification('Auto-refresh enabled (' + minutes + ' minutes)', 'success');
}

function stopAutoRefresh() {
  if (autoRefreshInterval) {
    clearInterval(autoRefreshInterval);
    autoRefreshInterval = null;
  }
  showNotification('Auto-refresh disabled', 'info');
}

function refreshCharts() {
  showLoadingState();

  // Check if CRM API is available
  if (typeof CRM !== 'undefined' && CRM.api3) {
    CRM.api3('EmailqueueAdmin', 'getmetrics')
      .done(function(result) {
        hideLoadingState();
        if (result.values && result.values.charts) {
          chartData = result.values.charts;
          window.chartData = chartData;

          // Reinitialize charts with new data
          initializeAllCharts();

          showNotification('Charts refreshed successfully', 'success');
        } else {
          showNotification('No chart data received', 'warning');
        }
      })
      .fail(function(xhr, status, error) {
        hideLoadingState();
        console.error('Chart refresh failed:', error);
        showNotification('Failed to refresh chart data: ' + error, 'error');
      });
  } else {
    hideLoadingState();
    showNotification('CRM API not available', 'error');
  }
}

function refreshChartsWithTimeRange(range) {
  showLoadingState();

  if (typeof CRM !== 'undefined' && CRM.api3) {
    CRM.api3('EmailqueueAdmin', 'getmetrics', { time_range: range })
      .done(function(result) {
        hideLoadingState();
        if (result.values && result.values.charts) {
          chartData = result.values.charts;
          window.chartData = chartData;
          initializeAllCharts();
          showNotification('Charts updated for ' + range + ' period', 'success');
        }
      })
      .fail(function() {
        hideLoadingState();
        showNotification('Failed to refresh chart data', 'error');
      });
  } else {
    hideLoadingState();
    showNotification('CRM API not available', 'error');
  }
}

// Utility functions
function formatTimeLabel(timeString) {
  try {
    var date = new Date(timeString);
    if (isNaN(date.getTime())) {
      return timeString;
    }

    var today = new Date();
    var isToday = date.toDateString() === today.toDateString();

    if (isToday) {
      return date.getHours().toString().padStart(2, '0') + ':00';
    } else {
      return (date.getMonth() + 1) + '/' + date.getDate() + ' ' +
        date.getHours().toString().padStart(2, '0') + ':00';
    }
  } catch (error) {
    console.warn('Error formatting time label:', error);
    return timeString;
  }
}

function showLoadingState() {
  var loadingElements = document.querySelectorAll('.chart-loading');
  loadingElements.forEach(function(element) {
    element.style.display = 'flex';
  });

  var chartContainers = document.querySelectorAll('.chart-container');
  chartContainers.forEach(function(container) {
    container.style.opacity = '0.5';
  });
}

function hideLoadingState() {
  var loadingElements = document.querySelectorAll('.chart-loading');
  loadingElements.forEach(function(element) {
    element.style.display = 'none';
  });

  var chartContainers = document.querySelectorAll('.chart-container');
  chartContainers.forEach(function(container) {
    container.style.opacity = '1';
  });
}

function showNotification(message, type) {
  // Use CiviCRM's notification system if available
  if (typeof CRM !== 'undefined' && CRM.alert) {
    var title = type === 'success' ? 'Success' :
      type === 'error' ? 'Error' :
        type === 'warning' ? 'Warning' : 'Info';
    CRM.alert(message, title, type);
  } else {
    // Fallback to console
    console.log(type.toUpperCase() + ': ' + message);
  }
}

function exportChartsAsPNG() {
  try {
    Object.keys(chartInstances).forEach(function(key) {
      var chart = chartInstances[key];
      if (chart) {
        var url = chart.toBase64Image();
        var link = document.createElement('a');
        link.download = key + '_chart.png';
        link.href = url;
        link.click();
      }
    });
    showNotification('Charts exported successfully', 'success');
  } catch (error) {
    console.error('Export failed:', error);
    showNotification('Failed to export charts: ' + error.message, 'error');
  }
}

// Handle window resize for responsive charts
function handleWindowResize() {
  Object.keys(chartInstances).forEach(function(key) {
    if (chartInstances[key] && typeof chartInstances[key].resize === 'function') {
      chartInstances[key].resize();
    }
  });
}

// Debounce utility function
function debounce(func, wait) {
  var timeout;
  return function executedFunction() {
    var context = this;
    var args = arguments;
    var later = function() {
      clearTimeout(timeout);
      func.apply(context, args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

// Function to dynamically load Chart.js if not available
function loadChartJS() {
  return new Promise(function(resolve, reject) {
    if (window.Chart) {
      resolve();
      return;
    }

    var script = document.createElement('script');
    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js';
    script.onload = function() {
      console.log('Chart.js loaded successfully');
      resolve();
    };
    script.onerror = function() {
      reject(new Error('Failed to load Chart.js from CDN'));
    };
    document.head.appendChild(script);
  });
}

// Clean up function for when leaving the page
function cleanupCharts() {
  // Stop auto-refresh
  stopAutoRefresh();

  // Destroy all chart instances
  destroyAllCharts();

  // Reset initialization flag
  chartsInitialized = false;
  window.chartsInitialized = false;

  console.log('Charts cleanup completed');
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', function() {
    // Only initialize if not already done
    if (!chartsInitialized) {
      setTimeout(initializeCharts, 200);
    }
  });
} else {
  // Only initialize if not already done
  if (!chartsInitialized) {
    setTimeout(initializeCharts, 200);
  }
}

// Clean up when leaving the page
window.addEventListener('beforeunload', cleanupCharts);

// Export functions for global access
window.initializeCharts = initializeCharts;
window.refreshCharts = refreshCharts;
window.cleanupCharts = cleanupCharts;
window.chartsInitialized = false;
