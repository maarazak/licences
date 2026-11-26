{{-- Menu déroulant : $id, $name, $label, $options (valeur => libellé), $selected, $empty (libellé de l'option vide), $hint (optionnel).
     Si la valeur actuelle n'est pas dans la liste (ancien produit), elle reste sélectionnable pour ne rien perdre. --}}
@php
    $selected = (string) $selected;
    if ($selected !== '' && !array_key_exists($selected, $options)) {
        $options = [$selected => $selected . ' (actuel)'] + $options;
    }
@endphp
<div>
    <label for="{{ $id }}" class="label">{{ $label }}</label>
    <select id="{{ $id }}" name="{{ $name }}" class="input">
        <option value="">{{ $empty }}</option>
        @foreach($options as $value => $text)
            <option value="{{ $value }}" {{ (string) $value === $selected ? 'selected' : '' }}>{{ $text }}</option>
        @endforeach
    </select>
    @if(!empty($hint))
        <p class="hint">{{ $hint }}</p>
    @endif
</div>
