<?php

return [
    // Values that do not fit the type of their setting.
    'not_null' => 'El ajuste :key no puede ser nulo.',
    'string' => 'El ajuste :key debe ser un texto.',
    'boolean' => 'El ajuste :key debe ser verdadero o falso.',
    'integer' => 'El ajuste :key debe ser un número entero.',
    'number' => 'El ajuste :key debe ser un número.',
    'array' => 'El ajuste :key debe ser una lista.',
    'one_of' => 'El ajuste :key debe ser uno de estos valores: :values.',

    // Keys that cannot be written from user input.
    'unknown' => "El ajuste ':key' no existe.",
    'not_editable' => "El ajuste ':key' no se puede cambiar aquí.",

    // JSON endpoints.
    'values_object' => 'Envía en "values" un objeto con las claves y los valores de los ajustes.',
    'too_many' => 'Envía como máximo :max ajustes por petición.',
    'too_large' => "El valor de ':key' supera los :max bytes.",
];
