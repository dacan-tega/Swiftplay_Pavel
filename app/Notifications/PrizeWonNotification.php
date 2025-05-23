<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PrizeWonNotification extends Notification
{
    use Queueable;

    protected $prize;
    protected $gameName;

    /**
     * @param $prize
     * @param $gameName
     */
    public function __construct($prize, $gameName)
    {
        $this->prize = $prize;
        $this->gameName = $gameName;
    }

    /**
     * @param $notifiable
     * @return string[]
     */
    public function via($notifiable)
    {
        return ['database'];
    }

    /**
     * @param $notifiable
     * @return string[]
     */
    public function toArray($notifiable)
    {
        return [
            'message' => __('admin.Congratulations') . \Helper::amountFormatDecimal($this->prize) . __('admin.in_game') . $this->gameName,
        ];
    }
}
