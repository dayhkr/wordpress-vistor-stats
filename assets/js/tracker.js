/**
 * Visitor Stats Behavior Tracker
 * Tracks time on page, scroll depth, and clicks
 */

(function($) {
    'use strict';
    
    var VisitorTracker = {
        startTime: Date.now(),
        scrollDepth: 0,
        clickCount: 0,
        maxScrollDepth: 0,
        isPageVisible: true,
        trackingInterval: null,
        
        init: function() {
            this.bindEvents();
            this.startTracking();
            this.trackPageVisibility();
        },
        
        bindEvents: function() {
            var self = this;
            
            // Track scroll depth
            $(window).on('scroll', this.throttle(function() {
                self.trackScrollDepth();
            }, 100));
            
            // Track clicks
            $(document).on('click', '*', function(e) {
                self.trackClick(e);
            });
            
            // Track page unload
            $(window).on('beforeunload', function() {
                self.sendBehaviorData();
            });
            
            // Track page visibility changes
            document.addEventListener('visibilitychange', function() {
                self.handleVisibilityChange();
            });
            
            // Track when user leaves page (mouse leaves window)
            $(document).on('mouseleave', function() {
                self.sendBehaviorData();
            });
        },
        
        startTracking: function() {
            var self = this;
            
            // Send data every 30 seconds while user is active
            this.trackingInterval = setInterval(function() {
                if (self.isPageVisible) {
                    self.sendBehaviorData();
                }
            }, 30000);
        },
        
        trackScrollDepth: function() {
            var scrollTop = $(window).scrollTop();
            var documentHeight = $(document).height();
            var windowHeight = $(window).height();
            
            var scrollPercent = Math.round((scrollTop / (documentHeight - windowHeight)) * 100);
            
            if (scrollPercent > this.maxScrollDepth) {
                this.maxScrollDepth = scrollPercent;
            }
        },
        
        trackClick: function(event) {
            this.clickCount++;
            
            // Track clicks on specific elements
            var element = $(event.target);
            var elementData = {
                tag: element.prop('tagName').toLowerCase(),
                id: element.attr('id') || '',
                class: element.attr('class') || '',
                text: element.text().substring(0, 50) || ''
            };
            
            // Send click data immediately for important elements
            if (element.is('a, button, input[type="submit"], input[type="button"]')) {
                this.sendClickData(elementData);
            }
        },
        
        sendClickData: function(elementData) {
            $.ajax({
                url: visitorStats.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'visitor_stats_track_click',
                    nonce: visitorStats.nonce,
                    session_id: visitorStats.sessionId,
                    page_url: visitorStats.pageUrl,
                    element_data: elementData,
                    timestamp: Date.now()
                },
                success: function(response) {
                    // Optional: Handle success
                },
                error: function() {
                    // Optional: Handle error
                }
            });
        },
        
        sendBehaviorData: function() {
            var timeOnPage = Math.round((Date.now() - this.startTime) / 1000);
            
            // Only send if user has been on page for at least 3 seconds
            if (timeOnPage < 3) {
                return;
            }
            
            $.ajax({
                url: visitorStats.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'visitor_stats_track_behavior',
                    nonce: visitorStats.nonce,
                    session_id: visitorStats.sessionId,
                    page_url: visitorStats.pageUrl,
                    time_on_page: timeOnPage,
                    scroll_depth: this.maxScrollDepth,
                    clicks: this.clickCount
                },
                success: function(response) {
                    // Reset counters after successful send
                    VisitorTracker.resetCounters();
                },
                error: function() {
                    // Optional: Handle error
                }
            });
        },
        
        resetCounters: function() {
            this.startTime = Date.now();
            this.maxScrollDepth = 0;
            this.clickCount = 0;
        },
        
        trackPageVisibility: function() {
            var self = this;
            
            // Track when page becomes visible/hidden
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    self.isPageVisible = false;
                    self.sendBehaviorData();
                } else {
                    self.isPageVisible = true;
                    self.startTime = Date.now(); // Reset timer when page becomes visible
                }
            });
        },
        
        handleVisibilityChange: function() {
            if (document.hidden) {
                this.isPageVisible = false;
            } else {
                this.isPageVisible = true;
                this.startTime = Date.now();
            }
        },
        
        // Utility function to throttle events
        throttle: function(func, wait) {
            var timeout;
            return function executedFunction() {
                var later = function() {
                    clearTimeout(timeout);
                    func.apply(this, arguments);
                }.bind(this);
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    };
    
    // Initialize tracker when DOM is ready
    $(document).ready(function() {
        // Check if tracking is enabled and user has consent
        if (typeof visitorStats !== 'undefined') {
            VisitorTracker.init();
        }
    });
    
    // Expose tracker globally for debugging
    window.VisitorTracker = VisitorTracker;
    
})(jQuery);
