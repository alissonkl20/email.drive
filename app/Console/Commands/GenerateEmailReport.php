<?php

namespace App\Console\Commands;

use App\Services\Email\EmailReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GenerateEmailReport extends Command
{
    protected $signature = 'email:report {--from=} {--to=}';

    protected $description = 'Busca os e-mails e grava um relatório com as tags configuradas';

    public function handle(EmailReportService $reports): int
    {
        $overrides = [];

        if (is_string($this->option('from')) && $this->option('from') !== '') {
            $overrides['filters']['date']['from'] = $this->option('from');
        }

        if (is_string($this->option('to')) && $this->option('to') !== '') {
            $overrides['filters']['date']['to'] = $this->option('to');
        }

        $report = $reports->run($overrides);
        $path = 'email-reports/report-'.now()->format('Ymd-His').'.json';

        Storage::disk('local')->put(
            $path,
            (string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );

        $this->info('Total: '.$report['total']);

        foreach ($report['by_tag'] as $tag => $count) {
            $this->line($tag.': '.$count);
        }

        $this->info('Arquivo: '.storage_path('app/private/'.$path));

        return self::SUCCESS;
    }
}
