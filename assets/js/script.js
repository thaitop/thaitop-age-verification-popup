/**
 * ThaiTop Age Verification Popup
 * https://thaitoptecs.com/plugins/thaitop-age-verification-popup
 *
 * Copyright (c) 2024 ThaiTop
 * Licensed under the GPL v2 or later license.
 */
jQuery(document).ready(function($) {
    // Show popup when page loads
    if ($('#age-verification-popup').length) {
        $('#age-verification-popup').show();
    }

    // Handle age verification
    $('#verify-age-btn').on('click', function(e) {
        e.preventDefault();
        
        var birthDay = $('#birth-day').val();
        var birthMonth = $('#birth-month').val();
        var birthYear = $('#birth-year').val();
        var minimumAge = $('#minimum-age').val();
        var calculationType = $('#calculation-type').val();
        var referenceDate = calculationType === 'custom' ? ($('#reference-date').val() || '') : '';

        // Validate all fields are filled
        if (!birthDay || !birthMonth || !birthYear) {
            showError('Please select your complete date of birth.');
            return;
        }

        // Send AJAX request
        $.ajax({
            url: thaitopAgeVerification.ajaxurl,
            type: 'POST',
            data: {
                action: 'verify_age',
                birth_day: birthDay,
                birth_month: birthMonth,
                birth_year: birthYear,
                minimum_age: minimumAge,
                calculation_type: calculationType,
                reference_date: referenceDate,
                nonce: thaitopAgeVerification.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#age-verification-popup').fadeOut();
                    // Store verification in session with calculation type and reference date if applicable
                    var storageKey = 'age_verified_' + minimumAge + '_' + calculationType + (referenceDate ? '_' + referenceDate : '');
                    sessionStorage.setItem(storageKey, 'true');
                } else {
                    showError(response.data);
                }
            },
            error: function() {
                showError('An error occurred. Please try again.');
            }
        });
    });

    // Show error message
    function showError(message) {
        var errorDiv = $('.error-message');
        if (!errorDiv.length) {
            errorDiv = $('<div class="error-message"></div>');
            $('.popup-content').append(errorDiv);
        }
        errorDiv.html(message).addClass('show');
    }

    // Clear error message
    function clearError() {
        $('.error-message').removeClass('show');
    }

    // Prevent form submission on enter key
    $('.birthday-selector select').on('keypress', function(e) {
        return e.which !== 13;
    });

    // Auto-focus next select on change
    $('.birthday-selector select').on('change', function() {
        clearError();
        var next = $(this).next('select');
        if (next.length) {
            next.focus();
        }
    });

    // Check if age was already verified for this product
    var minimumAge = $('#minimum-age').val();
    var calculationType = $('#calculation-type').val();
    var referenceDate = calculationType === 'custom' ? ($('#reference-date').val() || '') : '';
    var storageKey = 'age_verified_' + minimumAge + '_' + calculationType + (referenceDate ? '_' + referenceDate : '');
    if (sessionStorage.getItem(storageKey) === 'true') {
        $('#age-verification-popup').hide();
    }
}); 