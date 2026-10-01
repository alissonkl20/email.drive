<?php

namespace App\Contracts;

interface EmailProvider
{
    /**
     * @param  array<string, mixed>  $specs
     * @return list<array{id: string, subject: string, from: string, to: string, date: string, body: string, folder: string}>
     */
    public function getEmails(array $specs): array;
}
