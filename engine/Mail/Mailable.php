<?php
declare(strict_types=1);

namespace Oshim\Mail;

class Mailable
{
    private string $from = 'noreply@oshim.cloud';
    private string $fromName = 'OSHIM Sovereign Cloud';
    private array $to = [];
    private array $cc = [];
    private array $bcc = [];
    private string $subject = '';
    private string $htmlBody = '';
    private string $textBody = '';
    private array $attachments = [];

    public function from(string $address, string $name = ''): static
    {
        $this->from = $address;
        $this->fromName = $name ?: $this->fromName;
        return $this;
    }

    public function to(string|array $address, string $name = ''): static
    {
        if (is_array($address)) {
            foreach ($address as $email => $n) {
                if (is_int($email)) {
                    $this->to[] = ['email' => $n, 'name' => ''];
                } else {
                    $this->to[] = ['email' => $email, 'name' => (string)$n];
                }
            }
        } else {
            $this->to[] = ['email' => $address, 'name' => $name];
        }
        return $this;
    }

    public function subject(string $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    public function html(string $html): static
    {
        $this->htmlBody = $html;
        return $this;
    }

    public function text(string $text): static
    {
        $this->textBody = $text;
        return $this;
    }

    public function attach(string $filename, string $content, string $mimeType = 'application/octet-stream'): static
    {
        $this->attachments[] = [
            'name' => $filename,
            'content' => $content,
            'mime' => $mimeType,
        ];
        return $this;
    }

    public function getFrom(): string { return $this->from; }
    public function getFromName(): string { return $this->fromName; }
    public function getTo(): array { return $this->to; }
    public function getSubject(): string { return $this->subject; }
    public function getHtml(): string { return $this->htmlBody; }
    public function getText(): string { return $this->textBody ?: strip_tags($this->htmlBody); }
    public function getAttachments(): array { return $this->attachments; }
}
