<?php

use App\Models\Email;
use App\Models\EmailAccount;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Email\EmailClassifierService;
use App\Services\Email\EmailSendService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Model::unguard();
    $this->tenant = Tenant::factory()->create();
    app()->instance('current_tenant_id', $this->tenant->id);

    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_tenant_id' => $this->tenant->id,
    ]);

    $this->emailAccount = EmailAccount::create([
        'tenant_id' => $this->tenant->id,
        'email_address' => 'lab@kalibrium.test',
        'label' => 'Kalibrium Lab',
        'imap_host' => 'imap.test.com',
        'imap_port' => 993,
        'imap_encryption' => 'ssl',
        'imap_username' => 'lab@kalibrium.test',
        'imap_password' => 'secret',
        'is_active' => true,
    ]);

    $this->sendService = app(EmailSendService::class);
});

// ── EmailSendService ──

test('send creates email record with sent status', function () {
    Mail::fake();

    $email = $this->sendService->send(
        account: $this->emailAccount,
        to: 'client@example.com',
        subject: 'Certificado de Calibração',
        body: '<p>Segue em anexo o certificado.</p>',
    );

    expect($email)->toBeInstanceOf(Email::class);
    expect($email->folder)->toBe('Sent');
    expect($email->direction)->toBe('outbound');
    expect($email->from_address)->toBe('lab@kalibrium.test');
    expect($email->subject)->toBe('Certificado de Calibração');
    expect($email->is_read)->toBeTrue();
});

test('send creates scheduled email when scheduledAt is provided', function () {
    $email = $this->sendService->send(
        account: $this->emailAccount,
        to: 'client@example.com',
        subject: 'Agendamento',
        body: '<p>Confirmação de agendamento.</p>',
        scheduledAt: '2026-04-01 09:00:00',
    );

    expect($email->status)->toBe('scheduled');
    expect($email->scheduled_at)->not->toBeNull();
});

test('send generates unique message_id', function () {
    Mail::fake();

    $email1 = $this->sendService->send(
        account: $this->emailAccount,
        to: 'a@test.com',
        subject: 'Test 1',
        body: 'body',
    );

    $email2 = $this->sendService->send(
        account: $this->emailAccount,
        to: 'b@test.com',
        subject: 'Test 2',
        body: 'body',
    );

    expect($email1->message_id)->not->toBe($email2->message_id);
});

test('send strips HTML for body_text', function () {
    Mail::fake();

    $email = $this->sendService->send(
        account: $this->emailAccount,
        to: 'client@test.com',
        subject: 'Test',
        body: '<p>Hello <strong>World</strong></p>',
    );

    expect($email->body_text)->toBe('Hello World');
});

test('reply sets in_reply_to from original email', function () {
    Mail::fake();

    $original = Email::create([
        'tenant_id' => $this->tenant->id,
        'email_account_id' => $this->emailAccount->id,
        'message_id' => '<original@test.com>',
        'thread_id' => 'thread-123',
        'folder' => 'Inbox',
        'from_address' => 'client@example.com',
        'from_name' => 'Client',
        'to_addresses' => [['email' => 'lab@kalibrium.test']],
        'subject' => 'Question about calibration',
        'body_html' => '<p>Question</p>',
        'body_text' => 'Question',
        'date' => now(),
        'is_read' => true,
        'direction' => 'inbound',
        'status' => 'received',
    ]);

    $reply = $this->sendService->reply($original, [
        'body' => '<p>Here is the answer.</p>',
    ]);

    expect($reply->in_reply_to)->toBe('<original@test.com>');
    expect($reply->thread_id)->toBe('thread-123');
    expect($reply->subject)->toBe('Re: Question about calibration');
});

test('reply does not double Re: prefix', function () {
    Mail::fake();

    $original = Email::create([
        'tenant_id' => $this->tenant->id,
        'email_account_id' => $this->emailAccount->id,
        'message_id' => '<msg@test.com>',
        'thread_id' => 'thread-456',
        'folder' => 'Inbox',
        'from_address' => 'client@example.com',
        'from_name' => 'Client',
        'to_addresses' => [['email' => 'lab@kalibrium.test']],
        'subject' => 'Re: Previous topic',
        'body_html' => '<p>Follow up</p>',
        'body_text' => 'Follow up',
        'date' => now(),
        'is_read' => true,
        'direction' => 'inbound',
        'status' => 'received',
    ]);

    $reply = $this->sendService->reply($original, [
        'body' => '<p>Response</p>',
    ]);

    expect($reply->subject)->toBe('Re: Previous topic');
    expect($reply->subject)->not->toContain('Re: Re:');
});

