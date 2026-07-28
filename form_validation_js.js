/**
 * Anugrah Accounting - Form Validation Script
 * Universal validation for all forms
 */

document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form[id]');
    
    forms.forEach(form => {
        // Real-time validation on input
        const inputs = form.querySelectorAll('input, select, textarea');
        
        inputs.forEach(input => {
            // Validate on blur
            input.addEventListener('blur', function() {
                validateField(this);
            });
            
            // Clear error on input
            input.addEventListener('input', function() {
                if (this.closest('.form-group').classList.contains('has-error')) {
                    clearError(this);
                }
            });
        });
        
        // Form submission validation
        form.addEventListener('submit', function(e) {
            let isValid = true;
            const requiredFields = form.querySelectorAll('[required]');
            
            // Clear all previous errors
            form.querySelectorAll('.form-group').forEach(group => {
                group.classList.remove('has-error');
            });
            
            // Validate all required fields
            requiredFields.forEach(field => {
                if (!validateField(field)) {
                    isValid = false;
                }
            });
            
            // Validate specific field types
            const panFields = form.querySelectorAll('input[name="pan_number"]');
            panFields.forEach(field => {
                if (!validatePAN(field)) {
                    isValid = false;
                }
            });
            
            const emailFields = form.querySelectorAll('input[type="email"]');
            emailFields.forEach(field => {
                if (field.value && !validateEmail(field)) {
                    isValid = false;
                }
            });
            
            const phoneFields = form.querySelectorAll('input[name*="phone"], input[name*="mobile"]');
            phoneFields.forEach(field => {
                if (field.value && !validatePhone(field)) {
                    isValid = false;
                }
            });
            
            const gstFields = form.querySelectorAll('input[name="gst_number"]');
            gstFields.forEach(field => {
                if (field.value && !validateGST(field)) {
                    isValid = false;
                }
            });
            
            const numberFields = form.querySelectorAll('input[type="number"][min]');
            numberFields.forEach(field => {
                if (field.value && !validateNumber(field)) {
                    isValid = false;
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                
                // Scroll to first error
                const firstError = form.querySelector('.has-error');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                
                // Show general error message
                showGeneralError(form, 'Please correct the errors below before submitting.');
            } else {
                // Add loading state to submit button
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.classList.add('loading');
                    submitBtn.disabled = true;
                }
            }
        });
    });
});

/**
 * Validate a single field
 */
function validateField(field) {
    const formGroup = field.closest('.form-group');
    const errorMessage = formGroup.querySelector('.error-message');
    
    // Check if field is required and empty
    if (field.hasAttribute('required') && !field.value.trim()) {
        showError(formGroup, errorMessage, 'This field is required');
        return false;
    }
    
    // Check pattern attribute
    if (field.hasAttribute('pattern') && field.value) {
        const pattern = new RegExp(field.getAttribute('pattern'));
        if (!pattern.test(field.value)) {
            showError(formGroup, errorMessage, 'Invalid format');
            return false;
        }
    }
    
    // Check min/max length
    if (field.hasAttribute('minlength') && field.value.length < field.getAttribute('minlength')) {
        showError(formGroup, errorMessage, `Minimum ${field.getAttribute('minlength')} characters required`);
        return false;
    }
    
    if (field.hasAttribute('maxlength') && field.value.length > field.getAttribute('maxlength')) {
        showError(formGroup, errorMessage, `Maximum ${field.getAttribute('maxlength')} characters allowed`);
        return false;
    }
    
    return true;
}

/**
 * Validate PAN Number
 */
function validatePAN(field) {
    if (!field.value) return true;
    
    const panPattern = /^[A-Z]{5}[0-9]{4}[A-Z]{1}$/;
    const formGroup = field.closest('.form-group');
    const errorMessage = formGroup.querySelector('.error-message');
    
    if (!panPattern.test(field.value.toUpperCase())) {
        showError(formGroup, errorMessage, 'Invalid PAN format (e.g., ABCDE1234F)');
        return false;
    }
    
    return true;
}

/**
 * Validate Email
 */
function validateEmail(field) {
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const formGroup = field.closest('.form-group');
    const errorMessage = formGroup.querySelector('.error-message');
    
    if (!emailPattern.test(field.value)) {
        showError(formGroup, errorMessage, 'Invalid email address');
        return false;
    }
    
    return true;
}

/**
 * Validate Phone Number (Indian format)
 */
function validatePhone(field) {
    const phonePattern = /^[6-9]\d{9}$/;
    const formGroup = field.closest('.form-group');
    const errorMessage = formGroup.querySelector('.error-message');
    
    const cleanPhone = field.value.replace(/\D/g, '');
    
    if (!phonePattern.test(cleanPhone)) {
        showError(formGroup, errorMessage, 'Invalid phone number (10 digits starting with 6-9)');
        return false;
    }
    
    return true;
}

/**
 * Validate GST Number
 */
function validateGST(field) {
    const gstPattern = /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/;
    const formGroup = field.closest('.form-group');
    const errorMessage = formGroup.querySelector('.error-message');
    
    if (!gstPattern.test(field.value.toUpperCase())) {
        showError(formGroup, errorMessage, 'Invalid GST number format');
        return false;
    }
    
    return true;
}

/**
 * Validate Number Fields
 */
function validateNumber(field) {
    const formGroup = field.closest('.form-group');
    const errorMessage = formGroup.querySelector('.error-message');
    const value = parseFloat(field.value);
    const min = parseFloat(field.getAttribute('min'));
    const max = parseFloat(field.getAttribute('max'));
    
    if (isNaN(value)) {
        showError(formGroup, errorMessage, 'Please enter a valid number');
        return false;
    }
    
    if (!isNaN(min) && value < min) {
        showError(formGroup, errorMessage, `Value must be at least ${min}`);
        return false;
    }
    
    if (!isNaN(max) && value > max) {
        showError(formGroup, errorMessage, `Value must not exceed ${max}`);
        return false;
    }
    
    return true;
}

/**
 * Show error on field
 */
function showError(formGroup, errorMessage, message) {
    formGroup.classList.add('has-error');
    if (errorMessage) {
        errorMessage.textContent = message;
        errorMessage.style.display = 'block';
    }
}

/**
 * Clear error from field
 */
function clearError(field) {
    const formGroup = field.closest('.form-group');
    const errorMessage = formGroup.querySelector('.error-message');
    
    formGroup.classList.remove('has-error');
    if (errorMessage) {
        errorMessage.style.display = 'none';
    }
}

/**
 * Show general error message
 */
function showGeneralError(form, message) {
    let alertDiv = form.querySelector('.alert-error');
    
    if (!alertDiv) {
        alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-error';
        form.insertBefore(alertDiv, form.firstChild);
    }
    
    alertDiv.innerHTML = `<strong>✕ Error!</strong> ${message}`;
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        alertDiv.style.display = 'none';
    }, 5000);
}

/**
 * Format currency input
 */
function formatCurrency(input) {
    input.addEventListener('blur', function() {
        if (this.value) {
            const value = parseFloat(this.value);
            if (!isNaN(value)) {
                this.value = value.toFixed(2);
            }
        }
    });
}

// Apply currency formatting to all number inputs with specific names
document.addEventListener('DOMContentLoaded', function() {
    const currencyInputs = document.querySelectorAll(
        'input[name*="amount"], input[name*="sales"], input[name*="profit"], ' +
        'input[name*="assets"], input[name*="liabilities"], input[name*="salary"]'
    );
    
    currencyInputs.forEach(input => {
        if (input.type === 'number') {
            formatCurrency(input);
        }
    });
});