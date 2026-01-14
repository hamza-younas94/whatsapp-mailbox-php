/**
 * Professional Form Validation System
 * Provides real-time and submit-time validation with beautiful UI feedback
 */

class FormValidator {
    constructor(formId, rules = {}) {
        this.form = document.getElementById(formId);
        this.rules = rules;
        this.errors = {};
        
        if (!this.form) {
            console.error(`Form with id "${formId}" not found`);
            return;
        }
        
        this.init();
    }
    
    init() {
        // Add Bootstrap validation class
        this.form.classList.add('needs-validation');
        
        // Add error container
        const errorContainer = document.createElement('div');
        errorContainer.className = 'validation-errors';
        this.form.insertBefore(errorContainer, this.form.firstChild);
        
        // Add real-time validation to all inputs
        const inputs = this.form.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            // Skip hidden inputs
            if (input.type === 'hidden') return;
            
            // Add Bootstrap invalid-feedback div
            this.addFeedbackDiv(input);
            
            // Real-time validation
            input.addEventListener('blur', () => this.validateField(input));
            input.addEventListener('input', () => {
                if (this.errors[input.name]) {
                    this.validateField(input);
                }
            });
        });
        
        // Form submit validation
        this.form.addEventListener('submit', (e) => {
            if (!this.validate()) {
                e.preventDefault();
                e.stopPropagation();
                this.form.classList.add('was-validated');
                return false;
            }
        });
    }
    
    addFeedbackDiv(input) {
        // Check if feedback div already exists
        if (input.nextElementSibling && 
            (input.nextElementSibling.classList.contains('invalid-feedback') || 
             input.nextElementSibling.classList.contains('valid-feedback'))) {
            return;
        }
        
        // Create invalid feedback div
        const invalidFeedback = document.createElement('div');
        invalidFeedback.className = 'invalid-feedback';
        invalidFeedback.setAttribute('data-field', input.name);
        
        // Insert after input (or after its parent if it's in a wrapper)
        const parent = input.parentElement;
        if (parent.classList.contains('mb-3') || parent.classList.contains('form-group')) {
            parent.appendChild(invalidFeedback);
        } else {
            input.parentNode.insertBefore(invalidFeedback, input.nextSibling);
        }
    }
    
    
    validate() {
        this.errors = {};
        const inputs = this.form.querySelectorAll('input, textarea, select');
        
        inputs.forEach(input => {
            if (input.type === 'hidden') return;
            this.validateField(input);
        });
        
        this.displayErrors();
        return Object.keys(this.errors).length === 0;
    }
    
    validateField(input) {
        const fieldName = input.name;
        const value = this.getValue(input);
        const fieldRules = this.rules[fieldName] || [];
        
        // Clear previous error
        delete this.errors[fieldName];
        this.clearFieldError(input);
        
        // Apply validation rules
        for (const rule of fieldRules) {
            const error = this.applyRule(rule, value, input);
            if (error) {
                this.errors[fieldName] = error;
                this.showFieldError(input, error);
                break; // Stop at first error
            }
        }
        
        // Update field state
        this.updateFieldState(input, !this.errors[fieldName]);
        
        return !this.errors[fieldName];
    }
    
    applyRule(rule, value, input) {
        if (typeof rule === 'string') {
            return this.applyStringRule(rule, value, input);
        } else if (typeof rule === 'function') {
            return rule(value, input);
        } else if (typeof rule === 'object') {
            return this.applyObjectRule(rule, value, input);
        }
        return null;
    }
    
    applyStringRule(rule, value, input) {
        switch (rule) {
            case 'required':
                if (!value || (typeof value === 'string' && value.trim() === '')) {
                    return `${this.getFieldLabel(input)} is required`;
                }
                break;
                
            case 'email':
                if (value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                    return `${this.getFieldLabel(input)} must be a valid email address`;
                }
                break;
                
            case 'url':
                if (value && !/^https?:\/\/.+/.test(value)) {
                    return `${this.getFieldLabel(input)} must be a valid URL`;
                }
                break;
                
            case 'phone':
                if (value && !/^\+?[\d\s\-()]+$/.test(value)) {
                    return `${this.getFieldLabel(input)} must be a valid phone number`;
                }
                break;
                
            case 'number':
                if (value && isNaN(value)) {
                    return `${this.getFieldLabel(input)} must be a number`;
                }
                break;
                
            case 'integer':
                if (value && !Number.isInteger(parseFloat(value))) {
                    return `${this.getFieldLabel(input)} must be an integer`;
                }
                break;
                
            default:
                // Handle min:max:length patterns
                if (rule.startsWith('min:')) {
                    const min = parseInt(rule.split(':')[1]);
                    if (value && value.length < min) {
                        return `${this.getFieldLabel(input)} must be at least ${min} characters`;
                    }
                } else if (rule.startsWith('max:')) {
                    const max = parseInt(rule.split(':')[1]);
                    if (value && value.length > max) {
                        return `${this.getFieldLabel(input)} must not exceed ${max} characters`;
                    }
                } else if (rule.startsWith('length:')) {
                    const length = parseInt(rule.split(':')[1]);
                    if (value && value.length !== length) {
                        return `${this.getFieldLabel(input)} must be exactly ${length} characters`;
                    }
                }
        }
        return null;
    }
    
    applyObjectRule(rule, value, input) {
        if (rule.required && (!value || (typeof value === 'string' && value.trim() === ''))) {
            return rule.message || `${this.getFieldLabel(input)} is required`;
        }
        
        if (rule.min && value && value.length < rule.min) {
            return rule.message || `${this.getFieldLabel(input)} must be at least ${rule.min} characters`;
        }
        
        if (rule.max && value && value.length > rule.max) {
            return rule.message || `${this.getFieldLabel(input)} must not exceed ${rule.max} characters`;
        }
        
        if (rule.pattern && value && !rule.pattern.test(value)) {
            return rule.message || `${this.getFieldLabel(input)} format is invalid`;
        }
        
        if (rule.custom && typeof rule.custom === 'function') {
            const error = rule.custom(value, input);
            if (error) return error;
        }
        
        return null;
    }
    
    getValue(input) {
        if (input.type === 'checkbox') {
            return input.checked;
        } else if (input.type === 'radio') {
            const checked = this.form.querySelector(`input[name="${input.name}"]:checked`);
            return checked ? checked.value : null;
        } else if (input.tagName === 'SELECT') {
            return input.value;
        }
        return input.value;
    }
    
    getFieldLabel(input) {
        const label = this.form.querySelector(`label[for="${input.id}"]`);
        if (label) {
            return label.textContent.replace('*', '').trim();
        }
        return input.name.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }
    
    showFieldError(input, message) {
        // Use Bootstrap classes
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
        
        // Find or create invalid-feedback div
        let feedback = input.nextElementSibling;
        if (!feedback || !feedback.classList.contains('invalid-feedback')) {
            // Look in parent
            const parent = input.parentElement;
            feedback = parent.querySelector('.invalid-feedback[data-field="' + input.name + '"]');
            
            if (!feedback) {
                this.addFeedbackDiv(input);
                feedback = input.nextElementSibling || parent.querySelector('.invalid-feedback[data-field="' + input.name + '"]');
            }
        }
        
        if (feedback) {
            feedback.textContent = message;
        }
    }
    
    clearFieldError(input) {
        // Remove Bootstrap classes
        input.classList.remove('is-invalid', 'is-valid');
        
        // Clear feedback message
        const feedback = input.nextElementSibling;
        if (feedback && feedback.classList.contains('invalid-feedback')) {
            feedback.textContent = '';
        } else {
            const parent = input.parentElement;
            const parentFeedback = parent.querySelector('.invalid-feedback[data-field="' + input.name + '"]');
            if (parentFeedback) {
                parentFeedback.textContent = '';
            }
        }
    }
    
    updateFieldState(input, isValid) {
        if (isValid && this.getValue(input)) {
            input.classList.add('is-valid');
            input.classList.remove('is-invalid');
        } else {
            input.classList.remove('is-valid');
        }
    }
    
    displayErrors() {
        const errorContainer = this.form.querySelector('.validation-errors');
        if (!errorContainer) return;
        
        errorContainer.innerHTML = '';
        
        if (Object.keys(this.errors).length > 0) {
            errorContainer.classList.add('show');
            const errorList = document.createElement('ul');
            errorList.className = 'error-list';
            
            Object.values(this.errors).forEach(error => {
                const li = document.createElement('li');
                li.textContent = error;
                errorList.appendChild(li);
            });
            
            errorContainer.appendChild(errorList);
        } else {
            errorContainer.classList.remove('show');
        }
    }
    
    setErrors(errors) {
        // Set errors from backend response
        this.errors = errors;
        this.displayErrors();
        
        // Show field errors
        Object.keys(errors).forEach(fieldName => {
            const input = this.form.querySelector(`[name="${fieldName}"]`);
            if (input) {
                const error = Array.isArray(errors[fieldName]) 
                    ? errors[fieldName][0] 
                    : errors[fieldName];
                this.showFieldError(input, error);
            }
        });
    }
    
    clearErrors() {
        this.errors = {};
        this.displayErrors();
        
        const inputs = this.form.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            this.clearFieldError(input);
            this.updateFieldState(input, true);
        });
    }
    
    getFormData() {
        const formData = new FormData(this.form);
        const data = {};
        
        for (const [key, value] of formData.entries()) {
            if (data[key]) {
                // Handle multiple values (e.g., checkboxes)
                if (Array.isArray(data[key])) {
                    data[key].push(value);
                } else {
                    data[key] = [data[key], value];
                }
            } else {
                data[key] = value;
            }
        }
        
        return data;
    }
}

// Export for use in other files
window.FormValidator = FormValidator;

