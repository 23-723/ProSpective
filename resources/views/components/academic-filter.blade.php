@props(['name', 'label', 'placeholder' => null])
<label class="filter-field">
    <span>{{ $label }}</span>
    <select x-model="draft.{{ $name }}" @change="dependencyChanged('{{ $name }}')" :disabled="loading" aria-label="{{ $label }}">
        @if($placeholder)<option value="">{{ $placeholder }}</option>@endif
        <template x-for="option in availableOptions('{{ $name }}')" :key="option.value">
            <option :value="option.value" x-text="option.label"></option>
        </template>
    </select>
</label>
