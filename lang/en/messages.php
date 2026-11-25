<?php

return [
    // Values that do not fit the type of their setting.
    'not_null' => 'The :key setting cannot be null.',
    'string' => 'The :key setting must be a string.',
    'boolean' => 'The :key setting must be true or false.',
    'integer' => 'The :key setting must be an integer.',
    'number' => 'The :key setting must be a number.',
    'array' => 'The :key setting must be an array.',
    'one_of' => 'The :key setting must be one of: :values.',

    // Keys that cannot be written from user input.
    'unknown' => "Unknown setting ':key'.",
    'not_editable' => "The setting ':key' cannot be changed here.",

    // JSON endpoints.
    'values_object' => 'Send an object of setting keys and values in "values".',
    'too_many' => 'Send at most :max settings per request.',
    'too_large' => "The value of ':key' is larger than :max bytes.",
];
