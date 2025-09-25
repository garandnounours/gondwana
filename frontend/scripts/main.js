// Configuration
const API_BASE_URL = 'http://localhost:8000';

// DOM Elements
const bookingForm = document.getElementById('bookingForm');
const occupantsInput = document.getElementById('occupants');
const agesContainer = document.getElementById('agesContainer');
const loadingSpinner = document.getElementById('loadingSpinner');
const resultsSection = document.getElementById('resultsSection');
const resultsContainer = document.getElementById('resultsContainer');
const errorSection = document.getElementById('errorSection');
const errorText = document.getElementById('errorText');

// Initialize the application
document.addEventListener('DOMContentLoaded', function() {
    initializeForm();
    setupEventListeners();
    setupDateValidation();
});

function initializeForm() {
    // Set default dates (today + 7 days for arrival, +14 days for departure)
    const today = new Date();
    const arrivalDate = new Date(today);
    arrivalDate.setDate(today.getDate() + 7);
    
    const departureDate = new Date(today);
    departureDate.setDate(today.getDate() + 14);
    
    document.getElementById('arrival').value = formatDateForInput(arrivalDate);
    document.getElementById('departure').value = formatDateForInput(departureDate);
}

function setupEventListeners() {
    // Form submission
    bookingForm.addEventListener('submit', handleFormSubmit);
    
    // Dynamic age inputs based on occupants
    occupantsInput.addEventListener('change', updateAgeInputs);
    
    // Date validation
    document.getElementById('arrival').addEventListener('change', validateDates);
    document.getElementById('departure').addEventListener('change', validateDates);
}

function setupDateValidation() {
    const arrivalInput = document.getElementById('arrival');
    const departureInput = document.getElementById('departure');
    
    // Set minimum date to today
    const today = new Date();
    const minDate = formatDateForInput(today);
    
    arrivalInput.setAttribute('min', minDate);
    departureInput.setAttribute('min', minDate);
}

function handleFormSubmit(event) {
    event.preventDefault();
    
    // Clear previous results and errors
    clearResults();
    clearError();
    
    // Validate form
    if (!validateForm()) {
        return;
    }
    
    // Collect form data
    const formData = collectFormData();
    
    // Show loading spinner
    showLoading();
    
    // Make API request
    fetchRates(formData);
}

function validateForm() {
    const form = bookingForm;
    
    // Basic HTML5 validation
    if (!form.checkValidity()) {
        form.reportValidity();
        return false;
    }
    
    // Custom validations
    const arrival = new Date(convertDateFormat(document.getElementById('arrival').value, 'dd/mm/yyyy', 'yyyy-mm-dd'));
    const departure = new Date(convertDateFormat(document.getElementById('departure').value, 'dd/mm/yyyy', 'yyyy-mm-dd'));
    
    if (departure <= arrival) {
        showError('Departure date must be after arrival date');
        return false;
    }
    
    // Validate ages
    const ageInputs = document.querySelectorAll('.age-input');
    for (let input of ageInputs) {
        if (!input.value || input.value < 0 || input.value > 120) {
            showError('Please enter valid ages (0-120) for all occupants');
            return false;
        }
    }
    
    return true;
}

function collectFormData() {
    const ageInputs = document.querySelectorAll('.age-input');
    const ages = Array.from(ageInputs).map(input => parseInt(input.value));
    
    return {
        "Unit Name": document.getElementById('unitName').value,
        "Arrival": document.getElementById('arrival').value,
        "Departure": document.getElementById('departure').value,
        "Occupants": parseInt(document.getElementById('occupants').value),
        "Ages": ages
    };
}

