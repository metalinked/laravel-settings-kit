<?php

return [
    // Values that do not fit the type of their setting.
    'not_null' => 'El paràmetre :key no pot ser nul.',
    'string' => 'El paràmetre :key ha de ser un text.',
    'boolean' => 'El paràmetre :key ha de ser cert o fals.',
    'integer' => 'El paràmetre :key ha de ser un nombre enter.',
    'number' => 'El paràmetre :key ha de ser un número.',
    'array' => 'El paràmetre :key ha de ser una llista.',
    'one_of' => 'El paràmetre :key ha de ser un d’aquests valors: :values.',

    // Keys that cannot be written from user input.
    'unknown' => "El paràmetre ':key' no existeix.",
    'not_editable' => "El paràmetre ':key' no es pot canviar aquí.",

    // JSON endpoints.
    'values_object' => 'Envia a "values" un objecte amb les claus i els valors dels paràmetres.',
    'too_many' => 'Envia com a màxim :max paràmetres per petició.',
    'too_large' => "El valor de ':key' supera els :max bytes.",
];
