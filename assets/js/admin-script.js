/**
 * Visitor Stats Admin Dashboard JavaScript
 */

(function($) {
    'use strict';
    
    var VisitorStatsAdmin = {
        charts: {},
        currentData: null,
        
        init: function() {
            this.bindEvents();
            this.loadInitialData();
            this.setupAutoRefresh();
        },
        
        bindEvents: function() {
            var self = this;
            
            // Time range change
            $('#time-range').on('change', function() {
                self.handleTimeRangeChange();
            });
            
            // Refresh button
            $('#refresh-data').on('click', function() {
                self.loadData();
            });
            
            // Export button
            $('#export-data').on('click', function() {
                self.exportData();
            });
        },
        
        handleTimeRangeChange: function() {
            var timeRange = $('#time-range').val();
            var customRange = $('.visitor-stats-custom-range');
            
            if (timeRange === 'custom') {
                customRange.show();
                this.setDefaultCustomDates();
            } else {
                customRange.hide();
                this.loadData();
            }
        },
        
        setDefaultCustomDates: function() {
            var today = new Date();
            var lastMonth = new Date(today.getFullYear(), today.getMonth() - 1, today.getDate());
            
            $('#end-date').val(this.formatDate(today));
            $('#start-date').val(this.formatDate(lastMonth));
        },
        
        formatDate: function(date) {
            var year = date.getFullYear();
            var month = String(date.getMonth() + 1).padStart(2, '0');
            var day = String(date.getDate()).padStart(2, '0');
            return year + '-' + month + '-' + day;
        },
        
        loadInitialData: function() {
            this.loadData();
        },
        
        setupAutoRefresh: function() {
            var self = this;
            
            // Refresh data every 5 minutes
            setInterval(function() {
                self.loadData(true); // Silent refresh
            }, 300000);
        },
        
        loadData: function(silent) {
            if (!silent) {
                this.showLoading();
            }
            
            var timeRange = $('#time-range').val();
            var startDate = $('#start-date').val();
            var endDate = $('#end-date').val();
            
            var self = this;
            
            $.ajax({
                url: visitorStatsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'visitor_stats_get_data',
                    nonce: visitorStatsAdmin.nonce,
                    time_range: timeRange,
                    start_date: startDate,
                    end_date: endDate
                },
                success: function(response) {
                    if (response.success) {
                        self.currentData = response.data;
                        self.updateDashboard(response.data);
                    } else {
                        self.showError(response.data.message || visitorStatsAdmin.strings.error);
                    }
                },
                error: function() {
                    self.showError(visitorStatsAdmin.strings.error);
                },
                complete: function() {
                    if (!silent) {
                        self.hideLoading();
                    }
                }
            });
        },
        
        updateDashboard: function(data) {
            this.updateOverviewCards(data.overview);
            this.updateVisitsChart(data.visits_over_time);
            this.updateBrowserChart(data.browser_stats);
            this.updateDeviceChart(data.device_stats);
            this.updateCountriesChart(data.geo_stats);
            this.updateTopPagesTable(data.top_pages);
            this.updateReferrersTable(data.top_referrers);
            this.updateRecentVisitorsTable(data.recent_visitors);
        },
        
        updateOverviewCards: function(overview) {
            $('#total-visits').text(this.formatNumber(overview.total_visits));
            $('#unique-visitors').text(this.formatNumber(overview.unique_visitors));
            $('#page-views').text(this.formatNumber(overview.page_views));
            $('#bounce-rate').text(overview.bounce_rate);
        },
        
        updateVisitsChart: function(data) {
            var ctx = document.getElementById('visits-chart').getContext('2d');
            
            var labels = data.map(function(item) {
                return item.period;
            });
            
            var visitsData = data.map(function(item) {
                return item.visits;
            });
            
            var uniqueVisitorsData = data.map(function(item) {
                return item.unique_visitors;
            });
            
            if (this.charts.visits) {
                this.charts.visits.destroy();
            }
            
            this.charts.visits = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Total Visits',
                        data: visitsData,
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.1)',
                        tension: 0.1
                    }, {
                        label: 'Unique Visitors',
                        data: uniqueVisitorsData,
                        borderColor: 'rgb(255, 99, 132)',
                        backgroundColor: 'rgba(255, 99, 132, 0.1)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        },
        
        updateBrowserChart: function(data) {
            var ctx = document.getElementById('browsers-chart').getContext('2d');
            
            var labels = data.map(function(item) {
                return item.browser;
            });
            
            var values = data.map(function(item) {
                return item.count;
            });
            
            if (this.charts.browsers) {
                this.charts.browsers.destroy();
            }
            
            this.charts.browsers = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: [
                            '#FF6384',
                            '#36A2EB',
                            '#FFCE56',
                            '#4BC0C0',
                            '#9966FF',
                            '#FF9F40'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        }
                    }
                }
            });
        },
        
        updateDeviceChart: function(data) {
            var ctx = document.getElementById('devices-chart').getContext('2d');
            
            var labels = data.map(function(item) {
                return item.device_type;
            });
            
            var values = data.map(function(item) {
                return item.count;
            });
            
            if (this.charts.devices) {
                this.charts.devices.destroy();
            }
            
            this.charts.devices = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: [
                            '#FF6384',
                            '#36A2EB',
                            '#FFCE56'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        }
                    }
                }
            });
        },
        
        updateCountriesChart: function(data) {
            var ctx = document.getElementById('countries-chart').getContext('2d');
            
            // Take only top 10 countries
            var topData = data.slice(0, 10);
            
            var labels = topData.map(function(item) {
                return item.country || 'Unknown';
            });
            
            var values = topData.map(function(item) {
                return item.count;
            });
            
            if (this.charts.countries) {
                this.charts.countries.destroy();
            }
            
            this.charts.countries = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Visits',
                        data: values,
                        backgroundColor: 'rgba(54, 162, 235, 0.8)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        },
        
        updateTopPagesTable: function(data) {
            var tbody = $('#top-pages-table tbody');
            tbody.empty();
            
            if (data.length === 0) {
                tbody.append('<tr><td colspan="3" class="visitor-stats-no-data">' + visitorStatsAdmin.strings.noData + '</td></tr>');
                return;
            }
            
            var self = this;
            data.forEach(function(page) {
                var row = '<tr>' +
                    '<td>' + self.truncateUrl(page.page_url) + '</td>' +
                    '<td>' + self.formatNumber(page.page_views) + '</td>' +
                    '<td>' + self.formatNumber(page.unique_visitors) + '</td>' +
                    '</tr>';
                tbody.append(row);
            });
        },
        
        updateReferrersTable: function(data) {
            var tbody = $('#top-referrers-table tbody');
            tbody.empty();
            
            if (data.length === 0) {
                tbody.append('<tr><td colspan="2" class="visitor-stats-no-data">' + visitorStatsAdmin.strings.noData + '</td></tr>');
                return;
            }
            
            var self = this;
            data.forEach(function(referrer) {
                var row = '<tr>' +
                    '<td>' + self.truncateUrl(referrer.referrer) + '</td>' +
                    '<td>' + self.formatNumber(referrer.count) + '</td>' +
                    '</tr>';
                tbody.append(row);
            });
        },
        
        updateRecentVisitorsTable: function(data) {
            var tbody = $('#recent-visitors-table tbody');
            tbody.empty();
            
            if (data.length === 0) {
                tbody.append('<tr><td colspan="5" class="visitor-stats-no-data">' + visitorStatsAdmin.strings.noData + '</td></tr>');
                return;
            }
            
            var self = this;
            data.forEach(function(visitor) {
                var row = '<tr>' +
                    '<td>' + self.formatTime(visitor.timestamp) + '</td>' +
                    '<td>' + self.truncateUrl(visitor.page_url) + '</td>' +
                    '<td>' + (visitor.country || '-') + '</td>' +
                    '<td>' + (visitor.browser || '-') + '</td>' +
                    '<td>' + (visitor.device_type || '-') + '</td>' +
                    '</tr>';
                tbody.append(row);
            });
        },
        
        exportData: function() {
            var timeRange = $('#time-range').val();
            var startDate = $('#start-date').val();
            var endDate = $('#end-date').val();
            
            var form = $('<form>', {
                method: 'POST',
                action: visitorStatsAdmin.ajaxUrl
            });
            
            form.append($('<input>', {
                type: 'hidden',
                name: 'action',
                value: 'visitor_stats_export_data'
            }));
            
            form.append($('<input>', {
                type: 'hidden',
                name: 'nonce',
                value: visitorStatsAdmin.nonce
            }));
            
            form.append($('<input>', {
                type: 'hidden',
                name: 'time_range',
                value: timeRange
            }));
            
            form.append($('<input>', {
                type: 'hidden',
                name: 'start_date',
                value: startDate
            }));
            
            form.append($('<input>', {
                type: 'hidden',
                name: 'end_date',
                value: endDate
            }));
            
            $('body').append(form);
            form.submit();
            form.remove();
        },
        
        showLoading: function() {
            $('.visitor-stats-loading').show();
        },
        
        hideLoading: function() {
            $('.visitor-stats-loading').hide();
        },
        
        showError: function(message) {
            // Show error message
            alert(message);
        },
        
        formatNumber: function(num) {
            return new Intl.NumberFormat().format(num);
        },
        
        formatTime: function(timestamp) {
            var date = new Date(timestamp);
            return date.toLocaleString();
        },
        
        truncateUrl: function(url) {
            if (url.length > 50) {
                return url.substring(0, 47) + '...';
            }
            return url;
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        VisitorStatsAdmin.init();
    });
    
    // Expose globally for debugging
    window.VisitorStatsAdmin = VisitorStatsAdmin;
    
})(jQuery);
