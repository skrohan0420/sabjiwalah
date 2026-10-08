(function () {
  const savedPhoneStorageKey = 'sabjiwalah.loginPhone';
  const savedProfileStorageKey = 'sabjiwalah.loginProfile';
  let isVerifyingOtp = false;

  function digitsOnly(value, maxLength = 10) {
    const digits = String(value || '').replace(/\D/g, '');

    if (maxLength === 10 && digits.length > 10) {
      return digits.slice(-10);
    }

    return digits.slice(0, maxLength);
  }

  function maskPhone(phone) {
    const digits = digitsOnly(phone);

    if (digits.length < 4) {
      return digits;
    }

    return `${digits.slice(0, 2)}${'*'.repeat(Math.max(0, digits.length - 4))}${digits.slice(-2)}`;
  }

  function cleanName(name) {
    return String(name || '').trim().replace(/\s+/g, ' ').slice(0, 40);
  }

  function getSavedPhone() {
    try {
      return getSavedProfile().phone;
    } catch (error) {
      return '';
    }
  }

  function getSavedProfile() {
    try {
      const savedProfile = JSON.parse(window.localStorage.getItem(savedProfileStorageKey) || '{}');
      const phone = digitsOnly(savedProfile.phone || window.localStorage.getItem(savedPhoneStorageKey));

      return {
        phone,
        name: cleanName(savedProfile.name),
      };
    } catch (error) {
      return {
        phone: digitsOnly(window.localStorage.getItem(savedPhoneStorageKey)),
        name: '',
      };
    }
  }

  function saveProfile(phone, name = '') {
    const digits = digitsOnly(phone);
    const savedProfile = getSavedProfile();
    const displayName = cleanName(name) || (savedProfile.phone === digits ? savedProfile.name : '');

    if (!digits) {
      return;
    }

    try {
      window.localStorage.setItem(savedPhoneStorageKey, digits);
      window.localStorage.setItem(savedProfileStorageKey, JSON.stringify({
        phone: digits,
        name: displayName,
      }));
    } catch (error) {
      // Local storage is only used to make repeat login faster.
    }
  }

  function renderSavedProfile(savedPhoneNode, profile) {
    if (!savedPhoneNode || !profile.phone) {
      return;
    }

    const title = document.createElement('strong');
    const phone = document.createElement('span');

    title.textContent = profile.name ? `Welcome back, ${profile.name}` : 'Welcome back';
    phone.textContent = maskPhone(profile.phone);

    savedPhoneNode.replaceChildren(title, phone);
    savedPhoneNode.hidden = false;
  }

  function hydrateSavedPhone() {
    const input = document.querySelector('[data-auth-phone]');
    const savedProfile = getSavedProfile();
    const savedPhoneNode = document.querySelector('[data-auth-saved-phone]');

    if (!input) {
      return;
    }

    input.value = digitsOnly(input.value || savedProfile.phone);

    renderSavedProfile(savedPhoneNode, savedProfile);
  }

  async function getCsrf() {
    const response = await fetch(window.Sabjiwalah.url('/api/v1/csrf'), {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    });
    const payload = await response.json();

    if (!payload.success) {
      throw new Error(payload.message || 'Unable to get CSRF token');
    }

    return payload.data;
  }

  async function api(path, body) {
    const csrf = await getCsrf();
    const response = await fetch(window.Sabjiwalah.url(path), {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        [csrf.header_name]: csrf.token_value,
      },
      credentials: 'same-origin',
      body: JSON.stringify(body),
    });
    const payload = await response.json();

    if (!response.ok || !payload.success) {
      throw new Error(payload.message || 'Request failed');
    }

    return payload;
  }

  function messageTone(message, isError) {
    if (isError) {
      return 'error';
    }

    if (/sending|verifying/i.test(message || '')) {
      return 'info';
    }

    return 'success';
  }

  function setMessage(form, message, isError = false) {
    const node = form.closest('.auth-card')?.querySelector('[data-auth-message]');
    if (!node) {
      return;
    }

    node.textContent = message || '';
    node.classList.remove('is-error', 'is-success', 'is-info', 'is-visible');

    if (!message) {
      return;
    }

    node.classList.add(`is-${messageTone(message, isError)}`, 'is-visible');
  }

  function showOtp(form, code) {
    if (!form) {
      return;
    }

    const node = form.querySelector('[data-auth-dev-otp]');
    if (!node) {
      return;
    }

    node.hidden = !code;
    node.textContent = code ? `Testing OTP: ${code}` : '';
  }

  function otpDigitInputs(form) {
    return Array.from(form?.querySelectorAll('[data-auth-otp-digit]') || []);
  }

  function syncOtpFromDigits(form) {
    const otpInput = form?.querySelector('[data-auth-otp]');

    if (!otpInput) {
      return '';
    }

    const code = otpDigitInputs(form).map((input) => digitsOnly(input.value, 1)).join('');
    otpInput.value = digitsOnly(code, 6);

    return otpInput.value;
  }

  function setOtpDigits(form, code, startIndex = 0) {
    const digits = digitsOnly(code, 6);
    const inputs = otpDigitInputs(form);

    inputs.forEach((input, index) => {
      if (index >= startIndex && index < startIndex + digits.length) {
        input.value = digits[index - startIndex] || '';
      } else if (startIndex === 0) {
        input.value = '';
      }
    });

    syncOtpFromDigits(form);

    const nextIndex = Math.min(startIndex + digits.length, inputs.length - 1);
    inputs[nextIndex]?.focus();
    inputs[nextIndex]?.select();
  }

  function resetOtpDigits(form) {
    otpDigitInputs(form).forEach((input) => {
      input.value = '';
      input.disabled = false;
    });

    syncOtpFromDigits(form);
    isVerifyingOtp = false;
  }

  async function verifyOtp(form) {
    if (!form || isVerifyingOtp) {
      return;
    }

    const phoneInput = form.querySelector('[data-auth-phone]');
    const otpInput = form.querySelector('[data-auth-otp]');
    const phone = digitsOnly(phoneInput?.value);
    const otp = syncOtpFromDigits(form) || digitsOnly(otpInput?.value, 6);
    const redirect = form.querySelector('[data-auth-redirect]')?.value || '';

    if (phoneInput) {
      phoneInput.value = phone;
    }

    if (otpInput) {
      otpInput.value = otp;
    }

    if (otp.length !== 6) {
      setMessage(form, 'Enter the 6 digit OTP.', true);
      otpDigitInputs(form).find((input) => !input.value)?.focus();
      return;
    }

    isVerifyingOtp = true;
    otpDigitInputs(form).forEach((input) => {
      input.disabled = true;
    });
    setMessage(form, 'Verifying OTP...');

    try {
      saveProfile(phone);
      const payload = await api('/api/v1/auth/otp/verify', { phone, otp });
      saveProfile(phone, payload.data.user?.name);
      window.location.href = window.Sabjiwalah.url(targetFor(payload.data.user, redirect));
    } catch (error) {
      isVerifyingOtp = false;
      otpDigitInputs(form).forEach((input) => {
        input.disabled = false;
      });
      resetOtpDigits(form);
      setMessage(form, error.message, true);
    }
  }

  function verifyWhenOtpComplete(form) {
    if (syncOtpFromDigits(form).length === 6) {
      verifyOtp(form);
    }
  }

  function showOtpPanel(form, isVisible, focusPhone = true) {
    if (!form) {
      return;
    }

    const panel = form.querySelector('[data-auth-otp-panel]');
    const otpInput = form.querySelector('[data-auth-otp]');
    const phoneInput = form.querySelector('[data-auth-phone]');
    const otpPhone = form.querySelector('[data-auth-otp-phone]');

    if (!panel) {
      return;
    }

    if (!isVisible) {
      isVerifyingOtp = false;
    }

    form.classList.toggle('is-verifying', isVisible);
    panel.hidden = !isVisible;

    if (otpPhone && phoneInput) {
      otpPhone.textContent = `+91 ${maskPhone(phoneInput.value)}`;
    }

    if (otpInput) {
      otpInput.required = isVisible;

      if (!isVisible) {
        otpInput.value = '';
        resetOtpDigits(form);
      }
    }

    if (isVisible) {
      otpDigitInputs(form)[0]?.focus();
    } else if (focusPhone) {
      phoneInput?.focus();
      phoneInput?.select();
    }
  }

  function targetFor(user, redirect) {
    if (user.role === 'admin') {
      return '/admin';
    }

    if (user.role === 'delivery') {
      return '/delivery';
    }

    return redirect || '/account';
  }

  document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-send-auth-otp]');
    const changePhoneButton = event.target.closest('[data-change-auth-phone]');

    if (changePhoneButton) {
      const form = changePhoneButton.closest('[data-auth-form]');

      showOtpPanel(form, false);
      showOtp(form, '');
      setMessage(form, '');
      return;
    }

    if (!button) {
      return;
    }

    const form = button.closest('[data-auth-form]');
    const phoneInput = form.querySelector('[data-auth-phone]');
    const phone = digitsOnly(phoneInput?.value);

    if (phoneInput) {
      phoneInput.value = phone;
    }

    showOtp(form, '');
    setMessage(form, 'Sending OTP...');

    try {
      saveProfile(phone);
      const payload = await api('/api/v1/auth/otp/start', { phone });
      saveProfile(phone, payload.data.user_name);
      renderSavedProfile(document.querySelector('[data-auth-saved-phone]'), getSavedProfile());
      showOtp(form, payload.data.dev_otp);
      showOtpPanel(form, true);

      if (!payload.data.user_exists) {
        setMessage(form, 'New number. Verify the OTP and we will create your customer account.');
        return;
      }

      setMessage(form, 'OTP generated. Enter the testing OTP shown above.');
    } catch (error) {
      setMessage(form, error.message, true);
    }
  });

  document.addEventListener('submit', async (event) => {
    const form = event.target.closest('[data-auth-form]');
    if (!form) {
      return;
    }

    event.preventDefault();
    verifyOtp(form);
  });

  document.addEventListener('input', (event) => {
    const phoneInput = event.target.closest('[data-auth-phone]');
    const otpInput = event.target.closest('[data-auth-otp]');
    const otpDigitInput = event.target.closest('[data-auth-otp-digit]');

    if (phoneInput) {
      phoneInput.value = digitsOnly(phoneInput.value);
      const savedPhoneNode = document.querySelector('[data-auth-saved-phone]');
      const savedProfile = getSavedProfile();

      if (savedPhoneNode) {
        savedPhoneNode.hidden = phoneInput.value !== savedProfile.phone;
      }

      // Editing must retain the caret, rather than select and replace every digit.
      showOtpPanel(phoneInput.closest('[data-auth-form]'), false, false);
      showOtp(phoneInput.closest('[data-auth-form]'), '');
    }

    if (otpInput) {
      otpInput.value = digitsOnly(otpInput.value, 6);
    }

    if (otpDigitInput) {
      const form = otpDigitInput.closest('[data-auth-form]');
      const inputs = otpDigitInputs(form);
      const index = inputs.indexOf(otpDigitInput);
      const digits = digitsOnly(otpDigitInput.value, 6);

      if (digits.length > 1) {
        setOtpDigits(form, digits, digits.length === inputs.length ? 0 : index);
        verifyWhenOtpComplete(form);
        return;
      }

      const digit = digitsOnly(otpDigitInput.value, 1);

      otpDigitInput.value = digit;
      syncOtpFromDigits(form);

      if (digit && index >= 0 && index < inputs.length - 1) {
        inputs[index + 1].focus();
        inputs[index + 1].select();
      }

      verifyWhenOtpComplete(form);
    }
  });

  document.addEventListener('keydown', (event) => {
    const otpDigitInput = event.target.closest('[data-auth-otp-digit]');

    if (!otpDigitInput) {
      return;
    }

    const form = otpDigitInput.closest('[data-auth-form]');
    const inputs = otpDigitInputs(form);
    const index = inputs.indexOf(otpDigitInput);

    if (event.key === 'Backspace' && !otpDigitInput.value && index > 0) {
      event.preventDefault();
      inputs[index - 1].value = '';
      inputs[index - 1].focus();
      syncOtpFromDigits(form);
    }

    if (event.key === 'ArrowLeft' && index > 0) {
      event.preventDefault();
      inputs[index - 1].focus();
      inputs[index - 1].select();
    }

    if (event.key === 'ArrowRight' && index < inputs.length - 1) {
      event.preventDefault();
      inputs[index + 1].focus();
      inputs[index + 1].select();
    }
  });

  document.addEventListener('paste', (event) => {
    const otpDigitInput = event.target.closest('[data-auth-otp-digit]');

    if (!otpDigitInput) {
      return;
    }

    const code = digitsOnly(event.clipboardData?.getData('text'), 6);

    if (!code) {
      return;
    }

    event.preventDefault();

    const form = otpDigitInput.closest('[data-auth-form]');
    const inputs = otpDigitInputs(form);
    const currentIndex = Math.max(0, inputs.indexOf(otpDigitInput));
    const index = code.length === inputs.length ? 0 : currentIndex;

    setOtpDigits(form, code, index);
    verifyWhenOtpComplete(form);
  });

  hydrateSavedPhone();
})();
