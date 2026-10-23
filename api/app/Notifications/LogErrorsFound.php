<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Súhrn chýb, ktoré pribudli v laravel.log a dajú sa opraviť v systéme.
 * Posiela App\Console\Commands\LogErrorsReport z plánovača.
 *
 * Zámerne nie ShouldQueue: jednou z hlásených chýb môže byť práve
 * nefunkčný worker fronty a mail by potom neodišiel nikdy.
 */
class LogErrorsFound extends Notification
{
    use Queueable;

    /** Viac druhov v jednom maile už nikto nečíta. */
    private const SHOWN = 20;

    /**
     * @param  list<array{message: string, class: ?string, location: ?string, count: int, first: string, last: string}>  $groups
     */
    public function __construct(
        public array $groups,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $total = array_sum(array_column($this->groups, 'count'));

        $mail = (new MailMessage())
            ->subject('Chyby v logu ('.config('app.name').'): '.count($this->groups).' druh(y), spolu '.$total.'×')
            ->greeting('Dobrý deň,')
            ->line('v `storage/logs/laravel.log` pribudli chyby, ktoré si pravdepodobne žiadajú opravu v kóde, databáze alebo konfigurácii.');

        foreach (array_slice($this->groups, 0, self::SHOWN) as $group) {
            $where = implode(' · ', array_filter([$group['class'], $group['location']]));

            $mail->line('**'.$group['count'].'× · naposledy '.$group['last'].'**')
                ->line($this->escape($group['message']))
                ->lineIf($where !== '', '`'.$where.'`');
        }

        if (count($this->groups) > self::SHOWN) {
            $mail->line('… a ďalších '.(count($this->groups) - self::SHOWN).' druhov chýb.');
        }

        return $mail->line('Celé záznamy so stack trace sú v laravel.log na serveri. Šum z výpadkov cudzích služieb sa vynecháva (config/logging.php → report.ignore).');
    }

    /** Text z logu nesmie rozbiť Markdown e-mailu (hviezdičky, podčiarkovníky…). HTML escapuje šablóna. */
    private function escape(string $text): string
    {
        return preg_replace('/([\\\\`*_{}\[\]#|])/', '\\\\$1', $text);
    }
}
