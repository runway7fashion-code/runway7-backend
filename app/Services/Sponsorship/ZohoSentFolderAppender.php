<?php

namespace App\Services\Sponsorship;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Webklex\PHPIMAP\ClientManager;

/**
 * Copia un email ya enviado (por Mailgun) a la carpeta Sent de la cuenta Zoho
 * correspondiente al asesor remitente, usando IMAP APPEND.
 *
 * Si falla: logea warning y retorna. Nunca lanza — el email ya fue enviado,
 * el APPEND es best-effort.
 */
class ZohoSentFolderAppender
{
    public function append(User $sender, string $rawMimeMessage): void
    {
        $emailKey = mb_strtolower(trim($sender->email ?? ''));
        if ($emailKey === '') {
            return;
        }

        // Usamos lookup directo sobre el array (no dot-notation) porque
        // las claves contienen puntos (p.ej. "@runway7fashion.com").
        $accounts = config('sponsorship_mailboxes.accounts', []);
        $password = $accounts[$emailKey] ?? null;
        if (!$password) {
            Log::info("[Zoho APPEND] Skipped — no mailbox mapping for sender '{$emailKey}'.");
            return;
        }

        try {
            $cm = new ClientManager();
            $client = $cm->make([
                'host'           => config('sponsorship_mailboxes.host'),
                'port'           => config('sponsorship_mailboxes.port'),
                'encryption'     => config('sponsorship_mailboxes.encryption'),
                'validate_cert'  => true,
                'username'       => $sender->email,
                'password'       => $password,
                'protocol'       => 'imap',
                'authentication' => null,
            ]);
            $client->connect();

            $folderPath = config('sponsorship_mailboxes.sent_folder', 'Sent');
            $folder = $client->getFolderByPath($folderPath);
            if (!$folder) {
                Log::warning("[Zoho APPEND] Folder '{$folderPath}' not found for {$sender->email}");
                $client->disconnect();
                return;
            }

            $folder->appendMessage(
                $rawMimeMessage,
                ['\\Seen'],
                Carbon::now()
            );

            $client->disconnect();
        } catch (\Throwable $e) {
            Log::warning("[Zoho APPEND] Failed for {$sender->email}: " . $e->getMessage());
        }
    }
}
