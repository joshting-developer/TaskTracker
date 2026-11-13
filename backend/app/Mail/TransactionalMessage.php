<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Job內建立的純文字信件, 不把明文內容序列化進Queue */
class TransactionalMessage extends Mailable
{
    /** 保存當次解密內容
     * @param  string  $subjectLine  已選定標題
     * @param  string  $messageBody  純文字範本
     */
    public function __construct(private string $subjectLine, private string $messageBody) {}

    /** 固定信件標題
     * @return Envelope Mail標頭
     */
    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    /** 只呈現純文字, 不執行使用者HTML
     * @return Content 純文字Mail內容
     */
    public function content(): Content
    {
        return new Content(text: 'mail.transactional', with: ['body' => $this->messageBody]);
    }
}
