(() => {
  const data = window.YclientsBookingWpData || {};
  const restUrl = data.restUrl || '';
  const nonce = data.nonce || '';
  const strings = data.strings || {};

  const formWrap = document.querySelector('[data-yclients-booking-form]');
  if (!formWrap) {
    return;
  }

  const form = formWrap.querySelector('form');
  const messageEl = formWrap.querySelector('.yclients-message');

  const serviceSelect = form.querySelector('#yclients-service');
  const staffSelect = form.querySelector('#yclients-staff');
  const dateSelect = form.querySelector('#yclients-date');
  const timeSelect = form.querySelector('#yclients-time');

  const setMessage = (text, type = 'info') => {
    messageEl.textContent = text;
    messageEl.className = `yclients-message yclients-message-${type}`;
  };

  const normalizeList = (payload) => {
    if (Array.isArray(payload)) {
      return payload;
    }
    if (payload && Array.isArray(payload.data)) {
      return payload.data;
    }
    return [];
  };

  const fetchJson = async (endpoint, params = {}) => {
    const url = new URL(restUrl + endpoint, window.location.origin);
    Object.keys(params).forEach((key) => {
      if (params[key] !== undefined && params[key] !== null && params[key] !== '') {
        url.searchParams.set(key, params[key]);
      }
    });

    const response = await fetch(url.toString(), {
      method: 'GET',
      headers: {
        'X-WP-Nonce': nonce,
      },
    });

    const json = await response.json();
    if (!response.ok) {
      throw new Error(json.message || strings.error || 'Error');
    }

    return json;
  };

  const postJson = async (endpoint, payload) => {
    const response = await fetch(restUrl + endpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': nonce,
      },
      body: JSON.stringify(payload),
    });

    const json = await response.json();
    if (!response.ok) {
      throw new Error(json.message || strings.error || 'Error');
    }

    return json;
  };

  const setOptions = (select, items, placeholder) => {
    select.innerHTML = '';
    const emptyOption = document.createElement('option');
    emptyOption.value = '';
    emptyOption.textContent = placeholder;
    select.appendChild(emptyOption);

    items.forEach((item) => {
      const option = document.createElement('option');
      option.value = item.id || item.staff_id || item.service_id || '';
      option.textContent = item.title || item.name || item.label || '';
      select.appendChild(option);
    });
  };

  const loadServices = async () => {
    setMessage(strings.loading || 'Loading');
    const payload = await fetchJson('/services');
    const services = normalizeList(payload);
    setOptions(serviceSelect, services, strings.select_service || 'Select');
    setMessage('');
  };

  const loadStaff = async (serviceId) => {
    setMessage(strings.loading || 'Loading');
    const payload = await fetchJson('/staff', { service_id: serviceId });
    const staff = normalizeList(payload);
    setOptions(staffSelect, staff, strings.select_staff || 'Select');
    setOptions(dateSelect, [], strings.select_date || 'Select');
    setOptions(timeSelect, [], strings.select_time || 'Select');
    setMessage('');
  };

  const loadDates = async (serviceId, staffId) => {
    setMessage(strings.loading || 'Loading');
    const payload = await fetchJson('/available-dates', { service_id: serviceId, staff_id: staffId });
    const dates = normalizeList(payload).map((item) => ({
      id: item.date || item,
      title: item.date || item,
    }));
    setOptions(dateSelect, dates, strings.select_date || 'Select');
    setOptions(timeSelect, [], strings.select_time || 'Select');
    setMessage('');
  };

  const loadTimes = async (serviceId, staffId, date) => {
    setMessage(strings.loading || 'Loading');
    const payload = await fetchJson('/available-times', { service_id: serviceId, staff_id: staffId, date });
    const times = normalizeList(payload).map((item) => ({
      id: item.time || item,
      title: item.time || item,
    }));
    setOptions(timeSelect, times, strings.select_time || 'Select');
    setMessage('');
  };

  serviceSelect.addEventListener('change', async (event) => {
    if (!event.target.value) {
      return;
    }
    try {
      await loadStaff(event.target.value);
    } catch (error) {
      setMessage(error.message, 'error');
    }
  });

  staffSelect.addEventListener('change', async (event) => {
    if (!event.target.value || !serviceSelect.value) {
      return;
    }
    try {
      await loadDates(serviceSelect.value, event.target.value);
    } catch (error) {
      setMessage(error.message, 'error');
    }
  });

  dateSelect.addEventListener('change', async (event) => {
    if (!event.target.value || !serviceSelect.value || !staffSelect.value) {
      return;
    }
    try {
      await loadTimes(serviceSelect.value, staffSelect.value, event.target.value);
    } catch (error) {
      setMessage(error.message, 'error');
    }
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    setMessage(strings.loading || 'Loading');

    const formData = new FormData(form);
    const payload = Object.fromEntries(formData.entries());
    payload.consent = formData.get('consent') === 'on';

    try {
      const response = await postJson('/book', payload);
      const bookingId = response.id || response.record_id || response.data?.id || '';
      const summary = bookingId ? `${strings.success || 'Success'} #${bookingId}` : strings.success || 'Success';
      setMessage(summary, 'success');
      form.reset();
    } catch (error) {
      setMessage(error.message, 'error');
    }
  });

  loadServices().catch((error) => {
    setMessage(error.message, 'error');
  });
})();
