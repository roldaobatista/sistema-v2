<?php

namespace App\Services\Email;

use App\Exceptions\CircuitBreakerException;
use App\Jobs\ClassifyEmailJob;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Email;
use App\Models\EmailAccount;
use App\Models\EmailAttachment;
use App\Services\Integration\CircuitBreaker;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Webklex\IMAP\Facades\Client as ImapClient;

class EmailSyncService
{
    public function syncAccount(EmailAccount $account): int
    {
        // Circuit breaker per email account — one failing server doesn't block others
        $cb = CircuitBreaker::for("imap_{$account->id}")
            ->withThreshold(3)
            ->withTimeout(300);

        if ($cb->isOpen()) {
            $accountName = $account->getAttribute('name');

            Log::info('Email sync: circuit breaker open, skipping', [
                'account' => $account->id,
                'name' => is_string($accountName) ? $accountName : null,
            ]);

            return 0;
        }

        $account->markSyncing();
        $synced = 0;

        try {
            $synced = $cb->execute(function () use ($account) {
                return $this->doSync($account);
            });

            $account->markSynced($this->lastSyncUid);

            Log::info('Email sync completed', [
                'account' => $account->id,
                'name' => is_string($account->getAttribute('name')) ? $account->getAttribute('name') : null,
                'synced' => $synced,
            ]);
        } catch (CircuitBreakerException $e) {
            $account->markSyncError('Circuit breaker open: '.$e->getMessage());
            Log::warning('Email sync: circuit breaker tripped', [
                'account' => $account->id,
                'retry_after' => $e->getRetryAfterSeconds(),
            ]);
        } catch (\Exception $e) {
            $account->markSyncError($e->getMessage());
            Log::error('Email sync failed', [
                'account' => $account->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $synced;
    }

    private int $lastSyncUid = 0;

    private function doSync(EmailAccount $account): int
    {
        $client = ImapClient::make([
            'host' => $account->imap_host,
            'port' => $account->imap_port,
            'encryption' => $account->imap_encryption,
            'validate_cert' => true,
            'username' => $account->imap_username,
            'password' => $account->imap_password,
            'protocol' => 'imap',
        ]);

        $client->connect();
        $folder = $client->getFolder('INBOX');

        if (! $folder) {
            throw new \RuntimeException('INBOX folder not found');
        }

        $query = $folder->messages();

        if ($account->last_sync_uid) {
            $query = $query->setFetchBody(true)->where('UID', '>', $account->last_sync_uid);
        } else {
            $query = $query->setFetchBody(true)->limit(50);
        }

        $messages = $query->get();
        $lastUid = $account->last_sync_uid ?? 0;
        $synced = 0;

        foreach ($messages as $message) {
            try {
                $email = $this->processMessage($account, $message);
                if ($email) {
                    $synced++;
                    $msgUid = (int) $message->getUid();
                    if ($msgUid > $lastUid) {
                        $lastUid = $msgUid;
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Email sync: failed to process message', [
                    'account' => $account->id,
                    'uid' => $message->getUid(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $client->disconnect();
        $this->lastSyncUid = $lastUid;

        return $synced;
    }

    private function processMessage(EmailAccount $account, $message): ?Email
    {
        $messageId = $message->getMessageId()?->toString() ?? '';
        if (! $messageId) {
            $messageId = md5($message->getSubject().$message->getDate()->toDateTimeString().$message->getFrom()[0]->mail);
        }

        // Skip duplicates
        if (Email::where('message_id', $messageId)->exists()) {
            return null;
        }

        $from = $message->getFrom()[0] ?? null;
        $fromAddress = $from ? $from->mail : 'unknown@unknown.com';
        $fromName = $from ? ($from->personal ?? null) : null;

        $toAddresses = collect($message->getTo() ?? [])
            ->map(fn ($addr) => ['email' => $addr->mail, 'name' => $addr->personal ?? null])
            ->toArray();

        $ccAddresses = collect($message->getCc() ?? [])
            ->map(fn ($addr) => ['email' => $addr->mail, 'name' => $addr->personal ?? null])
            ->toArray();

        $subject = $message->getSubject()?->toString() ?? '(sem assunto)';
        $bodyText = $message->getTextBody();
        $bodyHtml = $message->getHTMLBody();
        $snippet = Str::limit(strip_tags($bodyText ?: $bodyHtml ?: ''), 300);
        $date = $message->getDate()?->toDate() ?? now();

        $inReplyTo = $message->getInReplyTo()?->toString();
        $references = $message->getReferences()?->toString();
        $threadId = Email::resolveThreadId($messageId, $inReplyTo, $references);

        $hasAttachments = $message->getAttachments()->count() > 0;

        // Auto-link to customer by from_address (check main email and contacts)
        $customerId = Customer::where('tenant_id', $account->tenant_id)
            ->where('email', $fromAddress)
            ->value('id');

        if (! $customerId) {
            $customerId = CustomerContact::whereHas('customer', function ($q) use ($account) {
                $q->where('tenant_id', $account->tenant_id);
            })
                ->where('email', $fromAddress)
                ->value('customer_id');
        }

        $email = Email::create([
            'tenant_id' => $account->tenant_id,
            'email_account_id' => $account->id,
            'message_id' => $messageId,
            'in_reply_to' => $inReplyTo,
            'thread_id' => $threadId,
            'folder' => 'INBOX',
            'uid' => (int) $message->getUid(),
            'from_address' => $fromAddress,
            'from_name' => $fromName,
            'to_addresses' => $toAddresses,
            'cc_addresses' => $ccAddresses,
            'subject' => Str::limit($subject, 500, ''),
            'body_text' => $bodyText,
            'body_html' => $bodyHtml,
            'snippet' => $snippet,
            'date' => $date,
            'has_attachments' => $hasAttachments,
            'customer_id' => $customerId,
            'direction' => 'inbound',
            'status' => 'new',
        ]);

        // Process attachments
        if ($hasAttachments) {
            foreach ($message->getAttachments() as $attachment) {
                $this->saveAttachment($email, $attachment);
            }
        }

        // Dispatch AI classification + rule engine
        ClassifyEmailJob::dispatch($email);

        return $email;
    }

    private function saveAttachment(Email $email, $attachment): void
    {
        try {
            $filename = $attachment->getName() ?? 'attachment';
            $path = "email-attachments/{$email->tenant_id}/{$email->id}/".Str::random(8).'_'.$filename;

            Storage::put($path, $attachment->getContent());

            EmailAttachment::create([
                'email_id' => $email->id,
                'filename' => $filename,
                'mime_type' => $attachment->getMimeType() ?? 'application/octet-stream',
                'size_bytes' => $attachment->getSize() ?? strlen($attachment->getContent()),
                'storage_path' => $path,
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to save email attachment', [
                'email_id' => $email->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
