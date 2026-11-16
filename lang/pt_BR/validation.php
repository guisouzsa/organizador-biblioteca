<?php

// Mensagens usadas pelos formulários do catálogo. Chaves ausentes caem no inglês padrão do Laravel.
return [
    'array' => 'O campo :attribute deve ser uma lista.',
    'boolean' => 'O campo :attribute deve ser verdadeiro ou falso.',
    'exists' => 'O valor selecionado para :attribute é inválido.',
    'integer' => 'O campo :attribute deve ser um número inteiro.',
    'required' => 'O campo :attribute é obrigatório.',
    'string' => 'O campo :attribute deve ser um texto.',
    'unique' => 'Já existe um registro com este valor em :attribute.',
    'url' => 'O campo :attribute deve ser uma URL válida (http ou https).',
    'max' => [
        'numeric' => 'O campo :attribute não pode ser maior que :max.',
        'string' => 'O campo :attribute não pode ter mais de :max caracteres.',
        'array' => 'O campo :attribute não pode ter mais de :max itens.',
    ],
    'min' => [
        'numeric' => 'O campo :attribute deve ser pelo menos :min.',
        'string' => 'O campo :attribute deve ter pelo menos :min caracteres.',
        'array' => 'O campo :attribute deve ter pelo menos :min itens.',
    ],
    'attributes' => [
        'q' => 'busca',
        'category' => 'disciplina',
        'year' => 'ano',
        'collection' => 'coleção',
        'isbn' => 'ISBN',
        'new_categories' => 'outras categorias',
        'collection_name' => 'coleção',
    ],
];
