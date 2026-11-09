const availabilityForm = document.querySelector('#availability-form');
const dateInput = document.querySelector('#date');
const availabilityStatus = document.querySelector('#availability-status');
const slotList = document.querySelector('#mechanic-slots');
const bookingForm = document.querySelector('#booking-form');
const bookingDate = document.querySelector('#booking-date');
const mechanicSelect = document.querySelector('#mechanic');
const bookButton = bookingForm.querySelector('button[type="submit"]');
let activeRequest;

async function refreshAvailability() {
    activeRequest?.abort();
    mechanicSelect.disabled = true;
    bookButton.disabled = true;

    if (!dateInput.validity.valid) {
        availabilityStatus.textContent = 'Choose an appointment date.';
        return;
    }

    const request = new AbortController();
    activeRequest = request;
    availabilityStatus.textContent = 'Checking availability…';
    const url = new URL(location.href);
    url.searchParams.set('date', dateInput.value);
    url.searchParams.set('availability', '1');

    try {
        const response = await fetch(url, { signal: request.signal });
        if (!response.ok) throw new Error('Availability request failed');
        const { date, mechanics } = await response.json();
        if (request.signal.aborted) return;

        const selectedMechanic = mechanicSelect.value;
        const options = [new Option('Choose a mechanic', '')];
        const slots = mechanics.map(({ id, name, free_slots }) => {
            const row = document.createElement('li');
            const label = document.createElement('strong');
            const count = document.createElement('span');
            label.textContent = name;
            count.textContent = `${free_slots} of 4 slots available`;
            row.append(label, count);

            if (Number(free_slots) > 0) {
                options.push(new Option(`${name} (${free_slots} slots left)`, id));
            }
            return row;
        });
        slotList.replaceChildren(...slots);
        mechanicSelect.replaceChildren(...options);
        mechanicSelect.value = options.some(option => option.value === selectedMechanic) ? selectedMechanic : '';
        bookingForm.elements.namedItem('date').value = date;
        bookingDate.textContent = date;

        const hasAvailability = options.length > 1;
        mechanicSelect.disabled = !hasAvailability;
        bookButton.disabled = !hasAvailability;
        availabilityStatus.textContent = hasAvailability
            ? 'Availability updated.'
            : 'No mechanics are available on this date. Choose another date.';
    } catch (error) {
        if (!request.signal.aborted) {
            availabilityStatus.textContent = 'Could not check availability. Try the Check availability button again.';
        }
    }
}

dateInput.addEventListener('change', refreshAvailability);
availabilityForm.addEventListener('submit', event => {
    event.preventDefault();
    refreshAvailability();
});
