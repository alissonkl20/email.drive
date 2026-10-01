<?php

/*
| Tudo que o relatório usa fica aqui.
| Campos, pastas, período, remetente, assunto e tags são manuais:
| uma tag entra quando o assunto, o remetente ou o corpo contém uma das palavras.
*/

return [

    'connection' => [
        'host' => env('EMAIL_IMAP_HOST'),
        'port' => (int) env('EMAIL_IMAP_PORT', 993),
        'encryption' => env('EMAIL_IMAP_ENCRYPTION', 'ssl') === 'false'
            ? false
            : env('EMAIL_IMAP_ENCRYPTION', 'ssl'),
        'validate_cert' => filter_var(env('EMAIL_IMAP_VALIDATE_CERT', true), FILTER_VALIDATE_BOOL),
        'username' => env('EMAIL_IMAP_USERNAME'),
        'password' => env('EMAIL_IMAP_PASSWORD'),
    ],

    'body_max_length' => 2000,

    'specs' => [
        'fields' => [
            'subject',
            'from',
            'to',
            'date',
        ],

        'filters' => [
            'folders' => [
                env('EMAIL_IMAP_FOLDER', 'INBOX'),
            ],
            'date' => [
                'from' => null,
                'to' => null,
            ],
            'from' => [],
            'subject' => [],
        ],

        /*
        | Exemplo:
        | 'vendas' => [
        |     'subject' => ['proposta', 'orçamento'],
        |     'from' => ['vendas@'],
        |     'body' => [],
        | ],
        */
        'tags' => [],

        'limit' => 500,
    ],

];
