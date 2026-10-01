<?php

namespace App\Services\Email;

use App\Contracts\EmailProvider;

class EmailReportService
{
    /**
     * @var list<string>
     */
    public const FIELDS = ['subject', 'from', 'to', 'date', 'body', 'folder'];

    public function __construct(private EmailProvider $provider) {}

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function run(array $overrides = []): array
    {
        $specs = $this->specs($overrides);
        $emails = $this->filter($this->provider->getEmails($specs), $specs);

        if (count($emails) > $specs['limit']) {
            $emails = array_slice($emails, 0, $specs['limit']);
        }

        $byTag = [];

        $rows = array_map(function (array $email) use ($specs, &$byTag): array {
            $tags = $this->tagsFor($email, $specs['tags']);

            if ($tags === []) {
                $byTag['sem_tag'] = ($byTag['sem_tag'] ?? 0) + 1;
            }

            foreach ($tags as $tag) {
                $byTag[$tag] = ($byTag[$tag] ?? 0) + 1;
            }

            $row = ['id' => $email['id'], 'tags' => $tags];

            foreach ($specs['fields'] as $field) {
                $row[$field] = $email[$field];
            }

            return $row;
        }, $emails);

        return [
            'period' => [
                'from' => $specs['filters']['date']['from'],
                'to' => $specs['filters']['date']['to'],
            ],
            'total' => count($rows),
            'by_tag' => $byTag,
            'emails' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{fields: list<string>, filters: array{folders: list<string>, date: array{from: ?string, to: ?string}, from: list<string>, subject: list<string>}, tags: array<string, array<string, list<string>>>, limit: int}
     */
    public function specs(array $overrides = []): array
    {
        /** @var array<string, mixed> $base */
        $base = config('email_report.specs');
        /** @var array<string, mixed> $baseFilters */
        $baseFilters = $base['filters'] ?? [];
        /** @var array<string, mixed> $overrideFilters */
        $overrideFilters = $overrides['filters'] ?? [];
        /** @var array<string, mixed> $baseDate */
        $baseDate = $baseFilters['date'] ?? [];
        /** @var array<string, mixed> $overrideDate */
        $overrideDate = $overrideFilters['date'] ?? [];

        $fields = array_values(array_intersect(
            self::FIELDS,
            $overrides['fields'] ?? $base['fields'] ?? self::FIELDS,
        ));

        return [
            'fields' => $fields === [] ? ['subject', 'from', 'date'] : $fields,
            'filters' => [
                'folders' => array_values($overrideFilters['folders'] ?? $baseFilters['folders'] ?? ['INBOX']),
                'date' => [
                    'from' => $this->nullableString($overrideDate['from'] ?? $baseDate['from'] ?? null),
                    'to' => $this->nullableString($overrideDate['to'] ?? $baseDate['to'] ?? null),
                ],
                'from' => array_values($overrideFilters['from'] ?? $baseFilters['from'] ?? []),
                'subject' => array_values($overrideFilters['subject'] ?? $baseFilters['subject'] ?? []),
            ],
            'tags' => $overrides['tags'] ?? $base['tags'] ?? [],
            'limit' => max(1, (int) ($overrides['limit'] ?? $base['limit'] ?? 500)),
        ];
    }

    /**
     * @param  list<array{id: string, subject: string, from: string, to: string, date: string, body: string, folder: string}>  $emails
     * @param  array{filters: array{folders: list<string>, date: array{from: ?string, to: ?string}, from: list<string>, subject: list<string>}}  $specs
     * @return list<array{id: string, subject: string, from: string, to: string, date: string, body: string, folder: string}>
     */
    private function filter(array $emails, array $specs): array
    {
        $filters = $specs['filters'];

        return array_values(array_filter($emails, function (array $email) use ($filters): bool {
            if ($filters['folders'] !== [] && ! in_array($email['folder'], $filters['folders'], true)) {
                return false;
            }

            $day = substr($email['date'], 0, 10);

            if ($filters['date']['from'] !== null && $day < $filters['date']['from']) {
                return false;
            }

            if ($filters['date']['to'] !== null && $day > $filters['date']['to']) {
                return false;
            }

            if (! $this->matchesAny($email['from'], $filters['from'])) {
                return false;
            }

            return $this->matchesAny($email['subject'], $filters['subject']);
        }));
    }

    /**
     * @param  array{subject: string, from: string, body: string}  $email
     * @param  array<string, array<string, list<string>>>  $tagRules
     * @return list<string>
     */
    private function tagsFor(array $email, array $tagRules): array
    {
        $matched = [];

        foreach ($tagRules as $name => $rules) {
            $subject = $rules['subject'] ?? [];
            $from = $rules['from'] ?? [];
            $body = $rules['body'] ?? [];

            if ($subject === [] && $from === [] && $body === []) {
                continue;
            }

            $hit = ($subject !== [] && $this->hits($email['subject'], $subject))
                || ($from !== [] && $this->hits($email['from'], $from))
                || ($body !== [] && $this->hits($email['body'], $body));

            if ($hit) {
                $matched[] = (string) $name;
            }
        }

        return $matched;
    }

    /**
     * @param  list<string>  $needles
     */
    private function matchesAny(string $haystack, array $needles): bool
    {
        if ($needles === []) {
            return true;
        }

        return $this->hits($haystack, $needles);
    }

    /**
     * @param  list<string>  $needles
     */
    private function hits(string $haystack, array $needles): bool
    {
        $haystack = mb_strtolower($haystack);

        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, mb_strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
