@php
    /** @var \App\Models\Customer|null $customer */
    // The create form is rendered without $customer. Bind it to null up front:
    // undefined-variable warnings are exceptions here, so `$customer?->` alone
    // would fatal on create.
    $customer = $customer ?? null;
    $editing = $customer !== null;
    $address = old('address', $editing ? ($customer->primaryAddress?->toArray() ?? []) : []);
    $existingContacts = old('contacts', $editing ? $customer->contacts->toArray() : []);
    // Always render one blank row so a contact can be added without JavaScript.
    $contactRows = array_pad(array_values($existingContacts), max(1, count($existingContacts)), []);
@endphp

<h3 class="h6 text-navy border-bottom pb-2 mb-3">Personal information</h3>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <label for="first_name" class="form-label">First name <span class="text-danger">*</span></label>
        <input type="text" name="first_name" id="first_name"
               class="form-control @error('first_name') is-invalid @enderror"
               value="{{ old('first_name', $customer->first_name ?? '') }}" required>
        @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label for="middle_name" class="form-label">Middle name</label>
        <input type="text" name="middle_name" id="middle_name"
               class="form-control @error('middle_name') is-invalid @enderror"
               value="{{ old('middle_name', $customer->middle_name ?? '') }}">
        @error('middle_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label for="last_name" class="form-label">Last name <span class="text-danger">*</span></label>
        <input type="text" name="last_name" id="last_name"
               class="form-control @error('last_name') is-invalid @enderror"
               value="{{ old('last_name', $customer->last_name ?? '') }}" required>
        @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label for="suffix" class="form-label">Suffix</label>
        <input type="text" name="suffix" id="suffix"
               class="form-control @error('suffix') is-invalid @enderror"
               value="{{ old('suffix', $customer->suffix ?? '') }}" placeholder="Jr., Sr., III">
        @error('suffix')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label for="gender" class="form-label">Gender</label>
        <select name="gender" id="gender" class="form-select @error('gender') is-invalid @enderror">
            <option value="">Not specified</option>
            @foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $value => $label)
                <option value="{{ $value }}" @selected(old('gender', $customer->gender ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label for="date_of_birth" class="form-label">Date of birth</label>
        <input type="date" name="date_of_birth" id="date_of_birth"
               class="form-control @error('date_of_birth') is-invalid @enderror"
               value="{{ old('date_of_birth', $customer?->date_of_birth?->format('Y-m-d')) }}">
        @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label for="contact_number" class="form-label">Contact number <span class="text-danger">*</span></label>
        <input type="text" name="contact_number" id="contact_number"
               class="form-control @error('contact_number') is-invalid @enderror"
               value="{{ old('contact_number', $customer->contact_number ?? '') }}" required>
        @error('contact_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label for="alternate_contact_number" class="form-label">Alternative number</label>
        <input type="text" name="alternate_contact_number" id="alternate_contact_number"
               class="form-control @error('alternate_contact_number') is-invalid @enderror"
               value="{{ old('alternate_contact_number', $customer->alternate_contact_number ?? '') }}">
        @error('alternate_contact_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="email" class="form-label">Email address</label>
        <input type="email" name="email" id="email"
               class="form-control @error('email') is-invalid @enderror"
               value="{{ old('email', $customer->email ?? '') }}">
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="photo" class="form-label">Profile photo</label>
        <input type="file" name="photo" id="photo" accept="image/*"
               class="form-control @error('photo') is-invalid @enderror">
        @error('photo')<div class="invalid-feedback">{{ $message }}</div>@enderror
        @if ($editing && $customer->photo_path)
            <div class="form-text">A photo is already on file; uploading replaces it.</div>
        @endif
    </div>
</div>

<h3 class="h6 text-navy border-bottom pb-2 mb-3">Service address</h3>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <label for="address_house" class="form-label">House / building</label>
        <input type="text" name="address[house_building]" id="address_house"
               class="form-control @error('address.house_building') is-invalid @enderror"
               value="{{ $address['house_building'] ?? '' }}">
        @error('address.house_building')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label for="address_street" class="form-label">Street</label>
        <input type="text" name="address[street]" id="address_street"
               class="form-control @error('address.street') is-invalid @enderror"
               value="{{ $address['street'] ?? '' }}">
        @error('address.street')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label for="address_barangay" class="form-label">Barangay <span class="text-danger">*</span></label>
        <input type="text" name="address[barangay]" id="address_barangay"
               class="form-control @error('address.barangay') is-invalid @enderror"
               value="{{ $address['barangay'] ?? '' }}" required>
        @error('address.barangay')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label for="address_city" class="form-label">Municipality / city <span class="text-danger">*</span></label>
        <input type="text" name="address[municipality_city]" id="address_city"
               class="form-control @error('address.municipality_city') is-invalid @enderror"
               value="{{ $address['municipality_city'] ?? '' }}" required>
        @error('address.municipality_city')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label for="address_province" class="form-label">Province <span class="text-danger">*</span></label>
        <input type="text" name="address[province]" id="address_province"
               class="form-control @error('address.province') is-invalid @enderror"
               value="{{ $address['province'] ?? '' }}" required>
        @error('address.province')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label for="address_postal" class="form-label">Postal code</label>
        <input type="text" name="address[postal_code]" id="address_postal"
               class="form-control @error('address.postal_code') is-invalid @enderror"
               value="{{ $address['postal_code'] ?? '' }}">
        @error('address.postal_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<h3 class="h6 text-navy border-bottom pb-2 mb-3">Service details</h3>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <label for="customer_type" class="form-label">Customer type <span class="text-danger">*</span></label>
        <select name="customer_type" id="customer_type" class="form-select @error('customer_type') is-invalid @enderror" required>
            @foreach ($types as $type)
                <option value="{{ $type->value }}"
                    @selected(old('customer_type', $customer->customer_type->value ?? 'residential') === $type->value)>
                    {{ $type->label() }}
                </option>
            @endforeach
        </select>
        @error('customer_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label for="installation_date" class="form-label">Installation date</label>
        <input type="date" name="installation_date" id="installation_date"
               class="form-control @error('installation_date') is-invalid @enderror"
               value="{{ old('installation_date', $customer?->installation_date?->format('Y-m-d')) }}">
        @error('installation_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-2">
        <label for="status" class="form-label">Customer status <span class="text-danger">*</span></label>
        <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}"
                    @selected(old('status', $customer->status->value ?? 'pending_installation') === $status->value)>
                    {{ $status->label() }}
                </option>
            @endforeach
        </select>
        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-2">
        <label for="account_status" class="form-label">Billing standing <span class="text-danger">*</span></label>
        <select name="account_status" id="account_status" class="form-select @error('account_status') is-invalid @enderror" required>
            @foreach ($accountStatuses as $status)
                <option value="{{ $status->value }}"
                    @selected(old('account_status', $customer->account_status->value ?? 'good_standing') === $status->value)>
                    {{ $status->label() }}
                </option>
            @endforeach
        </select>
        @error('account_status')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-2">
        <label for="connection_status" class="form-label">Connection <span class="text-danger">*</span></label>
        <select name="connection_status" id="connection_status" class="form-select @error('connection_status') is-invalid @enderror" required>
            @foreach ($connectionStatuses as $status)
                <option value="{{ $status->value }}"
                    @selected(old('connection_status', $customer->connection_status->value ?? 'pending') === $status->value)>
                    {{ $status->label() }}
                </option>
            @endforeach
        </select>
        @error('connection_status')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label for="notes" class="form-label">Notes</label>
        <textarea name="notes" id="notes" rows="3"
                  class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $customer->notes ?? '') }}</textarea>
        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<h3 class="h6 text-navy border-bottom pb-2 mb-3">
    Additional contacts
    <span class="fw-normal small text-secondary">— optional, up to five</span>
</h3>

<div id="contact-rows">
    @foreach ($contactRows as $index => $contact)
        <div class="row g-2 mb-2 contact-row">
            <div class="col-md-3">
                <input type="text" name="contacts[{{ $index }}][name]" placeholder="Name"
                       class="form-control form-control-sm @error("contacts.$index.name") is-invalid @enderror"
                       value="{{ $contact['name'] ?? '' }}">
                @error("contacts.$index.name")<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <input type="text" name="contacts[{{ $index }}][relationship]" placeholder="Relationship"
                       class="form-control form-control-sm"
                       value="{{ $contact['relationship'] ?? '' }}">
            </div>
            <div class="col-md-3">
                <input type="text" name="contacts[{{ $index }}][contact_number]" placeholder="Contact number"
                       class="form-control form-control-sm @error("contacts.$index.contact_number") is-invalid @enderror"
                       value="{{ $contact['contact_number'] ?? '' }}">
                @error("contacts.$index.contact_number")<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <input type="email" name="contacts[{{ $index }}][email]" placeholder="Email"
                       class="form-control form-control-sm @error("contacts.$index.email") is-invalid @enderror"
                       value="{{ $contact['email'] ?? '' }}">
                @error("contacts.$index.email")<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    @endforeach
</div>

<button type="button" class="btn btn-sm btn-light border" id="add-contact">
    <i class="bi bi-plus-lg me-1"></i> Add another contact
</button>

@push('scripts')
<script>
    document.getElementById('add-contact')?.addEventListener('click', () => {
        const container = document.getElementById('contact-rows');
        const rows = container.querySelectorAll('.contact-row');

        if (rows.length >= 5) {
            return;
        }

        const clone = rows[rows.length - 1].cloneNode(true);
        const index = rows.length;

        clone.querySelectorAll('input').forEach((input) => {
            input.value = '';
            input.classList.remove('is-invalid');
            input.name = input.name.replace(/contacts\[\d+\]/, 'contacts[' + index + ']');
        });
        clone.querySelectorAll('.invalid-feedback').forEach((el) => el.remove());

        container.appendChild(clone);
    });
</script>
@endpush
