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
  const slotsContainer = formWrap.querySelector('[data-yclients-slots]');

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
    renderQuickSlots([]);
    setMessage('');
  };

  const loadDates = async (serviceId, staffId) => {
    setMessage(strings.loading || 'Loading');
    const payload = await fetchJson('/available-dates', { service_id: serviceId, staff_id: staffId });
    const dates = normalizeList(payload).map((item) => item.date || item).filter(Boolean);
    const dateOptions = dates.map((date) => ({ id: date, title: date }));
    setOptions(dateSelect, dateOptions, strings.select_date || 'Select');
    setOptions(timeSelect, [], strings.select_time || 'Select');
    renderQuickSlots(dates.slice(0, 2));
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

  const renderQuickSlots = async (dates) => {
    if (!slotsContainer) {
      return;
    }
    slotsContainer.innerHTML = '';
    if (!dates.length || !serviceSelect.value || !staffSelect.value) {
      return;
    }

    for (const date of dates) {
      try {
        const payload = await fetchJson('/available-times', {
          service_id: serviceSelect.value,
          staff_id: staffSelect.value,
          date,
        });
        const times = normalizeList(payload).map((item) => item.time || item).filter(Boolean);
        if (!times.length) {
          continue;
        }
        const dateGroup = document.createElement('div');
        dateGroup.className = 'yclients-slots-group';
        const heading = document.createElement('h4');
        heading.textContent = date;
        dateGroup.appendChild(heading);
        const list = document.createElement('div');
        list.className = 'yclients-slots-times';
        times.forEach((time) => {
          const slot = document.createElement('div');
          slot.className = 'yclients-slot';
          const label = document.createElement('span');
          label.textContent = time;
          const button = document.createElement('button');
          button.type = 'button';
          button.className = 'yclients-slot-button';
          button.textContent = strings.submit || 'Book now';
          button.addEventListener('click', () => {
            dateSelect.value = date;
            timeSelect.value = time;
            if (!form.reportValidity()) {
              setMessage(strings.error || 'Error', 'error');
              return;
            }
            handleSubmit();
          });
          slot.appendChild(label);
          slot.appendChild(button);
          list.appendChild(slot);
        });
        dateGroup.appendChild(list);
        slotsContainer.appendChild(dateGroup);
      } catch (error) {
        setMessage(error.message, 'error');
      }
    }
  };

  const handleSubmit = async () => {
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
      renderQuickSlots([]);
    } catch (error) {
      setMessage(error.message, 'error');
    }
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
    await handleSubmit();
  });

  loadServices().catch((error) => {
    setMessage(error.message, 'error');
  });
})();
