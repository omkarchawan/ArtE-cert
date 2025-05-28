document.addEventListener('DOMContentLoaded', function() {
    // DOM Elements
    const phoneStep = document.getElementById('phone-step');
    const otpStep = document.getElementById('otp-step');
    const successStep = document.getElementById('success-step');
    const countryCodeSelect = document.getElementById('country-code');
    const phoneNumberInput = document.getElementById('phone-number');
    const otpSentToElement = document.getElementById('otp-sent-to');
    const sendOtpBtn = document.getElementById('send-otp-btn');
    const verifyOtpBtn = document.getElementById('verify-otp-btn');
    const resendOtpBtn = document.getElementById('resend-otp-btn');
    const changeNumberBtn = document.getElementById('change-number-btn');
    const continueBtn = document.getElementById('continue-btn');
    const phoneErrorElement = document.getElementById('phone-error');
    const otpErrorElement = document.getElementById('otp-error');
    const otpInputs = document.querySelectorAll('.otp-digit');
    const recaptchaContainer = document.getElementById('recaptcha-container');

    // Firebase Auth Variables
    let appVerifier;
    let confirmationResult;
    let timer;
    let resendTimeout = 30; // seconds

    // Initialize reCAPTCHA
    function initializeRecaptcha() {
        appVerifier = new firebase.auth.RecaptchaVerifier('recaptcha-container', {
            'size': 'normal',
            'callback': (response) => {
                sendOtpBtn.disabled = false;
            },
            'expired-callback': () => {
                sendOtpBtn.disabled = true;
                grecaptcha.reset(window.recaptchaWidgetId);
            }
        });
    }

    initializeRecaptcha();

    // Format phone number for display
    function formatPhoneNumber(phoneNumber) {
        const cleaned = phoneNumber.replace(/\D/g, '');
        const match = cleaned.match(/^(\d{3})(\d{3})(\d{4})$/);
        if (match) {
            return `${match[1]} ${match[2]} ${match[3]}`;
        }
        return phoneNumber;
    }

    // Handle OTP input auto-focus
    function handleOtpInput(e) {
        const input = e.target;
        const nextInput = input.nextElementSibling;
        const prevInput = input.previousElementSibling;

        if (input.value.length > 1) {
            input.value = input.value.slice(0, 1);
        }

        if (input.value && nextInput) {
            nextInput.focus();
        }

        if (e.key === 'Backspace' && !input.value && prevInput) {
            prevInput.focus();
        }
    }

    // Get full OTP code
    function getOtpCode() {
        return Array.from(otpInputs).map(input => input.value).join('');
    }

    // Start resend OTP timer
    function startResendTimer() {
        resendOtpBtn.disabled = true;
        let seconds = resendTimeout;
        
        timer = setInterval(() => {
            resendOtpBtn.textContent = `Resend Code (${seconds}s)`;
            seconds--;
            
            if (seconds < 0) {
                clearInterval(timer);
                resendOtpBtn.textContent = 'Resend Code';
                resendOtpBtn.disabled = false;
            }
        }, 1000);
    }

    // Show loading state
    function showLoading(button) {
        button.disabled = true;
        button.querySelector('.btn-text').textContent = button.id.includes('send') ? 'Sending...' : 'Verifying...';
        button.querySelector('.spinner').classList.remove('hidden');
    }

    // Hide loading state
    function hideLoading(button, originalText) {
        button.disabled = false;
        button.querySelector('.btn-text').textContent = originalText;
        button.querySelector('.spinner').classList.add('hidden');
    }

    // Switch between steps
    function goToStep(step) {
        document.querySelector('.step.active').classList.remove('active');
        document.getElementById(step).classList.add('active');
    }

    // Handle successful login
    function handleLoginSuccess() {
        localStorage.setItem('artecertLoggedIn', 'true');
        window.location.href = "../index.html?login=success"; // Redirect to main page
    }

    // Send OTP
    sendOtpBtn.addEventListener('click', async () => {
        const countryCode = countryCodeSelect.value;
        const phoneNumber = phoneNumberInput.value.trim();
        const fullPhoneNumber = countryCode + phoneNumber;

        phoneErrorElement.textContent = '';

        if (!phoneNumber) {
            phoneErrorElement.textContent = 'Please enter your phone number';
            return;
        }

        showLoading(sendOtpBtn);

        try {
            confirmationResult = await firebase.auth().signInWithPhoneNumber(fullPhoneNumber, appVerifier);
            
            otpSentToElement.textContent = `Sent to ${countryCode} ${formatPhoneNumber(phoneNumber)}`;
            goToStep('otp-step');
            startResendTimer();
            
            otpInputs[0].focus();
        } catch (error) {
            console.error('Error sending OTP:', error);
            phoneErrorElement.textContent = getErrorMessage(error);
            appVerifier.clear();
            initializeRecaptcha();
        } finally {
            hideLoading(sendOtpBtn, 'Send Verification Code');
        }
    });

    // Verify OTP
    verifyOtpBtn.addEventListener('click', async () => {
        const otpCode = getOtpCode();
        otpErrorElement.textContent = '';

        if (otpCode.length !== 6) {
            otpErrorElement.textContent = 'Please enter the 6-digit code';
            return;
        }

        showLoading(verifyOtpBtn);

        try {
            await confirmationResult.confirm(otpCode);
            handleLoginSuccess(); // Login success handling
        } catch (error) {
            console.error('Error verifying OTP:', error);
            otpErrorElement.textContent = getErrorMessage(error);
        } finally {
            hideLoading(verifyOtpBtn, 'Verify Code');
        }
    });

    // Resend OTP
    resendOtpBtn.addEventListener('click', async () => {
        const fullPhoneNumber = countryCodeSelect.value + phoneNumberInput.value.trim();
        
        showLoading(resendOtpBtn);
        resendOtpBtn.textContent = 'Sending...';

        try {
            confirmationResult = await firebase.auth().signInWithPhoneNumber(fullPhoneNumber, appVerifier);
            startResendTimer();
            otpErrorElement.textContent = '';
            otpInputs.forEach(input => input.value = '');
            otpInputs[0].focus();
        } catch (error) {
            console.error('Error resending OTP:', error);
            otpErrorElement.textContent = getErrorMessage(error);
        } finally {
            hideLoading(resendOtpBtn, 'Resend Code');
        }
    });

    // Change phone number
    changeNumberBtn.addEventListener('click', () => {
        goToStep('phone-step');
        clearInterval(timer);
        appVerifier.clear();
        initializeRecaptcha();
    });

    // Continue after success
    continueBtn.addEventListener('click', () => {
        alert('Authentication successful! Redirecting...');
    });

    // OTP input handling
    otpInputs.forEach(input => {
        input.addEventListener('input', handleOtpInput);
        input.addEventListener('keydown', handleOtpInput);
    });

    // Error message helper
    function getErrorMessage(error) {
        switch (error.code) {
            case 'auth/invalid-phone-number':
                return 'Invalid phone number format';
            case 'auth/missing-phone-number':
                return 'Please enter a phone number';
            case 'auth/captcha-check-failed':
                return 'Security check failed. Please try again';
            case 'auth/invalid-verification-code':
                return 'Invalid verification code';
            case 'auth/code-expired':
                return 'Code expired. Please request a new one';
            case 'auth/too-many-requests':
                return 'Too many attempts. Please try again later';
            default:
                return 'An error occurred. Please try again';
        }
    }
});
