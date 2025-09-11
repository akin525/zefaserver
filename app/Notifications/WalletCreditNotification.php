<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WalletCreditNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    protected $type;
    protected $amount;
    protected $date;
    protected $desc;

    public function __construct($type,$amount, $date, $desc)
    {
        $this->type = $type;
        $this->amount = $amount;
        $this->date = $date;
        $this->desc = $desc;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => $this->type,
            'amount' => $this->amount,
            'date' => $this->date,
            'desc' => $this->desc,
        ];
    }
    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->line('The introduction to the notification.')
            ->action('Notification Action', url('/'))
            ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
