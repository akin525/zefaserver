<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;

class InterestAccruedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $amount;
    protected $date;
    protected $desc;

    public function __construct($amount, $date, $desc)
    {
        $this->amount = $amount;
        $this->date = $date;
        $this->desc = $desc;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => 'Interest Earned',
            'amount' => $this->amount,
            'date' => $this->date,
            'desc' => $this->desc,
        ];
    }

    public function toArray($notifiable)
    {
        return $this->toDatabase($notifiable);
    }
}
