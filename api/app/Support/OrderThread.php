<?php

namespace App\Support;

use App\Models\Order;
use Symfony\Component\Mime\Email;

/**
 * Jedno e-mailové vlákno na objednávku.
 *
 * Potvrdenie objednávky má pevné Message-ID odvodené z uuid; každý ďalší e-mail
 * k objednávke naň odkazuje a má predmet „Re: …“, takže ho poštový klient zaradí
 * do tej istej konverzácie. ID sa nikde neukladá — dá sa kedykoľvek vypočítať.
 */
class OrderThread
{
    public const FROM = 'obchod@zastavy-vlajky.sk';

    public const FROM_NAME = 'Gajdoš Gabriel – Reprezent';

    public const HEADER = 'X-Order-Ref';

    public static function subject(Order $order): string
    {
        // Číslo v predmete: zákazník ho nájde v schránke aj bez otvorenia e-mailu.
        $number = $order->serial_number ? ' č. '.$order->serial_number : '';
        $name = $order->billing->company ?: $order->billing->name;

        return 'Objednávka'.$number.($name ? ' | '.$name : '');
    }

    public static function replySubject(Order $order): string
    {
        return 'Re: '.self::subject($order);
    }

    /** Admin kópie majú vlastný koreň: dve správy s rovnakým Message-ID by Gmail zlúčil. */
    public static function id(Order $order, bool $admin = false): string
    {
        return 'order-'.$order->uuid.($admin ? '-admin' : '').'@'.substr(strrchr(self::FROM, '@'), 1);
    }

    public static function root(Email $message, Order $order, bool $admin = false): void
    {
        $headers = $message->getHeaders();
        $headers->remove('Message-ID');
        $headers->addIdHeader('Message-ID', self::id($order, $admin));
        self::tag($message, $order, $admin);
    }

    public static function reply(Email $message, Order $order, bool $admin = false): void
    {
        $headers = $message->getHeaders();
        $headers->addIdHeader('In-Reply-To', self::id($order, $admin));
        $headers->addIdHeader('References', self::id($order, $admin));
        self::tag($message, $order, $admin);
    }

    /**
     * Interná značka, ku ktorej objednávke e-mail patrí — podľa nej sa zapíše do
     * histórie e-mailov objednávky (OrderEmail). Kópie pre obsluhu sú označené „admin“.
     */
    public static function tag(Email $message, Order $order, bool $admin = false): void
    {
        $headers = $message->getHeaders();
        $headers->remove(self::HEADER);
        $headers->addTextHeader(self::HEADER, $order->uuid.($admin ? '; admin' : ''));
    }

    /** @return array{0: string, 1: bool}|null  [uuid, je kópia pre obsluhu] */
    public static function parseTag(Email $message): ?array
    {
        $value = $message->getHeaders()->get(self::HEADER)?->getBodyAsString();

        if (! $value || ! preg_match('/^([0-9a-f-]{36})(; admin)?$/i', trim($value), $m)) {
            return null;
        }

        return [$m[1], isset($m[2])];
    }
}
