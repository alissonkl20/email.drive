<?php

namespace App\Services\Email;

use App\Contracts\EmailProvider;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use RuntimeException;
use Webklex\PHPIMAP\Address;
use Webklex\PHPIMAP\Attribute;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message;

class ImapEmailProvider implements EmailProvider
{
    public function getEmails(array $specs): array
    {
        /** @var array<string, mixed> $connection */
        $connection = config('email_report.connection');

        if (! is_string($connection['host'] ?? null) || $connection['host'] === ''
            || ! is_string($connection['username'] ?? null) || $connection['username'] === ''
            || ! is_string($connection['password'] ?? null) || $connection['password'] === '') {
            throw new RuntimeException('Preencha EMAIL_IMAP_HOST, EMAIL_IMAP_USERNAME e EMAIL_IMAP_PASSWORD no .env.');
        }

        $client = (new ClientManager)->make([
            'host' => $connection['host'],
            'port' => $connection['port'],
            'encryption' => $connection['encryption'],
            'validate_cert' => $connection['validate_cert'],
            'username' => $connection['username'],
            'password' => $connection['password'],
            'protocol' => 'imap',
        ]);

        $client->connect();

        try {
            return $this->fetch($client, $specs);
        } finally {
            $client->disconnect();
        }
    }

    /**
     * @param  array<string, mixed>  $specs
     * @return list<array{id: string, subject: string, from: string, to: string, date: string, body: string, folder: string}>
     */
    private function fetch(Client $client, array $specs): array
    {
        /** @var array<string, mixed> $filters */
        $filters = $specs['filters'] ?? [];
        /** @var list<string> $folders */
        $folders = $filters['folders'] ?? ['INBOX'];
        /** @var list<string> $fields */
        $fields = $specs['fields'] ?? [];
        $fetchBody = in_array('body', $fields, true);
        $limit = max(1, (int) ($specs['limit'] ?? 500));
        /** @var array<string, mixed> $date */
        $date = $filters['date'] ?? [];

        $emails = [];

        foreach ($folders as $folderName) {
            $folder = $client->getFolder($folderName);

            if ($folder === null) {
                throw new RuntimeException("Pasta IMAP não encontrada: {$folderName}");
            }

            $query = $folder->query()->leaveUnread()->setFetchBody($fetchBody)->limit($limit);

            if (is_string($date['from'] ?? null) && $date['from'] !== '') {
                $query->since($date['from']);
            }

            if (is_string($date['to'] ?? null) && $date['to'] !== '') {
                $query->before(Carbon::parse($date['to'])->addDay()->format('d-M-Y'));
            }

            foreach ($query->get() as $message) {
                $emails[] = $this->map($message, $folderName, $fetchBody);
            }
        }

        return $emails;
    }

    /**
     * @return array{id: string, subject: string, from: string, to: string, date: string, body: string, folder: string}
     */
    private function map(Message $message, string $folder, bool $fetchBody): array
    {
        $body = '';

        if ($fetchBody) {
            $body = trim($message->getTextBody());

            if ($body === '') {
                $body = trim(strip_tags($message->getHTMLBody()));
            }

            $body = mb_substr($body, 0, (int) config('email_report.body_max_length', 2000));
        }

        return [
            'id' => (string) $message->getUid(),
            'subject' => (string) $message->getSubject(),
            'from' => $this->addresses($message->getFrom()),
            'to' => $this->addresses($message->getTo()),
            'date' => $this->date($message->getDate()),
            'body' => $body,
            'folder' => $folder,
        ];
    }

    private function addresses(mixed $attribute): string
    {
        if (! is_iterable($attribute)) {
            return '';
        }

        $mails = [];

        foreach ($attribute as $address) {
            if ($address instanceof Address && $address->mail !== '') {
                $mails[] = $address->mail;
            }
        }

        return implode(', ', $mails);
    }

    private function date(mixed $attribute): string
    {
        $value = $attribute instanceof Attribute ? $attribute->first() : $attribute;

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return is_string($value) ? $value : '';
    }
}
