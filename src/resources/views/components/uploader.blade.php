@props([
    'id' => 'uploader-' . uniqid(),
    'name' => 'image',
    'value' => null,
    'type' => 'single',
    'text' => 'Select File',
    'preview' => 'true',
    'hideMediaTab' => 'false',
])

<div id="{{ $id }}" data-fileinputname="{{ $name }}"
    data-hasfile="{{ is_array($value) ? json_encode($value) : $value }}" data-uploadtype="{{ $type }}"
    data-uploadtext="{{ $text }}" data-preview="{{ $preview }}" data-hidemediatab="{{ $hideMediaTab }}"
    {{ $attributes->merge(['class' => 'uploader']) }}></div>
