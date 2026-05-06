// LECTURER DASHBOARD JAVASCRIPT
document.addEventListener('DOMContentLoaded', function() {
    // Live time update
    function updateLiveTime() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('en-US', { 
            hour12: false,
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
        
        const liveTimeElement = document.getElementById('live-time');
        if (liveTimeElement) {
            liveTimeElement.textContent = timeString;
        }
    }
    
    // Update time every second
    setInterval(updateLiveTime, 1000);
    updateLiveTime(); // Initial call
    
    // Add interactivity to class items
    const classItems = document.querySelectorAll('.class-item');
    classItems.forEach(item => {
        item.addEventListener('click', function(e) {
            if (!e.target.classList.contains('btn-lecturer')) {
                const originalBg = this.style.backgroundColor;
                this.style.backgroundColor = '#f1f5ff';
                
                setTimeout(() => {
                    this.style.backgroundColor = originalBg;
                }, 300);
            }
        });
    });
    
    // Refresh attendance data
    const refreshBtn = document.querySelector('.fa-sync-alt');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            this.classList.add('fa-spin');
            
            // Simulate API call
            setTimeout(() => {
                this.classList.remove('fa-spin');
                
                // Show success toast
                showToast('Attendance data refreshed successfully!', 'success');
            }, 1000);
        });
    }
    
    // Toast notification function
    function showToast(message, type = 'info') {
        // Remove existing toasts
        const existingToast = document.querySelector('.toast');
        if (existingToast) {
            existingToast.remove();
        }
        
        // Create toast
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'}"></i>
            <span>${message}</span>
            <button class="toast-close">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        // Add to body
        document.body.appendChild(toast);
        
        // Close button functionality
        const closeBtn = toast.querySelector('.toast-close');
        closeBtn.addEventListener('click', () => {
            toast.remove();
        });
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (toast.parentNode) {
                toast.remove();
            }
        }, 5000);
    }
    
    // Quick action card clicks
    const actionCards = document.querySelectorAll('.action-card-lecturer');
    actionCards.forEach(card => {
        card.addEventListener('click', function() {
            this.style.transform = 'translateY(-5px) scale(1.02)';
            
            setTimeout(() => {
                this.style.transform = '';
            }, 300);
        });
    });
    
    // Mark task as complete
    const taskItems = document.querySelectorAll('.task-item');
    taskItems.forEach(item => {
        item.addEventListener('dblclick', function() {
            const icon = this.querySelector('.task-icon i');
            if (icon.classList.contains('fa-exclamation')) {
                icon.className = 'fas fa-check';
                this.querySelector('.task-time').textContent = 'Completed';
                this.querySelector('.task-time').style.color = 'var(--lecturer-success)';
                
                showToast('Task marked as completed!', 'success');
            }
        });
    });
    
    // Initialize tooltips
    const elementsWithTitle = document.querySelectorAll('[title]');
    elementsWithTitle.forEach(el => {
        el.addEventListener('mouseenter', function(e) {
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = this.title;
            tooltip.style.position = 'absolute';
            tooltip.style.background = 'var(--lecturer-dark)';
            tooltip.style.color = 'white';
            tooltip.style.padding = '6px 12px';
            tooltip.style.borderRadius = '6px';
            tooltip.style.fontSize = '0.85rem';
            tooltip.style.zIndex = '1000';
            tooltip.style.top = (e.clientY + 15) + 'px';
            tooltip.style.left = (e.clientX + 15) + 'px';
            
            document.body.appendChild(tooltip);
            
            this._tooltip = tooltip;
        });
        
        el.addEventListener('mouseleave', function() {
            if (this._tooltip) {
                this._tooltip.remove();
                delete this._tooltip;
            }
        });
    });
});