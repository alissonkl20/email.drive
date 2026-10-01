<?php

use App\Contracts\EmailProvider;
use App\Models\User;
use App\Services\Email\EmailReportService;

test('o relatório filtra por data e aplica as tags configuradas', function () {
    config([
        'email_report.specs.tags' => [
            'vendas' => [
                'subject' => ['proposta'],
                'from' => [],
            ],
        ],
        'email_report.specs.filters.date' => [
            'from' => '2026-09-01',
            'to' => '2026-09-30',
        ],
    ]);

    $this->app->bind(EmailProvider::class, fn (): EmailProvider => new class implements EmailProvider
    {
        public function getEmails(array $specs): array
        {
            return [
                [
                    'id' => '1',
                    'subject' => 'Proposta comercial',
                    'from' => 'vendas@loja.com',
                    'to' => 'eu@exemplo.com',
                    'date' => '2026-09-15 10:00:00',
                    'body' => '',
                    'folder' => 'INBOX',
                ],
                [
                    'id' => '2',
                    'subject' => 'Newsletter',
                    'from' => 'news@mail.com',
                    'to' => 'eu@exemplo.com',
                    'date' => '2026-08-01 10:00:00',
                    'body' => '',
                    'folder' => 'INBOX',
                ],
            ];
        }
    });

    $report = app(EmailReportService::class)->run();

    expect($report['total'])->toBe(1)
        ->and($report['by_tag'])->toBe(['vendas' => 1])
        ->and($report['emails'][0]['subject'])->toBe('Proposta comercial')
        ->and($report['emails'][0]['tags'])->toBe(['vendas']);
});

test('o post usa as specs enviadas no lugar da configuração', function () {
    $this->app->bind(EmailProvider::class, fn (): EmailProvider => new class implements EmailProvider
    {
        public function getEmails(array $specs): array
        {
            return [
                [
                    'id' => '1',
                    'subject' => 'Chamado aberto',
                    'from' => 'cliente@empresa.com',
                    'to' => 'eu@exemplo.com',
                    'date' => '2026-09-20 11:00:00',
                    'body' => 'preciso de ajuda',
                    'folder' => 'INBOX',
                ],
            ];
        }
    });

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('email-report'), [
            'fields' => ['subject', 'from'],
            'tags' => [
                'suporte' => [
                    'subject' => ['chamado'],
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('by_tag.suporte', 1)
        ->assertJsonPath('emails.0.tags.0', 'suporte')
        ->assertJsonMissingPath('emails.0.body');
});