test('forward adds Fwd prefix and original content', function () {
    Mail::fake();

    $original = Email::create([
        'tenant_id' => $this->tenant->id,
        'email_account_id' => $this->emailAccount->id,
        'message_id' => '<fwd@test.com>',
        'thread_id' => 'thread-789',
        'folder' => 'Inbox',
        'from_address' => 'sender@example.com',
        'from_name' => 'Sender',
        'to_addresses' => [['email' => 'lab@kalibrium.test']],
        'subject' => 'Important document',
        'body_html' => '<p>Document content here</p>',
        'body_text' => 'Document content here',
        'date' => now(),
        'is_read' => true,
        'direction' => 'inbound',
        'status' => 'received',
    ]);

    $forwarded = $this->sendService->forward($original, [
        'to' => 'manager@example.com',
        'body' => 'Please review.',
    ]);

    expect($forwarded->subject)->toBe('Fwd: Important document');
    expect($forwarded->body_html)->toContain('Forwarded message');
    expect($forwarded->body_html)->toContain('Document content here');
});

test('forward does not double Fwd: prefix', function () {
    Mail::fake();

    $original = Email::create([
        'tenant_id' => $this->tenant->id,
        'email_account_id' => $this->emailAccount->id,
        'message_id' => '<fwd2@test.com>',
        'thread_id' => 'thread-000',
        'folder' => 'Inbox',
        'from_address' => 'sender@example.com',
        'from_name' => 'Sender',
        'to_addresses' => [['email' => 'lab@kalibrium.test']],
        'subject' => 'Fwd: Already forwarded',
        'body_html' => '<p>Content</p>',
        'body_text' => 'Content',
        'date' => now(),
        'is_read' => true,
        'direction' => 'inbound',
        'status' => 'received',
    ]);

    $forwarded = $this->sendService->forward($original, [
        'to' => 'another@example.com',
    ]);

    expect($forwarded->subject)->toBe('Fwd: Already forwarded');
});

// ── EmailClassifierService (fallback) ──

test('fallback classifier detects orcamento category', function () {
    $email = Email::create([
        'tenant_id' => $this->tenant->id,
        'email_account_id' => $this->emailAccount->id,
        'message_id' => '<classify@test.com>',
        'thread_id' => 'thread-c1',
        'folder' => 'Inbox',
        'from_address' => 'client@example.com',
        'from_name' => 'Client',
        'to_addresses' => [['email' => 'lab@kalibrium.test']],
        'subject' => 'Solicitação de orçamento para calibração',
        'body_html' => '<p>Preciso de cotação para 5 balanças</p>',
        'body_text' => 'Preciso de cotação para 5 balanças',
        'date' => now(),
        'is_read' => false,
        'direction' => 'inbound',
        'status' => 'received',
    ]);

    // Call fallback directly via reflection
    $service = app(EmailClassifierService::class);
    $reflection = new ReflectionMethod($service, 'fallbackClassify');
    $reflection->invoke($service, $email);

    $email->refresh();

    expect($email->ai_category)->toBe('orcamento');
    expect($email->ai_priority)->toBe('alta');
});

test('fallback classifier detects spam', function () {
    $email = Email::create([
        'tenant_id' => $this->tenant->id,
        'email_account_id' => $this->emailAccount->id,
        'message_id' => '<spam@test.com>',
        'thread_id' => 'thread-spam',
        'folder' => 'Inbox',
        'from_address' => 'promo@spam.com',
        'from_name' => 'Spam',
        'to_addresses' => [['email' => 'lab@kalibrium.test']],
        'subject' => 'Amazing newsletter unsubscribe here',
        'body_html' => '<p>Click to unsubscribe from this newsletter</p>',
        'body_text' => 'Click to unsubscribe from this newsletter',
        'date' => now(),
        'is_read' => false,
        'direction' => 'inbound',
        'status' => 'received',
    ]);

    $service = app(EmailClassifierService::class);
    $reflection = new ReflectionMethod($service, 'fallbackClassify');
    $reflection->invoke($service, $email);

    $email->refresh();

    expect($email->ai_category)->toBe('spam');
    expect($email->ai_priority)->toBe('baixa');
    expect($email->ai_suggested_action)->toBe('ignorar');
});
