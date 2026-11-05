<?php

namespace App\Console\Commands;

use App\Services\SalesAssistant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('amo:suggest {message* : Client messages; prefix a line with "M:" to mark it as a manager message}')]
#[Description('Generate a client reply draft and an upsell hint without amoCRM (for local demos)')]
class SuggestReply extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SalesAssistant $assistant): int
    {
        $dialog = array_map(function (string $line): array {
            if (str_starts_with($line, 'M:')) {
                return ['from' => 'manager', 'text' => trim(substr($line, 2))];
            }

            return ['from' => 'client', 'text' => $line];
        }, $this->argument('message'));

        $suggestion = $assistant->suggest($dialog);

        $this->line($suggestion->toNoteText());

        return self::SUCCESS;
    }
}
