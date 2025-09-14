/**
 * Again&Co Main JavaScript
 * Client-side functionality for the e-commerce website
 */

// Global application object
const EVinty = {
    // Configuration
    config: {
        debounceDelay: 300,
        ajaxTimeout: 10000
    },
    
    // Initialize application
    init: function() {
        this.bindEvents();
        this.initializeComponents();
        this.handleFlashMessages();
    },
    
    // Bind global event listeners
    bindEvents: function() {
        // Form validation
        document.addEventListener('submit', this.handleFormSubmit.bind(this));
        
        // AJAX forms
        document.querySelectorAll('.ajax-form').forEach(form => {
            form.addEventListener('submit', this.handleAjaxForm.bind(this));
        });
        
        // Quantity controls
        document.addEventListener('click', this.handleQuantityControls.bind(this));
        
        // Modal triggers
        document.addEventListener('click', this.handleModalTriggers.bind(this));
        
        // Search functionality
        const searchInput = document.getElementById('search-input');
        if (searchInput) {
            searchInput.addEventListener('input', this.debounce(this.handleSearch.bind(this), this.config.debounceDelay));
        }
        
        // File upload preview
        document.addEventListener('change', this.handleFileUpload.bind(this));
    },
    
    // Initialize components
    initializeComponents: function() {
        this.initializeDatePickers();
        this.initializeTooltips();
        this.initializeCarousels();
    },
    
    // Handle form submission with validation
    handleFormSubmit: function(event) {
        const form = event.target;
        
        if (form.classList.contains('needs-validation')) {
            event.preventDefault();
            event.stopPropagation();
            
            if (this.validateForm(form)) {
                if (!form.classList.contains('ajax-form')) {
                    this.showLoadingButton(form.querySelector('button[type="submit"]'));
                    form.submit();
                }
            }
            
            form.classList.add('was-validated');
        }
    },
    
    // Handle AJAX form submission
    handleAjaxForm: function(event) {
        event.preventDefault();
        
        const form = event.target;
        const submitButton = form.querySelector('button[type="submit"]');
        const formData = new FormData(form);
        
        this.showLoadingButton(submitButton);
        
        fetch(form.action, {
            method: form.method || 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            this.hideLoadingButton(submitButton);
            
            if (data.success) {
                this.showMessage(data.message || 'Operation successful', 'success');
                
                if (data.redirect) {
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1000);
                }
                
                if (data.reload) {
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                }
            } else {
                this.showMessage(data.message || 'An error occurred', 'error');
                
                if (data.errors) {
                    this.displayFormErrors(form, data.errors);
                }
            }
        })
        .catch(error => {
            this.hideLoadingButton(submitButton);
            this.showMessage('Network error occurred', 'error');
            console.error('Ajax error:', error);
        });
    },
    
    // Handle quantity controls for shopping cart
    handleQuantityControls: function(event) {
        const target = event.target;
        
        if (target.classList.contains('quantity-decrease')) {
            event.preventDefault();
            const input = target.nextElementSibling;
            const currentValue = parseInt(input.value) || 1;
            if (currentValue > 1) {
                input.value = currentValue - 1;
                this.updateCartItem(input);
            }
        }
        
        if (target.classList.contains('quantity-increase')) {
            event.preventDefault();
            const input = target.previousElementSibling;
            const currentValue = parseInt(input.value) || 1;
            const maxValue = parseInt(input.getAttribute('max')) || 999;
            if (currentValue < maxValue) {
                input.value = currentValue + 1;
                this.updateCartItem(input);
            }
        }
        
        if (target.classList.contains('remove-item')) {
            event.preventDefault();
            this.removeCartItem(target.dataset.productId);
        }
    },
    
    // Handle modal triggers
    handleModalTriggers: function(event) {
        const target = event.target;
        
        if (target.classList.contains('modal-trigger')) {
            event.preventDefault();
            const modalId = target.dataset.modal;
            this.showModal(modalId);
        }
        
        if (target.classList.contains('modal-close')) {
            event.preventDefault();
            this.hideModal(target.closest('.modal'));
        }
    },
    
    // Handle search functionality
    handleSearch: function(event) {
        const query = event.target.value.trim();
        const resultsContainer = document.getElementById('search-results');
        
        if (query.length < 2) {
            if (resultsContainer) {
                resultsContainer.innerHTML = '';
                resultsContainer.style.display = 'none';
            }
            return;
        }
        
        fetch(`/api/search.php?q=${encodeURIComponent(query)}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (resultsContainer) {
                resultsContainer.innerHTML = this.renderSearchResults(data.results);
                resultsContainer.style.display = data.results.length > 0 ? 'block' : 'none';
            }
        })
        .catch(error => {
            console.error('Search error:', error);
        });
    },
    
    // Handle file upload with preview
    handleFileUpload: function(event) {
        const input = event.target;
        
        if (input.type === 'file' && input.files.length > 0) {
            const file = input.files[0];
            const preview = document.getElementById(input.dataset.preview);
            
            if (preview && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
            
            // Validate file size
            const maxSize = parseInt(input.dataset.maxSize) || 5242880; // 5MB default
            if (file.size > maxSize) {
                this.showMessage('File size exceeds maximum allowed size', 'error');
                input.value = '';
                if (preview) preview.style.display = 'none';
            }
        }
    },
    
    // Form validation
    validateForm: function(form) {
        let isValid = true;
        const inputs = form.querySelectorAll('input, select, textarea');
        
        inputs.forEach(input => {
            if (!this.validateField(input)) {
                isValid = false;
            }
        });
        
        return isValid;
    },
    
    // Validate individual field
    validateField: function(field) {
        const value = field.value.trim();
        const type = field.type;
        const required = field.hasAttribute('required');
        
        // Clear previous errors
        this.clearFieldError(field);
        
        // Required validation
        if (required && !value) {
            this.setFieldError(field, 'This field is required');
            return false;
        }
        
        if (value) {
            // Email validation
            if (type === 'email' || field.classList.contains('email')) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(value)) {
                    this.setFieldError(field, 'Please enter a valid email address');
                    return false;
                }
            }
            
            // Password validation
            if (type === 'password' || field.classList.contains('password')) {
                if (value.length < 8) {
                    this.setFieldError(field, 'Password must be at least 8 characters long');
                    return false;
                }
            }
            
            // Confirm password validation
            if (field.classList.contains('confirm-password')) {
                const originalPassword = document.querySelector('input[type="password"]:not(.confirm-password)');
                if (originalPassword && value !== originalPassword.value) {
                    this.setFieldError(field, 'Passwords do not match');
                    return false;
                }
            }
            
            // Phone validation
            if (type === 'tel' || field.classList.contains('phone')) {
                const phoneRegex = /^\+?[\d\s\-\(\)]+$/;
                if (!phoneRegex.test(value)) {
                    this.setFieldError(field, 'Please enter a valid phone number');
                    return false;
                }
            }
        }
        
        return true;
    },
    
    // Set field error
    setFieldError: function(field, message) {
        field.classList.add('error');
        
        let errorElement = field.parentNode.querySelector('.error-message');
        if (!errorElement) {
            errorElement = document.createElement('div');
            errorElement.className = 'error-message';
            field.parentNode.appendChild(errorElement);
        }
        errorElement.textContent = message;
    },
    
    // Clear field error
    clearFieldError: function(field) {
        field.classList.remove('error');
        const errorElement = field.parentNode.querySelector('.error-message');
        if (errorElement) {
            errorElement.remove();
        }
    },
    
    // Display form errors from server
    displayFormErrors: function(form, errors) {
        Object.keys(errors).forEach(fieldName => {
            const field = form.querySelector(`[name="${fieldName}"]`);
            if (field) {
                this.setFieldError(field, errors[fieldName]);
            }
        });
    },
    
    // Show loading state on button
    showLoadingButton: function(button) {
        if (button) {
            button.disabled = true;
            button.dataset.originalText = button.textContent;
            button.innerHTML = '<span class="spinner"></span> Loading...';
        }
    },
    
    // Hide loading state on button
    hideLoadingButton: function(button) {
        if (button && button.dataset.originalText) {
            button.disabled = false;
            button.textContent = button.dataset.originalText;
            delete button.dataset.originalText;
        }
    },
    
    // Show message
    showMessage: function(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type}`;
        alertDiv.textContent = message;
        
        // Insert at top of main content
        const mainContent = document.querySelector('.main-content');
        if (mainContent) {
            mainContent.insertBefore(alertDiv, mainContent.firstChild);
        } else {
            document.body.insertBefore(alertDiv, document.body.firstChild);
        }
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    },
    
    // Handle flash messages
    handleFlashMessages: function() {
        const flashMessages = document.querySelectorAll('.alert');
        flashMessages.forEach(alert => {
            // Add close button
            const closeButton = document.createElement('button');
            closeButton.innerHTML = '&times;';
            closeButton.className = 'close-alert';
            closeButton.style.cssText = 'float: right; background: none; border: none; font-size: 1.5em; cursor: pointer;';
            alert.insertBefore(closeButton, alert.firstChild);
            
            closeButton.addEventListener('click', () => {
                alert.remove();
            });
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 5000);
        });
    },
    
    // Update cart item quantity
    updateCartItem: function(input) {
        const productId = input.dataset.productId;
        const quantity = parseInt(input.value);
        
        if (!productId || quantity < 1) return;
        
        fetch('/api/cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: 'update',
                product_id: productId,
                quantity: quantity
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.updateCartDisplay(data.cart);
            } else {
                this.showMessage(data.message || 'Failed to update cart', 'error');
            }
        })
        .catch(error => {
            console.error('Cart update error:', error);
            this.showMessage('Network error occurred', 'error');
        });
    },
    
    // Remove cart item
    removeCartItem: function(productId) {
        if (!confirm('Are you sure you want to remove this item?')) return;
        
        fetch('/api/cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: 'remove',
                product_id: productId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.querySelector(`[data-product-id="${productId}"]`).closest('.cart-item').remove();
                this.updateCartDisplay(data.cart);
            } else {
                this.showMessage(data.message || 'Failed to remove item', 'error');
            }
        })
        .catch(error => {
            console.error('Cart remove error:', error);
            this.showMessage('Network error occurred', 'error');
        });
    },
    
    // Update cart display
    updateCartDisplay: function(cartData) {
        // Update cart count
        const cartCount = document.querySelector('.cart-count');
        if (cartCount) {
            cartCount.textContent = cartData.item_count || 0;
        }
        
        // Update cart total
        const cartTotal = document.querySelector('.cart-total');
        if (cartTotal) {
            cartTotal.textContent = cartData.total || '$0.00';
        }
    },
    
    // Render search results
    renderSearchResults: function(results) {
        if (!results || results.length === 0) {
            return '<div class="search-no-results">No results found</div>';
        }
        
        return results.map(result => `
            <div class="search-result-item">
                <a href="/product.php?id=${result.product_id}">
                    <div class="search-result-title">${result.product_name}</div>
                    <div class="search-result-price">${result.price}</div>
                </a>
            </div>
        `).join('');
    },
    
    // Initialize date pickers
    initializeDatePickers: function() {
        // Basic date picker implementation
        // In a real application, you might use a library like Flatpickr
        const dateInputs = document.querySelectorAll('input[type="date"]');
        dateInputs.forEach(input => {
            // Add date validation
            input.addEventListener('change', function() {
                const date = new Date(this.value);
                const today = new Date();
                
                if (this.classList.contains('future-date') && date <= today) {
                    this.setCustomValidity('Please select a future date');
                } else if (this.classList.contains('past-date') && date >= today) {
                    this.setCustomValidity('Please select a past date');
                } else {
                    this.setCustomValidity('');
                }
            });
        });
    },
    
    // Initialize tooltips
    initializeTooltips: function() {
        const tooltipElements = document.querySelectorAll('[data-tooltip]');
        tooltipElements.forEach(element => {
            element.addEventListener('mouseenter', this.showTooltip.bind(this));
            element.addEventListener('mouseleave', this.hideTooltip.bind(this));
        });
    },
    
    // Show tooltip
    showTooltip: function(event) {
        const element = event.target;
        const text = element.dataset.tooltip;
        
        const tooltip = document.createElement('div');
        tooltip.className = 'tooltip';
        tooltip.textContent = text;
        tooltip.style.cssText = `
            position: absolute;
            background: #333;
            color: white;
            padding: 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
            z-index: 1000;
            pointer-events: none;
        `;
        
        document.body.appendChild(tooltip);
        
        const rect = element.getBoundingClientRect();
        tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
        tooltip.style.top = rect.top - tooltip.offsetHeight - 10 + 'px';
        
        element._tooltip = tooltip;
    },
    
    // Hide tooltip
    hideTooltip: function(event) {
        const element = event.target;
        if (element._tooltip) {
            element._tooltip.remove();
            delete element._tooltip;
        }
    },
    
    // Initialize carousels
    initializeCarousels: function() {
        // Basic carousel implementation
        // In a real application, you might use a library like Swiper
        const carousels = document.querySelectorAll('.carousel');
        carousels.forEach(carousel => {
            this.initCarousel(carousel);
        });
    },
    
    // Initialize individual carousel
    initCarousel: function(carousel) {
        const slides = carousel.querySelectorAll('.carousel-slide');
        const prevButton = carousel.querySelector('.carousel-prev');
        const nextButton = carousel.querySelector('.carousel-next');
        let currentSlide = 0;
        
        if (slides.length === 0) return;
        
        const showSlide = (index) => {
            slides.forEach((slide, i) => {
                slide.style.display = i === index ? 'block' : 'none';
            });
        };
        
        const nextSlide = () => {
            currentSlide = (currentSlide + 1) % slides.length;
            showSlide(currentSlide);
        };
        
        const prevSlide = () => {
            currentSlide = (currentSlide - 1 + slides.length) % slides.length;
            showSlide(currentSlide);
        };
        
        if (nextButton) nextButton.addEventListener('click', nextSlide);
        if (prevButton) prevButton.addEventListener('click', prevSlide);
        
        // Auto-advance slides every 5 seconds
        setInterval(nextSlide, 5000);
        
        // Show first slide
        showSlide(0);
    },
    
    // Show modal
    showModal: function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
    },
    
    // Hide modal
    hideModal: function(modal) {
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    },
    
    // Debounce function
    debounce: function(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },
    
    // Utility function to format currency
    formatCurrency: function(amount) {
        return new Intl.NumberFormat('en-AU', {
            style: 'currency',
            currency: 'AUD'
        }).format(amount);
    },
    
    // Utility function to format date
    formatDate: function(date, options = {}) {
        return new Intl.DateTimeFormat('en-AU', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            ...options
        }).format(new Date(date));
    }
};

// Initialize application when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    EVinty.init();
});

// Handle page unload
window.addEventListener('beforeunload', function(event) {
    // Check for unsaved form data
    const forms = document.querySelectorAll('form.warn-on-exit');
    for (let form of forms) {
        const formData = new FormData(form);
        const hasData = Array.from(formData.values()).some(value => value.trim() !== '');
        if (hasData) {
            event.preventDefault();
            return event.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
        }
    }
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = EVinty;
}
