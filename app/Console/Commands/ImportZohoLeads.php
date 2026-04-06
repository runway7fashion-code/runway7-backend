<?php

namespace App\Console\Commands;

use App\Models\DesignerLead;
use App\Models\LeadActivity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportZohoLeads extends Command
{
    protected $signature = 'leads:import-zoho {file}';
    protected $description = 'Import leads from Zoho Bigin CSV export';

    // Contact Owner → user email (resolved to IDs at runtime)
    private array $ownerEmailMap = [
        'Fabiana Abusada' => 'fabiana@runway7fashion.com',
        'Katie Corrales'  => 'katie@runway7fashion.com',
    ];

    private string $defaultOwnerEmail = 'info@runway7fashion.com';

    private array $resolvedOwnerMap = [];
    private ?int $defaultOwnerId = null;

    private array $summary = [
        'created'  => 0,
        'updated'  => 0,
        'skipped'  => 0,
        'no_email' => 0,
        'errors'   => 0,
    ];

    public function handle(): int
    {
        $file = $this->argument('file');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return 1;
        }

        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);

        if (!$header) {
            $this->error('Could not read CSV header.');
            return 1;
        }

        // Resolve owner emails to user IDs
        $this->resolveOwners();

        // Map header names to indices
        $cols = array_flip($header);
        $totalRows = 0;

        // Count total for progress bar
        $totalLines = 0;
        while (fgetcsv($handle) !== false) $totalLines++;
        rewind($handle);
        fgetcsv($handle); // skip header again

        $this->info("Importing {$totalLines} leads from Zoho CSV...");
        $bar = $this->output->createProgressBar($totalLines);
        $bar->start();

        // Process in chunks for better performance
        $batch = [];
        $batchSize = 500;

        while (($row = fgetcsv($handle)) !== false) {
            $totalRows++;
            $bar->advance();

            try {
                $this->processRow($row, $cols, $totalRows + 1);
            } catch (\Exception $e) {
                $this->summary['errors']++;
                if ($this->summary['errors'] <= 20) {
                    $this->newLine();
                    $this->warn("Row {$totalRows}: " . $e->getMessage());
                }
            }
        }

        fclose($handle);
        $bar->finish();
        $this->newLine(2);

        $this->info('=== Import Summary ===');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Created', $this->summary['created']],
                ['Updated (existing email)', $this->summary['updated']],
                ['Created without email', $this->summary['no_email']],
                ['Errors', $this->summary['errors']],
                ['Total processed', $totalRows],
            ]
        );

        return 0;
    }

    private function resolveOwners(): void
    {
        $User = \App\Models\User::class;

        // Resolve default owner
        $defaultUser = $User::where('email', $this->defaultOwnerEmail)->first();
        if (!$defaultUser) {
            $this->error("Default owner not found: {$this->defaultOwnerEmail}");
            exit(1);
        }
        $this->defaultOwnerId = $defaultUser->id;
        $this->info("Default owner: {$defaultUser->first_name} {$defaultUser->last_name} (ID {$defaultUser->id})");

        // Resolve mapped owners
        foreach ($this->ownerEmailMap as $name => $email) {
            $user = $User::where('email', $email)->first();
            if ($user) {
                $this->resolvedOwnerMap[$name] = $user->id;
                $this->info("Owner '{$name}' → {$user->first_name} {$user->last_name} (ID {$user->id})");
            } else {
                $this->warn("Owner '{$name}' ({$email}) not found, will use default");
            }
        }
        $this->newLine();
    }

    private function processRow(array $row, array $cols, int $rowNum): void
    {
        $email      = trim(strtolower($row[$cols['Email']] ?? ''));
        $firstName  = trim($row[$cols['First Name']] ?? '');
        $lastName   = trim($row[$cols['Last Name']] ?? '');
        $company    = trim($row[$cols['Company Name']] ?? '');
        $mobile     = trim($row[$cols['Mobile']] ?? '');
        $phone      = trim($row[$cols['Phone']] ?? '');
        $country    = trim($row[$cols['Mailing Country']] ?? '');
        $instagram  = trim($row[$cols['Instagram Link']] ?? '');
        $tag        = trim($row[$cols['Tag']] ?? '');
        $owner      = trim($row[$cols['Contact Owner']] ?? '');
        $createdAt  = trim($row[$cols['Created Time']] ?? '');

        // Resolve phone: prefer mobile, fallback to phone
        $finalPhone = $mobile ?: $phone;

        // Clean last name: remove "-" placeholder
        if ($lastName === '-') $lastName = '';

        // Clean instagram
        if ($instagram) {
            $instagram = strtok($instagram, '?');
            $instagram = preg_replace('#^https?://(www\.)?instagram\.com/#i', '', $instagram);
            $instagram = rtrim($instagram, '/');
            $instagram = ltrim($instagram, '@');
        }

        // Resolve assigned_to
        $assignedTo = $this->resolvedOwnerMap[$owner] ?? $this->defaultOwnerId;

        // Parse created time
        $parsedCreatedAt = null;
        if ($createdAt) {
            try {
                $parsedCreatedAt = \Carbon\Carbon::parse($createdAt);
            } catch (\Exception $e) {
                $parsedCreatedAt = null;
            }
        }

        $hasEmail = $email && filter_var($email, FILTER_VALIDATE_EMAIL);

        // Skip the sample Zoho contact
        if ($hasEmail && $email === 'support@bigin.com') {
            $this->summary['skipped']++;
            return;
        }

        DB::transaction(function () use ($hasEmail, $email, $firstName, $lastName, $company, $finalPhone, $country, $instagram, $tag, $assignedTo, $parsedCreatedAt) {
            if ($hasEmail) {
                $existing = DesignerLead::withTrashed()->where('email', $email)->first();

                if ($existing) {
                    // Update only empty fields
                    $updates = [];
                    if (!$existing->first_name && $firstName)      $updates['first_name'] = $firstName;
                    if (!$existing->last_name && $lastName)        $updates['last_name'] = $lastName;
                    if (!$existing->phone && $finalPhone)          $updates['phone'] = $finalPhone;
                    if (!$existing->country && $country)           $updates['country'] = $country;
                    if (!$existing->company_name && $company)      $updates['company_name'] = $company;
                    if (!$existing->instagram && $instagram)       $updates['instagram'] = $instagram;
                    if (!$existing->assigned_to)                   $updates['assigned_to'] = $assignedTo;
                    if ($updates) $existing->update($updates);

                    $this->summary['updated']++;
                    return;
                }
            }

            $lead = DesignerLead::create([
                'first_name'   => $firstName ?: 'Lead',
                'last_name'    => $lastName ?: '',
                'email'        => $hasEmail ? $email : null,
                'phone'        => $finalPhone ?: null,
                'country'      => $country ?: null,
                'company_name' => $company ?: null,
                'instagram'    => $instagram ?: null,
                'status'       => 'new',
                'source'       => 'other',
                'assigned_to'  => $assignedTo,
                'notes'        => $tag ?: null,
                'created_at'   => $parsedCreatedAt ?? now(),
                'updated_at'   => now(),
            ]);

            if ($hasEmail) {
                $this->summary['created']++;
            } else {
                $this->summary['no_email']++;
            }
        });
    }
}