async function fetchRates(formData) {
    try {
        const response = await fetch(`${API_BASE_URL}/api/rates`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        
        hideLoading();
        
        if (data.success) {
            displayResults(data.data);
        } else {
            showError(data.message || 'Failed to fetch rates');
        }
        
    } catch (error) {
        hideLoading();
        console.error('API Error:', error);
        showError('Unable to connect to the server. Please check your connection and try again.');
    }
}

function displayResults(results) {
    if (!results || results.length === 0) {
        showError('No rates found for your search criteria');
        return;
    }
    
    resultsContainer.innerHTML = '';
    
    results.forEach(result => {
        const rateCard = createRateCard(result);
        resultsContainer.appendChild(rateCard);
    });
    
    resultsSection.classList.remove('hidden');
    
    // Smooth scroll to results
    resultsSection.scrollIntoView({ behavior: 'smooth' });
}

function createRateCard(result) {
    const card = document.createElement('div');
    card.className = `rate-card ${result.availability ? 'available' : 'unavailable'}`;
    
    const price = result.rate ? `$${parseFloat(result.rate).toFixed(2)}` : 'N/A';
    const availabilityClass = result.availability ? 'available' : 'unavailable';
    const availabilityText = result.availability ? 'Available' : 'Unavailable';
    const availabilityIcon = result.availability ? 'check-circle' : 'times-circle';
    
    card.innerHTML = `
        <div class="rate-header">
            <h3 class="rate-title">${escapeHtml(result.unitName)}</h3>
            <div class="rate-price">${price}</div>
        </div>
        
        <div class="availability-badge ${availabilityClass}">
            <i class="fas fa-${availabilityIcon}"></i>
            ${availabilityText}
        </div>
        
        <div class="rate-details">
            <div class="rate-detail">
                <i class="fas fa-calendar-alt"></i>
                <span>${result.dateRange}</span>
            </div>
            
            <div class="rate-detail">
                <i class="fas fa-users"></i>
                <span>${result.occupants} occupant${result.occupants !== 1 ? 's' : ''}</span>
            </div>
            
            <div class="rate-detail">
                <i class="fas fa-hashtag"></i>
                <span>Unit Type ID: ${result.unitTypeId}</span>
            </div>
        </div>
        
        ${result.error ? `
            <div class="rate-detail" style="color: #dc3545; margin-top: 15px;">
                <i class="fas fa-exclamation-triangle"></i>
                <span>${escapeHtml(result.error)}</span>
            </div>
        ` : ''}
    `;
    
    return card;
}

function updateAgeInputs() {
    const occupants = parseInt(occupantsInput.value) || 0;
    const currentInputs = agesContainer.querySelectorAll('.age-input');
    
    // Remove excess inputs
    while (currentInputs.length > occupants) {
        currentInputs[currentInputs.length - 1].remove();
        currentInputs.length--;
    }
    
    // Add missing inputs
    for (let i = currentInputs.length; i < occupants; i++) {
        const input = document.createElement('input');
        input.type = 'number';
        input.className = 'age-input';
        input.min = '0';
        input.max = '120';
        input.placeholder = `Age ${i + 1}`;
        input.required = true;
        agesContainer.appendChild(input);
    }
}

function validateDates() {
    const arrivalValue = document.getElementById('arrival').value;
    const departureValue = document.getElementById('departure').value;
    
    if (arrivalValue && departureValue) {
        const arrival = new Date(convertDateFormat(arrivalValue, 'dd/mm/yyyy', 'yyyy-mm-dd'));
        const departure = new Date(convertDateFormat(departureValue, 'dd/mm/yyyy', 'yyyy-mm-dd'));
        
        if (departure <= arrival) {
            document.getElementById('departure').setCustomValidity('Departure date must be after arrival date');
        } else {
            document.getElementById('departure').setCustomValidity('');
        }
    }
}

function showLoading() {
    loadingSpinner.classList.remove('hidden');
    resultsSection.classList.add('hidden');
    errorSection.classList.add('hidden');
}

function hideLoading() {
    loadingSpinner.classList.add('hidden');
}

function showError(message) {
    errorText.textContent = message;
    errorSection.classList.remove('hidden');
    resultsSection.classList.add('hidden');
    
    // Smooth scroll to error
    errorSection.scrollIntoView({ behavior: 'smooth' });
}

function clearError() {
    errorSection.classList.add('hidden');
}

function clearResults() {
    resultsSection.classList.add('hidden');
    resultsContainer.innerHTML = '';
}

// Utility functions
function formatDateForInput(date) {
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    return `${day}/${month}/${year}`;
}

function convertDateFormat(dateString, fromFormat, toFormat) {
    if (fromFormat === 'dd/mm/yyyy' && toFormat === 'yyyy-mm-dd') {
        const [day, month, year] = dateString.split('/');
        return `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
    }
    return dateString;
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

// Global function for error section button
window.clearError = clearError;
